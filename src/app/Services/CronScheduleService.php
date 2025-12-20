<?php

namespace App\Services;

use App\Models\ContactList;
use App\Models\ContactListCronSetting;
use App\Models\CronJobLog;
use App\Models\CronSetting;
use App\Models\Message;
use App\Jobs\SendEmailJob;
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
        
        $stats = [
            'dispatched' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        try {
            $globalVolume = (int) CronSetting::getValue('volume_per_minute', 100);
            $listVolumes = []; // Tracker zużycia limitu per lista

            // Pobierz wiadomości do wysłania
            $messages = Message::where('status', 'scheduled')
                ->where('scheduled_at', '<=', now())
                ->orderBy('scheduled_at')
                ->orderBy('priority', 'desc')
                ->limit($globalVolume)
                ->with('contactLists')
                ->get();

            foreach ($messages as $message) {
                // Sprawdź limit globalny
                if ($stats['dispatched'] >= $globalVolume) {
                    $stats['skipped']++;
                    continue;
                }

                // Pobierz listę (pierwszą jeśli wiele)
                $listId = $message->contactLists->first()?->id;
                
                if ($listId) {
                    // Sprawdź harmonogram listy
                    if (!$this->isDispatchAllowed($listId)) {
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

                // Dispatch job
                try {
                    SendEmailJob::dispatch($message);
                    $stats['dispatched']++;
                    $log->incrementSent();
                } catch (\Exception $e) {
                    $stats['errors']++;
                    $log->incrementFailed();
                    $log->appendError("Message {$message->id}: " . $e->getMessage());
                    Log::error("CRON dispatch error: " . $e->getMessage(), ['message_id' => $message->id]);
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
