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

