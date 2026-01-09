<?php

namespace App\Services;

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
                                    ->orWhere('scheduled_at', '<=', now());
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
                            $expectedSendDate = $subscribedAt->copy()->startOfDay()->addDays($message->day);

                            // Jeśli oczekiwana data wysyłki jest w przyszłości, pomiń na razie
                            if ($expectedSendDate->gt(now()->startOfDay())) {
                                Log::info('CronScheduleService: Skipping future autoresponder', [
                                    'entry_id' => $entry->id,
                                    'subscriber_id' => $subscriber->id,
                                    'expected_date' => $expectedSendDate->toDateString(),
                                    'today' => now()->toDateString()
                                ]);
                                continue; // Zostanie przetworzony we właściwym dniu
                            }
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
                            $expectedSendDate = $subscribedAt->copy()->startOfDay()->addDays($message->day);

                            // Jeśli oczekiwana data wysyłki jest w przyszłości, pomiń na razie
                            if ($expectedSendDate->gt(now()->startOfDay())) {
                                continue; // Zostanie przetworzony we właściwym dniu
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

            // 2. Tu można dodać inne operacje dzienne:
            // - Usuwanie niepotwierdzonych subskrybentów
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
