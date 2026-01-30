<?php

namespace App\Console\Commands;

use Database\Seeders\DefaultAutomationsSeeder;
use Illuminate\Console\Command;

class SeedDefaultAutomations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'automations:seed-defaults {--force : Force seeding even if automations exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed default AutoTag Pro automations for all users';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Seeding default AutoTag Pro automations...');

        $seeder = new DefaultAutomationsSeeder();
        $seeder->run();

        $this->info('✅ Default automations seeded successfully!');
        $this->info("   Created {$seeder->getDefaultCount()} automation templates per user.");

        return Command::SUCCESS;
    }
}
