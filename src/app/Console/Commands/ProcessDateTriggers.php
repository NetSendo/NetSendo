<?php

namespace App\Console\Commands;

use App\Services\Automation\DateTriggerService;
use Illuminate\Console\Command;

class ProcessDateTriggers extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'automations:process-date-triggers 
                            {--birthdays : Process only birthday triggers}
                            {--anniversaries : Process only anniversary triggers}
                            {--dates : Process only date_reached triggers}';

    /**
     * The console command description.
     */
    protected $description = 'Process date-based automation triggers (birthdays, anniversaries, specific dates)';

    /**
     * Execute the console command.
     */
    public function handle(DateTriggerService $service): int
    {
        $this->info('Processing date-based triggers...');

        $startTime = microtime(true);
        $stats = [];

        if ($this->option('birthdays')) {
            $stats['birthdays_processed'] = $service->processBirthdays();
        } elseif ($this->option('anniversaries')) {
            $stats['anniversaries_processed'] = $service->processAnniversaries();
        } elseif ($this->option('dates')) {
            $stats['date_triggers_processed'] = $service->processDateTriggers();
        } else {
            // Process all if no specific option
            $stats = $service->processAll();
        }

        $duration = round(microtime(true) - $startTime, 2);

        $this->info('');
        $this->info('Results:');
        $this->table(
            ['Trigger Type', 'Processed'],
            collect($stats)->map(fn($count, $key) => [
                str_replace('_', ' ', ucfirst($key)),
                $count
            ])->toArray()
        );
        $this->info("Completed in {$duration}s");

        return Command::SUCCESS;
    }
}
