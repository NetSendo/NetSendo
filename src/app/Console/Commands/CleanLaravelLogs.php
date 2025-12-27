<?php

namespace App\Console\Commands;

use App\Models\LogSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CleanLaravelLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:clean {--force : Force clean regardless of retention time}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean Laravel log file based on retention settings';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $logPath = storage_path('logs/laravel.log');

        if (!File::exists($logPath)) {
            $this->info('Log file does not exist.');
            return self::SUCCESS;
        }

        $retentionHours = LogSetting::getRetentionHours();
        $lastModified = File::lastModified($logPath);
        $ageInHours = (time() - $lastModified) / 3600;

        if ($this->option('force') || $ageInHours >= $retentionHours) {
            // Truncate file instead of deleting (keep file for logging)
            File::put($logPath, '');

            $this->info("Log file cleaned. Age was: " . round($ageInHours, 1) . "h, retention: {$retentionHours}h");

            // Log the cleanup action
            \Log::info('[logs:clean] Laravel log file cleaned', [
                'age_hours' => round($ageInHours, 1),
                'retention_hours' => $retentionHours,
                'forced' => $this->option('force'),
            ]);

            return self::SUCCESS;
        }

        $this->info("Log file not old enough. Age: " . round($ageInHours, 1) . "h, retention: {$retentionHours}h");
        return self::SUCCESS;
    }
}
