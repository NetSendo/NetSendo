<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CampaignAuditorService;
use Illuminate\Console\Command;

class RunCampaignAuditCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:run
                            {--user= : Run audit for a specific user ID}
                            {--all : Run audit for all users}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run AI Campaign Audit for users (daily automated task)';

    /**
     * Execute the console command.
     */
    public function handle(CampaignAuditorService $auditorService): int
    {
        $userId = $this->option('user');
        $runForAll = $this->option('all');

        if ($userId) {
            // Run for specific user
            $user = User::find($userId);
            if (!$user) {
                $this->error("User with ID {$userId} not found.");
                return self::FAILURE;
            }

            $this->runAuditForUser($user, $auditorService);
            return self::SUCCESS;
        }

        if ($runForAll) {
            // Run for all users
            $users = User::all();
            $this->info("Running campaign audit for {$users->count()} users...");

            $bar = $this->output->createProgressBar($users->count());
            $bar->start();

            foreach ($users as $user) {
                $this->runAuditForUser($user, $auditorService, silent: true);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info('✓ Campaign audits completed for all users.');
            return self::SUCCESS;
        }

        // Default: run for all users (for cron/scheduler)
        $users = User::all();

        foreach ($users as $user) {
            $this->runAuditForUser($user, $auditorService, silent: true);
        }

        $this->info("Campaign audits completed for {$users->count()} users.");
        return self::SUCCESS;
    }

    /**
     * Run audit for a single user.
     */
    protected function runAuditForUser(User $user, CampaignAuditorService $auditorService, bool $silent = false): void
    {
        try {
            $audit = $auditorService->runAudit($user);

            if (!$silent) {
                $this->info("✓ Audit completed for user {$user->email}");
                $this->line("  Score: {$audit->overall_score}/100 ({$audit->getScoreLabel()})");
                $this->line("  Issues: {$audit->critical_count} critical, {$audit->warning_count} warnings, {$audit->info_count} info");
            }
        } catch (\Exception $e) {
            if (!$silent) {
                $this->error("✗ Audit failed for user {$user->email}: {$e->getMessage()}");
            }

            \Log::error("Campaign audit failed for user {$user->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
