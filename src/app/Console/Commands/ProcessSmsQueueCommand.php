<?php

namespace App\Console\Commands;

use App\Services\CronScheduleService;
use Illuminate\Console\Command;

class ProcessSmsQueueCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron:process-sms-queue
                            {--dry-run : Wykonaj symulację bez faktycznej wysyłki}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Przetwórz kolejkę SMS zgodnie z harmonogramami CRON';

    /**
     * Execute the console command.
     */
    public function handle(CronScheduleService $cronService): int
    {
        $this->info('📱 Rozpoczynam przetwarzanie kolejki SMS...');

        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('⚠️  Tryb symulacji - żadne SMS-y nie zostaną wysłane');
        }

        try {
            // Sprawdź czy wysyłka jest globalnie dozwolona
            if (!$cronService->isGlobalDispatchAllowed()) {
                $this->warn('⏸️  Wysyłka jest obecnie wstrzymana wg harmonogramu globalnego');
                return 0;
            }

            // Pobierz dozwolone listy
            $allowedLists = $cronService->getListsAllowedForDispatch();

            if ($this->option('verbose')) {
                $this->info('📋 Dozwolone listy: ' . count($allowedLists));
                foreach ($allowedLists as $list) {
                    $this->line("   - {$list['name']} (limit: {$list['volume_per_minute']}/min)");
                }
            }

            if ($isDryRun) {
                $this->info('✅ Symulacja zakończona - kolejka SMS byłaby przetworzona');
                return 0;
            }

            // Przetwórz kolejkę SMS
            $stats = $cronService->processSmsQueue();

            $this->info('✅ Przetwarzanie zakończone:');
            $this->line("   🔄 Zsynchronizowano: {$stats['synced']}");
            $this->line("   📱 Wysłano: {$stats['dispatched']}");
            $this->line("   ⏭️  Pominięto: {$stats['skipped']}");

            if ($stats['errors'] > 0) {
                $this->error("   ❌ Błędy: {$stats['errors']}");
            }

            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Błąd: ' . $e->getMessage());
            return 1;
        }
    }
}
