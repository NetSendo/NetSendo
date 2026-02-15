<?php

namespace App\Services;

use App\Models\AbTest;
use App\Models\ContactList;
use App\Models\ContactListCronSetting;
use App\Models\CronJobLog;
use App\Models\CronSetting;
use App\Models\Message;
use App\Models\MessageQueueEntry;
use App\Jobs\SendEmailJob;
use App\Jobs\SendSmsJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CronScheduleService
{
    /**
     * Pobierz wszystkie ustawienia globalne
     */
    public function getGlobalSettings(): array
    {
        return [
            'volume_per_minute' => (int) CronSetting::getValue('volume_per_minute', 100),
            'daily_maintenance_hour' => (int) CronSetting::getValue('daily_maintenance_hour', 4),
            'last_run' => CronSetting::getValue('last_run'),
            'last_daily_run' => CronSetting::getValue('last_daily_run'),
            'schedule' => CronSetting::getGlobalSchedule(),
        ];
    }

    /**
     * Zapisz ustawienia globalne
     */
    public function saveGlobalSettings(array $settings): void
    {
        if (isset($settings['volume_per_minute'])) {
            CronSetting::setValue('volume_per_minute', $settings['volume_per_minute'], 'Maksymalna liczba wiadomości na minutę');
        }

        if (isset($settings['daily_maintenance_hour'])) {
            CronSetting::setValue('daily_maintenance_hour', $settings['daily_maintenance_hour'], 'Godzina operacji dziennych');
        }

        if (isset($settings['schedule'])) {
            CronSetting::setGlobalSchedule($settings['schedule']);
        }
    }

    /**
     * Pobierz ustawienia dla listy
     */
    public function getListSettings(int $contactListId): array
    {
        $settings = ContactListCronSetting::getOrCreateForList($contactListId);

        return [
            'use_defaults' => $settings->use_defaults,
            'volume_per_minute' => $settings->volume_per_minute,
            'schedule' => $settings->schedule ?? CronSetting::getDefaultSchedule(),
            'effective_schedule' => $settings->getEffectiveSchedule(),
            'effective_volume' => $settings->getEffectiveVolumePerMinute(),
        ];
    }

    /**
     * Zapisz ustawienia dla listy
     */
    public function saveListSettings(int $contactListId, array $settings): ContactListCronSetting
    {
        $cronSettings = ContactListCronSetting::getOrCreateForList($contactListId);

        $cronSettings->update([
            'use_defaults' => $settings['use_defaults'] ?? true,
            'volume_per_minute' => $settings['use_defaults'] ? null : ($settings['volume_per_minute'] ?? null),
            'schedule' => $settings['use_defaults'] ? null : ($settings['schedule'] ?? null),
        ]);

        return $cronSettings->fresh();
    }

    /**
     * Sprawdź czy wysyłka jest dozwolona dla listy w danym momencie
     */
    public function isDispatchAllowed(int $contactListId, ?\DateTimeInterface $dateTime = null): bool
    {
        $settings = ContactListCronSetting::getOrCreateForList($contactListId);
        return $settings->isDispatchAllowedNow($dateTime);
    }

    /**
     * Sprawdź czy wysyłka jest dozwolona globalnie
     */
    public function isGlobalDispatchAllowed(?\DateTimeInterface $dateTime = null): bool
    {
        $dateTime = $dateTime ?? now();
        $schedule = CronSetting::getGlobalSchedule();
        $dayName = strtolower($dateTime->format('l'));

        if (!isset($schedule[$dayName])) {
            return true;
        }

        $daySchedule = $schedule[$dayName];

        if (!($daySchedule['enabled'] ?? true)) {
            return false;
        }

        $minuteOfDay = ((int) $dateTime->format('G') * 60) + (int) $dateTime->format('i');
        $start = $daySchedule['start'] ?? 0;
        $end = $daySchedule['end'] ?? 1440;

        if ($start === $end) {
            return false;
        }

        return $minuteOfDay >= $start && $minuteOfDay <= $end;
    }

    /**
     * Pobierz listy dla których wysyłka jest aktualnie dozwolona
     */
    public function getListsAllowedForDispatch(): array
    {
        $allowedListIds = [];

        // Pobierz wszystkie listy z ustawieniami
        $lists = ContactList::with('cronSettings')->get();

        foreach ($lists as $list) {
            $settings = $list->cronSettings ?? ContactListCronSetting::getOrCreateForList($list->id);

            if ($settings->isDispatchAllowedNow()) {
                $allowedListIds[] = [
                    'id' => $list->id,
                    'name' => $list->name,
                    'volume_per_minute' => $settings->getEffectiveVolumePerMinute(),
                ];
            }
        }

        return $allowedListIds;
    }

    /**
     * Przetworz kolejkę wiadomości zgodnie z harmonogramami
     */
    public function processQueue(): array
    {
        $log = CronJobLog::startJob('email_queue_processor');
        Log::info('CronScheduleService: Starting processQueue');

        $stats = [
            'dispatched' => 0,
            'skipped' => 0,
            'errors' => 0,
            'synced' => 0,
        ];

        try {
            $globalVolume = (int) CronSetting::getValue('volume_per_minute', 100);
            $listVolumes = []; // Tracker zużycia limitu per lista

            // 1. Pobierz aktywne wiadomości i zsynchronizuj odbiorców
            // Optymalizacja: pobieramy tylko schedulowane wiadomości, które "powinny" być już wysłane
            $activeMessages = Message::where('status', 'scheduled')
                ->where('channel', 'email') // Filter only email messages
                ->where(function($query) {
                    $query->where('type', 'broadcast')
                        ->orWhere(function($q) {
                            $q->where('type', 'autoresponder')
                                ->where('is_active', true);
                        });
                })
                ->where(function($q) {
                    $q->whereNull('scheduled_at')
                        ->orWhere('scheduled_at', '<=', now());
                })
                ->with('contactLists')
                ->get();

            Log::info('CronScheduleService: Found active messages', ['count' => $activeMessages->count()]);

            foreach ($activeMessages as $message) {
                // Sync only if recipients synchronization is needed or it's a new message
                // TODO: Consider adding a flag or check to avoid syncing every minute for static lists
                // For now, we keep it to ensure new subscribers are picked up
                $syncResult = $message->syncPlannedRecipients();
                Log::info('CronScheduleService: Message sync result', [
                    'message_id' => $message->id,
                    'type' => $message->type,
                    'day' => $message->day,
                    'results' => $syncResult
                ]);
                $stats['synced'] += $syncResult['added'];
            }

            // 2. Przetwórz kolejkę używając chunków, aby ominąć zablokowane listy
            // (Zapobiega "Head-of-Line Blocking")

            $lastId = 0;
            $batchSize = 200; // Mniejszy batch dla bezpieczeństwa pamięci
            $maxInspected = 2000; // Zabezpieczenie przed timeoutem (sprawdzamy max X wpisów w jednym przebiegu)
            $totalInspected = 0;

            while ($stats['dispatched'] < $globalVolume && $totalInspected < $maxInspected) {
                $entries = MessageQueueEntry::where('status', MessageQueueEntry::STATUS_PLANNED)
                    ->where('id', '>', $lastId)
                    ->whereHas('message', function($query) {
                        $query->where('status', 'scheduled')
                            ->where('channel', 'email') // Filter only email messages
                            ->where(function($q) {
                                $q->where('type', 'broadcast')
                                    ->orWhere(function($sub) {
                                        $sub->where('type', 'autoresponder')
                                            ->where('is_active', true);
                                    });
                            })
                            ->where(function($q) {
                                $q->whereNull('scheduled_at')
                                    ->orWhere('scheduled_at', '<=', now())
                                    // Allow timezone-aware messages through — per-subscriber gating happens in the loop
                                    ->orWhere('send_in_subscriber_timezone', true);
                            });
                    })
                    ->with(['message.contactLists.defaultMailbox', 'message.mailbox', 'subscriber'])
                    ->orderBy('id', 'asc') // Sortowanie po ID dla kursora
                    ->limit($batchSize)
                    ->get();

                if ($entries->isEmpty()) {
                    break;
                }

                foreach ($entries as $entry) {
                    $lastId = $entry->id;
                    $totalInspected++;

                    // Sprawdź limit globalny ponownie
                    if ($stats['dispatched'] >= $globalVolume) {
                        break 2;
                    }

                    $message = $entry->message;
                    $subscriber = $entry->subscriber;

                    // Sprawdź czy subskrybent jest nadal aktywny
                    if (!$subscriber || $subscriber->status !== 'active') {
                        $entry->markAsSkipped('Subscriber is no longer active');
                        $stats['skipped']++;
                        continue;
                    }

                    // Dla autoresponderów: sprawdź czy minęło wystarczająco dni od subskrypcji
                    if ($message->type === 'autoresponder' && $message->day !== null) {
                        $listIds = $message->contactLists->pluck('id')->toArray();
                        $pivot = $subscriber->contactLists()
                            ->whereIn('contact_lists.id', $listIds)
                            ->first()?->pivot;

                        if ($pivot && $pivot->subscribed_at) {
                            $subscribedAt = Carbon::parse($pivot->subscribed_at);
                            $dayOffset = $message->day ?? 0;
                            $timeOfDay = $message->time_of_day;

                            // Calculate expected send datetime with full time component
                            $expectedSendDateTime = $subscribedAt->copy()->addDays($dayOffset);

                            if ($timeOfDay) {
                                // If time_of_day is set, use that specific hour on the expected day
                                $timeParts = explode(':', $timeOfDay);
                                $hour = (int) ($timeParts[0] ?? 0);
                                $minute = (int) ($timeParts[1] ?? 0);

                                // Determine target timezone for the time_of_day
                                $targetTimezone = 'UTC';
                                if ($message->send_in_subscriber_timezone) {
                                    $targetTimezone = $subscriber->getEffectiveTimezone(
                                        $message->effective_timezone
                                    );
                                }

                                // Build the expected send time in the target timezone, then convert to UTC
                                $expectedSendDateTime = $expectedSendDateTime->copy()
                                    ->startOfDay()
                                    ->shiftTimezone($targetTimezone)
                                    ->setTime($hour, $minute, 0)
                                    ->setTimezone('UTC');
                            }
                            // If no time_of_day, we keep the original time from subscribed_at
                            // This means for day=0: expectedSendDateTime = subscribedAt (immediate)

                            // If the expected send datetime is in the future, skip for now
                            if ($expectedSendDateTime->gt(now('UTC'))) {
                                Log::info('CronScheduleService: Skipping future autoresponder', [
                                    'entry_id' => $entry->id,
                                    'subscriber_id' => $subscriber->id,
                                    'expected_datetime' => $expectedSendDateTime->format('Y-m-d H:i'),
                                    'now' => now('UTC')->format('Y-m-d H:i'),
                                    'subscriber_timezone' => $message->send_in_subscriber_timezone
                                        ? $subscriber->getEffectiveTimezone($message->effective_timezone)
                                        : null,
                                ]);
                                continue; // Will be processed at the appropriate time
                            }
                        }
                    }

                    // For broadcasts with send_in_subscriber_timezone:
                    // gate each subscriber individually based on their timezone
                    if ($message->type === 'broadcast'
                        && $message->send_in_subscriber_timezone
                        && $message->send_at
                    ) {
                        // Get the intended local time from the message's own timezone
                        $intendedLocalTime = $message->send_at->copy()
                            ->setTimezone($message->effective_timezone);

                        // Determine subscriber's timezone (fallback to message's effective timezone)
                        $subscriberTz = $subscriber->getEffectiveTimezone($message->effective_timezone);

                        // Re-interpret the same local time in the subscriber's timezone and convert to UTC
                        $subscriberSendAtUtc = $intendedLocalTime->copy()
                            ->shiftTimezone($subscriberTz)
                            ->setTimezone('UTC');

                        if ($subscriberSendAtUtc->gt(now('UTC'))) {
                            Log::debug('CronScheduleService: Skipping broadcast - not yet time in subscriber timezone', [
                                'entry_id' => $entry->id,
                                'subscriber_id' => $subscriber->id,
                                'subscriber_tz' => $subscriberTz,
                                'subscriber_send_at_utc' => $subscriberSendAtUtc->format('Y-m-d H:i'),
                            ]);
                            continue;
                        }
                    }

                    // Pobierz listę (pierwszą jeśli wiele)
                    $listId = $message->contactLists->first()?->id;

                    if ($listId) {
                        // Sprawdź harmonogram listy
                        if (!$this->isDispatchAllowed($listId)) {
                            // Tutaj tylko inkrementujemy skipped w statystykach, ale NIE zmieniamy statusu w bazie na SKIPPED
                            // Dzięki temu wpis zostanie przetworzony, gdy harmonogram na to zezwoli.
                            // W tej pętli po prostu go pomijamy i idziemy do następnego.
                            $stats['skipped']++;
                            continue;
                        }

                        // Sprawdź limit listy
                        $settings = ContactListCronSetting::getOrCreateForList($listId);
                        $listVolume = $settings->getEffectiveVolumePerMinute();
                        $listVolumes[$listId] = ($listVolumes[$listId] ?? 0);

                        if ($listVolumes[$listId] >= $listVolume) {
                            $stats['skipped']++;
                            continue;
                        }

                        $listVolumes[$listId]++;
                    }

                    // A/B Test handling - check sample_percentage limit
                    $abTest = $message->abTest;
                    if ($abTest && in_array($abTest->status, [AbTest::STATUS_DRAFT, AbTest::STATUS_RUNNING])) {
                        // Calculate sample size
                        $totalRecipients = $message->planned_recipients_count ?: $message->getUniqueRecipients()->count();
                        $sampleSize = (int) ceil($totalRecipients * ($abTest->sample_percentage / 100));

                        // Count how many already sent in test
                        $sentInTest = MessageQueueEntry::where('message_id', $message->id)
                            ->whereNotNull('ab_test_variant_id')
                            ->whereIn('status', [MessageQueueEntry::STATUS_QUEUED, MessageQueueEntry::STATUS_SENT])
                            ->count();

                        // If sample limit reached, skip this entry for now
                        if ($sentInTest >= $sampleSize) {
                            Log::debug('A/B Test sample limit reached', [
                                'message_id' => $message->id,
                                'test_id' => $abTest->id,
                                'sample_size' => $sampleSize,
                                'sent' => $sentInTest,
                            ]);
                            continue;
                        }

                        // Assign variant if not already assigned
                        if (!$entry->ab_test_variant_id) {
                            $abTestService = app(AbTestService::class);
                            $variant = $abTestService->assignVariant($abTest, $subscriber);
                            $entry->update(['ab_test_variant_id' => $variant->id]);
                            Log::debug('A/B Test variant assigned', [
                                'entry_id' => $entry->id,
                                'variant_id' => $variant->id,
                                'variant_letter' => $variant->variant_letter,
                            ]);
                        }

                        // Start test if still in draft
                        if ($abTest->status === AbTest::STATUS_DRAFT) {
                            $abTest->update([
                                'status' => AbTest::STATUS_RUNNING,
                                'test_started_at' => now(),
                            ]);
                            Log::info('A/B Test started', ['test_id' => $abTest->id]);
                        }
                    }

                    // Oznacz jako w kolejce i wyślij
                    try {
                        $entry->markAsQueued();

                        // Dispatch job dla konkretnego subskrybenta
                        // Pass entry ID so the job can update status upon completion
                        SendEmailJob::dispatch($message, $subscriber, null, $entry->id);
                        Log::info('CronScheduleService: Job dispatched', [
                            'entry_id' => $entry->id,
                            'subscriber_id' => $subscriber->id
                        ]);

                        // Note: markAsSent() is now called by SendEmailJob after successful delivery
                        // This ensures accurate status tracking

                        $stats['dispatched']++;
                        $log->incrementSent();

                        // Note: sent_count increment is now done by SendEmailJob

                        // Dla broadcast: sprawdzanie statusu zostaje, ale używamy 'queued' zamiast 'sent'
                        // Broadcast zostanie oznaczony jako 'sent' gdy wszystkie wpisy będą 'sent' (sprawdzane w SendEmailJob)
                    } catch (\Exception $e) {
                        $entry->markAsFailed($e->getMessage());
                        $stats['errors']++;
                        $log->incrementFailed();
                        $log->appendError("Entry {$entry->id} (Message {$message->id}, Subscriber {$subscriber->id}): " . $e->getMessage());
                        Log::error("CRON dispatch error: " . $e->getMessage(), [
                            'entry_id' => $entry->id,
                            'message_id' => $message->id,
                            'subscriber_id' => $subscriber->id,
                        ]);
                    }
                }
            }

            // Zapisz timestamp ostatniego uruchomienia
            CronSetting::setValue('last_run', now()->toIso8601String());

            $log->completeSuccess($stats['dispatched'], $stats['errors']);
        } catch (\Exception $e) {
            $log->completeFailed($e->getMessage(), $stats['dispatched'], $stats['errors']);
            Log::error("CRON queue processor failed: " . $e->getMessage());
            throw $e;
        }

        return $stats;
    }

    /**
     * Przetwórz kolejkę wiadomości SMS zgodnie z harmonogramami
     */
    public function processSmsQueue(): array
    {
        $log = CronJobLog::startJob('sms_queue_processor');

        $stats = [
            'dispatched' => 0,
            'skipped' => 0,
            'errors' => 0,
            'synced' => 0,
        ];

        try {
            $globalVolume = (int) CronSetting::getValue('volume_per_minute', 100);
            $listVolumes = [];

            // 1. Pobierz aktywne wiadomości SMS i zsynchronizuj odbiorców
            $activeMessages = Message::where('status', 'scheduled')
                ->where('channel', 'sms')
                ->where(function($query) {
                    $query->where('type', 'broadcast')
                        ->orWhere(function($q) {
                            $q->where('type', 'autoresponder')
                                ->where('is_active', true);
                        });
                })
                ->where(function($q) {
                    $q->whereNull('scheduled_at')
                        ->orWhere('scheduled_at', '<=', now());
                })
                ->with('contactLists')
                ->get();

            foreach ($activeMessages as $message) {
                $syncResult = $message->syncPlannedRecipients();
                $stats['synced'] += $syncResult['added'];
            }

            // 2. Przetwórz kolejkę SMS używając chunków
            $lastId = 0;
            $batchSize = 200;
            $maxInspected = 2000;
            $totalInspected = 0;

            while ($stats['dispatched'] < $globalVolume && $totalInspected < $maxInspected) {
                $entries = MessageQueueEntry::where('status', MessageQueueEntry::STATUS_PLANNED)
                    ->where('id', '>', $lastId)
                    ->whereHas('message', function($query) {
                        $query->where('status', 'scheduled')
                            ->where('channel', 'sms')
                            ->where(function($q) {
                                $q->where('type', 'broadcast')
                                    ->orWhere(function($sub) {
                                        $sub->where('type', 'autoresponder')
                                            ->where('is_active', true);
                                    });
                            })
                            ->where(function($q) {
                                $q->whereNull('scheduled_at')
                                    ->orWhere('scheduled_at', '<=', now());
                            });
                    })
                    ->with(['message.contactLists', 'subscriber'])
                    ->orderBy('id', 'asc')
                    ->limit($batchSize)
                    ->get();

                if ($entries->isEmpty()) {
                    break;
                }

                foreach ($entries as $entry) {
                    $lastId = $entry->id;
                    $totalInspected++;

                    if ($stats['dispatched'] >= $globalVolume) {
                        break 2;
                    }

                    $message = $entry->message;
                    $subscriber = $entry->subscriber;

                    // Sprawdź czy subskrybent ma numer telefonu i jest aktywny
                    if (!$subscriber || $subscriber->status !== 'active' || empty($subscriber->phone)) {
                        $entry->markAsSkipped('Subscriber inactive or has no phone number');
                        $stats['skipped']++;
                        continue;
                    }

                    // Dla autoresponderów SMS: sprawdź czy minęło wystarczająco dni od subskrypcji
                    if ($message->type === 'autoresponder' && $message->day !== null) {
                        $listIds = $message->contactLists->pluck('id')->toArray();
                        $pivot = $subscriber->contactLists()
                            ->whereIn('contact_lists.id', $listIds)
                            ->first()?->pivot;

                        if ($pivot && $pivot->subscribed_at) {
                            $subscribedAt = Carbon::parse($pivot->subscribed_at);
                            $dayOffset = $message->day ?? 0;
                            $timeOfDay = $message->time_of_day;

                            // Calculate expected send datetime with full time component
                            $expectedSendDateTime = $subscribedAt->copy()->addDays($dayOffset);

                            if ($timeOfDay) {
                                $timeParts = explode(':', $timeOfDay);
                                $hour = (int) ($timeParts[0] ?? 0);
                                $minute = (int) ($timeParts[1] ?? 0);
                                $expectedSendDateTime = $expectedSendDateTime->copy()->startOfDay()->setTime($hour, $minute, 0);
                            }

                            // If the expected send datetime is in the future, skip for now
                            if ($expectedSendDateTime->gt(now())) {
                                continue; // Will be processed at the appropriate time
                            }
                        }
                    }

                    $listId = $message->contactLists->first()?->id;

                    if ($listId) {
                        if (!$this->isDispatchAllowed($listId)) {
                            $stats['skipped']++;
                            continue;
                        }

                        $settings = ContactListCronSetting::getOrCreateForList($listId);
                        $listVolume = $settings->getEffectiveVolumePerMinute();
                        $listVolumes[$listId] = ($listVolumes[$listId] ?? 0);

                        if ($listVolumes[$listId] >= $listVolume) {
                            $stats['skipped']++;
                            continue;
                        }

                        $listVolumes[$listId]++;
                    }

                    try {
                        $entry->markAsQueued();

                        SendSmsJob::dispatch($message, $subscriber, null, $entry->id);

                        $stats['dispatched']++;
                        $log->incrementSent();
                    } catch (\Exception $e) {
                        $entry->markAsFailed($e->getMessage());
                        $stats['errors']++;
                        $log->incrementFailed();
                        $log->appendError("SMS Entry {$entry->id} (Message {$message->id}, Subscriber {$subscriber->id}): " . $e->getMessage());
                        Log::error("CRON SMS dispatch error: " . $e->getMessage(), [
                            'entry_id' => $entry->id,
                            'message_id' => $message->id,
                            'subscriber_id' => $subscriber->id,
                        ]);
                    }
                }
            }

            CronSetting::setValue('last_sms_run', now()->toIso8601String());

            $log->completeSuccess($stats['dispatched'], $stats['errors']);
        } catch (\Exception $e) {
            $log->completeFailed($e->getMessage(), $stats['dispatched'], $stats['errors']);
            Log::error("CRON SMS queue processor failed: " . $e->getMessage());
            throw $e;
        }

        return $stats;
    }

    /**
     * Usuń niepotwierdzone subskrypcje z list które mają włączoną tę opcję
     */
    public function deleteUnconfirmedSubscribers(): array
    {
        $stats = [
            'lists_processed' => 0,
            'subscribers_deleted' => 0,
        ];

        // Pobierz wszystkie listy z włączonym delete_unconfirmed
        $lists = ContactList::all();

        foreach ($lists as $list) {
            $settings = $list->settings ?? [];
            $deleteUnconfirmed = $settings['subscription']['delete_unconfirmed'] ?? false;

            if (!$deleteUnconfirmed) {
                continue;
            }

            // Domyślnie 7 dni jeśli nie ustawiono
            $afterDays = $settings['subscription']['delete_unconfirmed_after_days'] ?? 7;
            $cutoffDate = now()->subDays($afterDays);

            // Znajdź ID niepotwierdzonych subskrybentów starszych niż X dni
            $subscriberIds = $list->subscribers()
                ->wherePivot('status', 'unconfirmed')
                ->wherePivot('subscribed_at', '<', $cutoffDate)
                ->pluck('subscriber_id')
                ->toArray();

            if (count($subscriberIds) > 0) {
                // Usuń relacje z tabeli pivot
                $deleted = $list->subscribers()->detach($subscriberIds);

                Log::info("Deleted {$deleted} unconfirmed subscribers from list {$list->id} ({$list->name})");
                $stats['subscribers_deleted'] += $deleted;
            }

            $stats['lists_processed']++;
        }

        return $stats;
    }

    /**
     * Uruchom operacje dzienne
     */
    public function runDailyMaintenance(): array
    {
        $log = CronJobLog::startJob('daily_maintenance');
        $results = [];

        try {
            // 1. Wyczyść stare logi CRON
            $deletedLogs = CronJobLog::cleanupOld(30);
            $results['deleted_cron_logs'] = $deletedLogs;

            // 2. Usuń niepotwierdzone subskrypcje
            $unconfirmedResults = $this->deleteUnconfirmedSubscribers();
            $results['deleted_unconfirmed_subscribers'] = $unconfirmedResults['subscribers_deleted'];
            $results['lists_checked_for_unconfirmed'] = $unconfirmedResults['lists_processed'];

            // 3. Tu można dodać inne operacje dzienne:
            // - Przetwarzanie odbitych wiadomości
            // - Archiwizacja starej kolejki

            // Zapisz timestamp
            CronSetting::setValue('last_daily_run', now()->toIso8601String());

            $log->completeSuccess();
            Log::info("Daily maintenance completed", $results);
        } catch (\Exception $e) {
            $log->completeFailed($e->getMessage());
            Log::error("Daily maintenance failed: " . $e->getMessage());
            throw $e;
        }

        return $results;
    }

    /**
     * Pobierz statystyki CRON
     */
    public function getStats(): array
    {
        $globalSettings = $this->getGlobalSettings();
        $last24h = CronJobLog::getLast24HoursStats();
        $recentLogs = CronJobLog::getRecent(10);

        return [
            'global_settings' => $globalSettings,
            'stats_24h' => $last24h,
            'recent_logs' => $recentLogs,
            'lists_allowed_now' => $this->getListsAllowedForDispatch(),
            'is_global_dispatch_allowed' => $this->isGlobalDispatchAllowed(),
        ];
    }
}
