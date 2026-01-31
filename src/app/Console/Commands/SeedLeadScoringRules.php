<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\LeadScoringRule;
use Illuminate\Console\Command;

class SeedLeadScoringRules extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'netsendo:seed-lead-scoring-rules
                            {--user= : Seed rules for a specific user ID}
                            {--force : Force re-seed rules even if they already exist}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed default Lead Scoring rules for users who don\'t have any';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $userId = $this->option('user');
        $force = $this->option('force');

        if ($userId) {
            // Seed for specific user
            $user = User::find($userId);
            if (!$user) {
                $this->error("User with ID {$userId} not found.");
                return Command::FAILURE;
            }

            return $this->seedForUser($user, $force);
        }

        // Seed for all admin users without rules
        $query = User::whereNull('admin_user_id'); // Only admin users

        if (!$force) {
            // Only users without existing rules
            $query->whereDoesntHave('leadScoringRules');
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->info('All users already have Lead Scoring rules.');
            return Command::SUCCESS;
        }

        $this->info("Seeding Lead Scoring rules for {$users->count()} user(s)...");

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        $seeded = 0;
        $skipped = 0;

        foreach ($users as $user) {
            if ($force) {
                // Delete existing rules if force mode
                LeadScoringRule::where('user_id', $user->id)->delete();
            }

            $existingCount = LeadScoringRule::where('user_id', $user->id)->count();

            if ($existingCount === 0) {
                LeadScoringRule::seedDefaultsForUser($user->id);
                $seeded++;
            } else {
                $skipped++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Done! Seeded: {$seeded}, Skipped (already had rules): {$skipped}");

        return Command::SUCCESS;
    }

    /**
     * Seed rules for a specific user.
     */
    protected function seedForUser(User $user, bool $force): int
    {
        $existingCount = LeadScoringRule::where('user_id', $user->id)->count();

        if ($existingCount > 0 && !$force) {
            $this->info("User {$user->id} ({$user->email}) already has {$existingCount} Lead Scoring rules.");
            $this->info("Use --force to delete and re-seed.");
            return Command::SUCCESS;
        }

        if ($force && $existingCount > 0) {
            LeadScoringRule::where('user_id', $user->id)->delete();
            $this->info("Deleted {$existingCount} existing rules.");
        }

        LeadScoringRule::seedDefaultsForUser($user->id);
        $newCount = LeadScoringRule::where('user_id', $user->id)->count();

        $this->info("Seeded {$newCount} Lead Scoring rules for user {$user->id} ({$user->email}).");

        return Command::SUCCESS;
    }
}
