<?php

namespace App\Console\Commands;

use App\Events\CrmDealIdle;
use App\Models\CrmDeal;
use App\Models\AutomationRule;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessCrmIdleDeals extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'crm:process-idle-deals {--days=7 : Minimum days of inactivity}';

    /**
     * The console command description.
     */
    protected $description = 'Process CRM deals that have been idle (no activity) for a specified number of days';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $minIdleDays = (int) $this->option('days');

        $this->info("Processing idle deals (minimum {$minIdleDays} days of inactivity)...");

        // Get all users who have active crm_deal_idle automation rules
        $userIdsWithRules = AutomationRule::where('trigger_event', 'crm_deal_idle')
            ->where('is_active', true)
            ->pluck('user_id')
            ->unique();

        if ($userIdsWithRules->isEmpty()) {
            $this->info('No active crm_deal_idle automation rules found.');
            return Command::SUCCESS;
        }

        $processedCount = 0;

        foreach ($userIdsWithRules as $userId) {
            // Get the specific idle_days configurations for this user's rules
            $userRules = AutomationRule::where('user_id', $userId)
                ->where('trigger_event', 'crm_deal_idle')
                ->where('is_active', true)
                ->get();

            foreach ($userRules as $rule) {
                $idleDays = (int) ($rule->trigger_config['idle_days'] ?? $minIdleDays);
                $pipelineId = $rule->trigger_config['pipeline_id'] ?? null;

                $cutoffDate = Carbon::now()->subDays($idleDays);

                // Find deals that haven't been updated since the cutoff date
                $query = CrmDeal::where('user_id', $userId)
                    ->where('status', 'open') // Only open deals
                    ->where('updated_at', '<=', $cutoffDate);

                if ($pipelineId) {
                    $query->where('crm_pipeline_id', $pipelineId);
                }

                $idleDeals = $query->get();

                foreach ($idleDeals as $deal) {
                    // Calculate actual idle days
                    $actualIdleDays = Carbon::parse($deal->updated_at)->diffInDays(now());

                    // Dispatch the event
                    event(new CrmDealIdle($deal, $actualIdleDays));

                    $processedCount++;

                    Log::info('CRM Deal Idle event dispatched', [
                        'deal_id' => $deal->id,
                        'deal_name' => $deal->name,
                        'idle_days' => $actualIdleDays,
                        'user_id' => $userId,
                    ]);
                }
            }
        }

        $this->info("Processed {$processedCount} idle deals.");

        return Command::SUCCESS;
    }
}
