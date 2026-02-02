<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| CRON Scheduler Configuration
|--------------------------------------------------------------------------
|
| Poniżej zdefiniowane są zadania CRON dla NetSendo.
| Aby uruchomić scheduler, dodaj do crontab serwera:
| * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
|
*/

// Przetwarzanie kolejki emaili - co minutę
Schedule::command('cron:process-queue')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/cron-queue.log'));

// Przetwarzanie kolejki SMS - co minutę
Schedule::command('cron:process-sms-queue')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/cron-sms-queue.log'));

// Operacje dzienne (czyszczenie logów, etc.) - o 4:00
Schedule::command('cron:daily-maintenance')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/cron-daily.log'));

// Czyszczenie starych kolejek Laravel (opcjonalne)
Schedule::command('queue:prune-batches --hours=48')
    ->dailyAt('04:30');

// Czyszczenie cache (opcjonalne, raz na tydzień)
Schedule::command('cache:prune-stale-tags')
    ->weekly()
    ->sundays()
    ->at('03:00');

// System Backup (Baza danych + Pliki)
Schedule::command('backup:run')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/backup.log'));

Schedule::command('backup:clean')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/backup.log'));

// Automatyzacje oparte na datach (urodziny, rocznice, konkretne daty) - co minutę
// Zabezpieczone wewnętrznym cachem przed wysłaniem duplikatów
Schedule::command('automations:process-date-triggers')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/cron-automations.log'));

// Sprawdzanie dostępnych aktualizacji NetSendo - co godzinę
Schedule::command('netsendo:check-updates')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/cron-updates.log'));

// Weryfikacja licencji z serwerem zewnętrznym - raz dziennie o 6:00
Schedule::command('license:verify --deactivate')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/license-verify.log'));

// Test połączenia MCP - raz dziennie o 5:30
Schedule::command('mcp:test-connection --silent')
    ->dailyAt('05:30')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/mcp-status.log'));

// AI Campaign Audit - raz dziennie o 5:00
// Automatyczna analiza kampanii dla wszystkich użytkowników
Schedule::command('audit:run --all')
    ->dailyAt('05:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/campaign-audit.log'));

// Czyszczenie logów Laravel - co godzinę sprawdza retencję
Schedule::command('logs:clean')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/log-cleanup.log'));

// A/B Testing - Ewaluacja testów i automatyczny wybór zwycięzcy
Schedule::job(new \App\Jobs\ProcessAbTestsJob)
    ->everyFiveMinutes()
    ->withoutOverlapping();

// CRM Follow-up Sequences - Przetwarzanie zapisów i tworzenie zadań
Schedule::command('crm:process-follow-ups')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/crm-follow-ups.log'));

// CRM Task Reminders - Wysyłka przypomnień o zadaniach
Schedule::command('crm:send-task-reminders')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/crm-reminders.log'));

// CRM Overdue Tasks - Powiadomienia o zaległych zadaniach
Schedule::command('crm:check-overdue-tasks')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/crm-overdue.log'));

// CRM Score Decay - Spadek punktów dla nieaktywnych kontaktów
Schedule::command('crm:process-score-decay')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/crm-score-decay.log'));

// Google Calendar - Czyszczenie osieroconych wydarzeń (wydarzenia dla usuniętych zadań)
Schedule::command('calendar:sync-orphaned-events')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/calendar-orphaned-sync.log'));

// Google Calendar - Odświeżanie kanałów push notification
Schedule::command('calendar:refresh-channels')
    ->everyThreeHours()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/calendar-channels.log'));

// Google Calendar - Synchronizacja oczekujących zadań (safety net dla niezsynchronizowanych)
Schedule::command('calendar:sync-pending-tasks')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/calendar-pending-sync.log'));

/*
|--------------------------------------------------------------------------
| Deliverability Shield Jobs
|--------------------------------------------------------------------------
*/

// Sprawdzanie konfiguracji DNS domen - co 6 godzin
Schedule::command('deliverability:check-domains')
    ->everySixHours()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/deliverability-dns.log'));

// Analiza możliwych upgrade'ów polityki DMARC - raz dziennie o 7:00
Schedule::command('deliverability:upgrade-dmarc')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/deliverability-dmarc.log'));

/*
|--------------------------------------------------------------------------
| NetSendo Mail Infrastructure (NMI) Jobs
|--------------------------------------------------------------------------
| These jobs only run when NMI is enabled.
*/

// NMI - Procesowanie aktualizacji IP warming - raz dziennie o 6:00
Schedule::command('nmi:process-warming')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/nmi-warming.log'));

// NMI - Sprawdzanie IP na blacklistach - co 6 godzin
Schedule::command('nmi:check-blacklists')
    ->everySixHours()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/nmi-blacklists.log'));

// NMI - Rotacja kluczy DKIM (jeśli auto-rotate włączone) - raz w tygodniu
Schedule::command('nmi:rotate-dkim')
    ->weekly()
    ->sundays()
    ->at('04:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/nmi-dkim.log'));
