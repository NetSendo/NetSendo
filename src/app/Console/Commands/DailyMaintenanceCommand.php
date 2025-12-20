<?php

namespace App\Console\Commands;

use App\Services\CronScheduleService;
use Illuminate\Console\Command;

class DailyMaintenanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron:daily-maintenance 
                            {--force : Wymuś uruchomienie nawet jeśli już było dzisiaj}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Wykonaj dzienne operacje konserwacyjne (czyszczenie logów, itp.)';

    /**
     * Execute the console command.
     */
    public function handle(CronScheduleService $cronService): int
    {
        $this->info('🔧 Rozpoczynam dzienne operacje konserwacyjne...');

        try {
            $results = $cronService->runDailyMaintenance();

            $this->info('✅ Operacje dzienne zakończone:');
            
            if (isset($results['deleted_cron_logs'])) {
                $this->line("   🗑️  Usunięto starych logów CRON: {$results['deleted_cron_logs']}");
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Błąd: ' . $e->getMessage());
            return 1;
        }
    }
}
