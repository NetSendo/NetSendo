<?php

namespace App\Console\Commands;

use App\Services\LicenseVerificationService;
use Illuminate\Console\Command;

class VerifyLicenseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'license:verify
                            {--force : Force verification even if recently checked}
                            {--deactivate : Auto-deactivate license if marked inactive by server}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify license status with external license server';

    /**
     * Execute the console command.
     */
    public function handle(LicenseVerificationService $verificationService): int
    {
        $this->info('🔐 Sprawdzanie statusu licencji NetSendo...');
        $this->newLine();

        $result = $verificationService->checkLicenseStatus();

        if (!$result['has_license']) {
            $this->warn('⚠️  Brak aktywnej licencji.');
            $this->info('   Przejdź do panelu licencji, aby aktywować.');
            return Command::SUCCESS;
        }

        if (!$result['checked']) {
            $this->error('❌ Nie udało się sprawdzić statusu licencji.');
            $this->line('   Powód: ' . ($result['message'] ?? 'Nieznany błąd'));
            return Command::FAILURE;
        }

        // Wyświetl wyniki
        $this->info('📋 Status licencji:');
        $this->table(
            ['Pole', 'Wartość'],
            [
                ['Valid', $result['valid'] ? '✅ Tak' : '❌ Nie'],
                ['Active', $result['is_active'] ? '✅ Tak' : '❌ Nie'],
                ['Plan', $result['plan'] ?? 'N/A'],
                ['Expires', $result['expires_at'] ?? 'Lifetime'],
                ['Message', $result['message'] ?? '-'],
            ]
        );

        // Sprawdź czy należy dezaktywować licencję
        if (isset($result['should_deactivate']) && $result['should_deactivate'] === true) {
            $this->newLine();
            $this->warn('⚠️  Serwer oznaczył licencję jako nieaktywną!');

            if ($this->option('deactivate')) {
                // GOLD falls back to the free SILVER licence; other licences are removed
                if ($verificationService->endLicense() === 'downgraded') {
                    $this->warn('🟡 GOLD zakończony: przełączono na licencję SILVER.');
                } else {
                    $this->error('🔴 Licencja została dezaktywowana.');
                }
                return Command::SUCCESS;
            } else {
                $this->line('   Użyj --deactivate aby automatycznie dezaktywować.');
            }
        }

        if ($result['valid'] && $result['is_active']) {
            $this->newLine();
            $this->info('✅ Licencja jest aktywna i ważna.');
            return Command::SUCCESS;
        }

        return Command::SUCCESS;
    }
}
