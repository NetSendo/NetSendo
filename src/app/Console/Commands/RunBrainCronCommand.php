<?php

namespace App\Console\Commands;

use App\Models\AiActionPlan;
use App\Models\AiActionPlanStep;
use App\Models\AiBrainActivityLog;
use App\Models\AiBrainSettings;
use App\Models\AiGoal;
use App\Models\AiPendingApproval;
use App\Models\User;
use App\Services\Brain\AgentOrchestrator;
use App\Services\Brain\GoalPlanner;
use App\Services\Brain\KnowledgeBaseService;
use App\Services\Brain\ModeController;
use App\Services\Brain\SituationAnalyzer;
use App\Services\Brain\Skills\MarketingSalesSkill;
use App\Services\Brain\Telegram\TelegramBotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunBrainCronCommand extends Command
{
    protected $signature = 'brain:run-cron';

    protected $description = 'Run Brain AI orchestration for users with active cron scheduling';

    public function handle(AgentOrchestrator $orchestrator): int
    {
        $this->info('[Brain CRON] Starting orchestration cycle...');

        // Find all users with cron enabled
        $settings = AiBrainSettings::where('cron_enabled', true)
            ->whereNotNull('cron_interval_minutes')
            ->get();

        if ($settings->isEmpty()) {
            $this->info('[Brain CRON] No users with active cron. Skipping.');
            return self::SUCCESS;
        }

        $processed = 0;
        $skipped = 0;

        foreach ($settings as $setting) {
            $user = User::find($setting->user_id);
            if (!$user) {
                $this->warn("[Brain CRON] User #{$setting->user_id} not found. Skipping.");
                continue;
            }

            // Check if enough time has passed since last cron run
            $intervalMinutes = (int) $setting->cron_interval_minutes;
            if ($setting->last_cron_run_at) {
                $minutesSinceLastRun = $setting->last_cron_run_at->diffInMinutes(now());
                if ($minutesSinceLastRun < $intervalMinutes) {
                    $remaining = $intervalMinutes - $minutesSinceLastRun;
                    $this->line("[Brain CRON] User #{$user->id} ({$user->name}): next run in {$remaining}min. Skipping.");
                    $skipped++;
                    continue;
                }
            }

            $this->info("[Brain CRON] Processing user #{$user->id} ({$user->name})...");

            try {
                $this->processUserCron($orchestrator, $user, $setting);
                $processed++;
            } catch (\Exception $e) {
                $this->error("[Brain CRON] Error for user #{$user->id}: {$e->getMessage()}");
                Log::error('Brain CRON error', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Log the error
                AiBrainActivityLog::logEvent(
                    $user->id,
                    'cron_error',
                    'error',
                    null,
                    ['error' => $e->getMessage()],
                );
            }
        }

        $this->info("[Brain CRON] Cycle complete. Processed: {$processed}, Skipped: {$skipped}");
        return self::SUCCESS;
    }

    /**
     * Process cron orchestration for a single user.
     */
    private function processUserCron(AgentOrchestrator $orchestrator, User $user, AiBrainSettings $settings): void
    {
        $workMode = $settings->work_mode;
        $cronResults = [
            'analysis' => null,
            'goals_created' => [],
            'tasks_executed' => [],
            'tasks_pending_approval' => [],
            'knowledge_saved' => false,
        ];

        // Step 0: AI-powered situation analysis
        $analysisReport = null;
        try {
            $analyzer = app(SituationAnalyzer::class);
            $analysisReport = $analyzer->analyze($user);

            if ($analysisReport) {
                $this->info("[Brain CRON] User #{$user->id}: Situation analysis complete.");
                $this->line("[Brain CRON]   Summary: " . mb_substr($analysisReport['summary'] ?? '', 0, 200));
                $cronResults['analysis'] = $analysisReport;
            } else {
                $this->line("[Brain CRON] User #{$user->id}: Situation analysis returned no results.");
            }
        } catch (\Exception $e) {
            $this->warn("[Brain CRON] User #{$user->id}: Situation analysis failed: {$e->getMessage()}");
            Log::warning('Brain CRON situation analysis failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Step 1: Auto-create goals if none exist
        try {
            $cronResults['goals_created'] = $this->autoCreateGoals($user, $analysisReport, $workMode);
            if (!empty($cronResults['goals_created'])) {
                $this->info("[Brain CRON] User #{$user->id}: Auto-created " . count($cronResults['goals_created']) . " goals.");
            }
        } catch (\Exception $e) {
            $this->warn("[Brain CRON] User #{$user->id}: Auto-goal creation failed: {$e->getMessage()}");
        }

        // Step 2: Enrich knowledge base from analysis insights
        try {
            $cronResults['knowledge_saved'] = $this->enrichKnowledgeBase($user, $analysisReport);
        } catch (\Exception $e) {
            $this->warn("[Brain CRON] User #{$user->id}: KB enrichment failed: {$e->getMessage()}");
        }

        // Step 3: Convert AI priorities to executable tasks
        $aiTasks = [];
        if ($analysisReport && !empty($analysisReport['priorities'])) {
            $aiTasks = collect($analysisReport['priorities'])
                ->map(fn($p) => [
                    'id' => 'ai_analysis_' . md5($p['action'] ?? ''),
                    'category' => 'ai_analysis',
                    'icon' => '🧠',
                    'title' => $p['title'] ?? $p['action'] ?? 'AI Task',
                    'description' => $p['reasoning'] ?? '',
                    'priority' => $p['priority'] ?? 'medium',
                    'action' => $p['action'] ?? '',
                    'agent' => $p['agent'] ?? null,
                    'parameters' => array_filter([
                        'target_list_ids' => $p['target_list_ids'] ?? [],
                        'exclude_segments' => $p['exclude_segments'] ?? [],
                    ]),
                ])->toArray();
        }

        // Step 4: Get rule-based suggested tasks from MarketingSalesSkill
        $suggestedTasks = MarketingSalesSkill::getSuggestedTasks($user);

        // Step 5: Merge — AI analysis priorities first, then rule-based tasks
        $allTasks = array_merge($aiTasks, $suggestedTasks);

        // Determine minimum priority threshold
        $minPriority = $settings->cron_min_priority ?? 'high';
        $acceptedPriorities = $this->getAcceptedPriorities($minPriority);

        // Filter to accepted priority tasks
        $eligibleTasks = collect($allTasks)
            ->filter(fn($t) => in_array($t['priority'] ?? 'medium', $acceptedPriorities))
            ->values();

        if ($eligibleTasks->isEmpty()) {
            $this->line("[Brain CRON] User #{$user->id}: No eligible tasks. Updating timestamps.");

            $settings->update(['last_cron_run_at' => now()]);

            AiBrainActivityLog::logEvent(
                $user->id,
                'cron_run',
                'success',
                null,
                [
                    'message' => 'No eligible tasks found.',
                    'suggested_tasks_count' => count($allTasks),
                    'eligible_count' => 0,
                    'analysis_summary' => $analysisReport['summary'] ?? null,
                    'goals_created' => count($cronResults['goals_created']),
                ],
            );

            $this->sendTelegramReport($settings, $user, collect(), [], $cronResults);
            return;
        }

        $this->info("[Brain CRON] User #{$user->id}: Found {$eligibleTasks->count()} eligible tasks (mode: {$workMode}).");

        // Step 6: Route based on work mode
        if ($workMode === ModeController::MODE_SEMI_AUTO) {
            // Semi-auto: create plans and send approval requests to Telegram
            $cronResults['tasks_pending_approval'] = $this->handleSemiAutoTasks(
                $orchestrator, $user, $settings, $eligibleTasks
            );
        } elseif ($workMode === ModeController::MODE_AUTONOMOUS) {
            // Autonomous: execute immediately
            $cronResults['tasks_executed'] = $this->handleAutonomousTasks(
                $orchestrator, $user, $eligibleTasks
            );
        }
        // Manual mode: just report, don't execute

        // Update timestamps
        $settings->update([
            'last_cron_run_at' => now(),
            'last_activity_at' => now(),
        ]);

        // Telegram report
        $this->sendTelegramReport($settings, $user, $eligibleTasks, $cronResults['tasks_executed'], $cronResults);

        $executedCount = count(array_filter($cronResults['tasks_executed'], fn($r) => $r === 'success'));
        $pendingCount = count($cronResults['tasks_pending_approval']);
        $this->info("[Brain CRON] User #{$user->id}: Executed: {$executedCount}, Pending approval: {$pendingCount}");
    }

    /**
     * Handle tasks in autonomous mode — execute immediately.
     */
    private function handleAutonomousTasks(
        AgentOrchestrator $orchestrator,
        User $user,
        $tasks,
    ): array {
        $taskResults = [];

        foreach ($tasks as $i => $task) {
            $this->line("[Brain CRON]   → Executing: {$task['title']}");

            try {
                $result = $orchestrator->executeCronTask($task, $user);
                $status = ($result['type'] ?? '') === 'error' ? 'error' : 'success';
                $taskResults[$i] = $status;

                $this->line("[Brain CRON]   ✓ Result: {$status}");

                AiBrainActivityLog::logEvent(
                    $user->id,
                    'cron_task_executed',
                    $status,
                    $task['agent'] ?? null,
                    [
                        'task_id' => $task['id'] ?? null,
                        'task_title' => $task['title'] ?? null,
                        'category' => $task['category'] ?? null,
                        'result_type' => $result['type'] ?? null,
                        'result_message' => mb_substr($result['message'] ?? '', 0, 500),
                    ],
                );
            } catch (\Exception $e) {
                $taskResults[$i] = 'error';
                $this->warn("[Brain CRON]   ✗ Task failed: {$e->getMessage()}");
                Log::warning('Brain CRON task execution failed', [
                    'user_id' => $user->id,
                    'task' => $task['title'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $taskResults;
    }

    /**
     * Handle tasks in semi-auto mode — create plans and send for approval via Telegram.
     */
    private function handleSemiAutoTasks(
        AgentOrchestrator $orchestrator,
        User $user,
        AiBrainSettings $settings,
        $tasks,
    ): array {
        $pendingTasks = [];

        foreach ($tasks as $i => $task) {
            $this->line("[Brain CRON]   → Creating plan for approval: {$task['title']}");

            try {
                $agentName = $task['agent'] ?? null;
                $agents = $orchestrator->getAgents();
                $agent = $agents[$agentName] ?? null;

                if (!$agent) {
                    $this->warn("[Brain CRON]   ✗ Agent '{$agentName}' not found.");
                    continue;
                }

                // Gather auto-context and build intent
                $autoContext = $orchestrator->gatherAutoContext($user, $agentName);
                $intent = [
                    'requires_agent' => true,
                    'agent' => $agentName,
                    'intent' => $task['action'] ?? $task['title'] ?? '',
                    'task_type' => $agentName,
                    'confidence' => 1.0,
                    'channel' => 'cron',
                    'has_user_details' => true,
                    'parameters' => array_merge(
                        $task['parameters'] ?? [],
                        ['auto_context' => $autoContext],
                        ['cron_task' => true],
                    ),
                ];

                $knowledgeContext = app(KnowledgeBaseService::class)->getContext($user, $agentName);

                // Create the plan (but DON'T execute)
                $plan = $agent->plan($intent, $user, $knowledgeContext);

                if (!$plan) {
                    $this->warn("[Brain CRON]   ✗ Plan creation failed for: {$task['title']}");
                    continue;
                }

                // Create pending approval
                $plan->update(['status' => 'pending_approval']);
                $approval = AiPendingApproval::create([
                    'ai_action_plan_id' => $plan->id,
                    'user_id' => $user->id,
                    'channel' => 'telegram',
                    'status' => 'pending',
                    'summary' => "📋 **{$plan->title}**\n{$plan->description}\n\n🤖 Agent: {$agentName}\n⏰ " . __('brain.approval_expiry'),
                    'expires_at' => now()->addHours(24),
                ]);

                // Send individual approval request to Telegram
                $this->sendTaskApprovalToTelegram($settings, $user, $plan, $approval, $task);

                $pendingTasks[] = [
                    'plan_id' => $plan->id,
                    'approval_id' => $approval->id,
                    'title' => $task['title'],
                ];

                $this->line("[Brain CRON]   📩 Sent for Telegram approval (plan #{$plan->id})");

                AiBrainActivityLog::logEvent(
                    $user->id,
                    'cron_task_pending_approval',
                    'pending',
                    $agentName,
                    [
                        'task_title' => $task['title'] ?? null,
                        'plan_id' => $plan->id,
                        'approval_id' => $approval->id,
                    ],
                );
            } catch (\Exception $e) {
                $this->warn("[Brain CRON]   ✗ Semi-auto task failed: {$e->getMessage()}");
                Log::warning('Brain CRON semi-auto task failed', [
                    'user_id' => $user->id,
                    'task' => $task['title'] ?? '',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $pendingTasks;
    }

    /**
     * Auto-create goals from situation analysis when user has zero active goals.
     * In semi-auto mode: sends proposals to Telegram for approval.
     * In autonomous mode: creates goals immediately.
     */
    private function autoCreateGoals(User $user, ?array $analysisReport, string $workMode = 'autonomous'): array
    {
        if (!$analysisReport || empty($analysisReport['priorities'])) {
            return [];
        }

        // Check if user already has active goals
        try {
            $activeGoals = AiGoal::forUser($user->id)->active()->count();
            if ($activeGoals > 0) {
                return []; // User already has goals, don't auto-create
            }
        } catch (\Exception $e) {
            return []; // Table may not exist
        }

        $goalPlanner = app(GoalPlanner::class);
        $createdGoals = [];

        // Create goals from top priorities (max 3)
        $topPriorities = collect($analysisReport['priorities'])
            ->where('priority', 'high')
            ->take(3);

        foreach ($topPriorities as $priority) {
            try {
                $title = $priority['title'] ?? 'Strategic Goal';
                $description = $priority['reasoning'] ?? $priority['action'] ?? null;
                $goalPriority = $priority['priority'] ?? 'medium';

                if ($workMode === ModeController::MODE_SEMI_AUTO) {
                    // Semi-auto: propose goal via Telegram for approval
                    $approval = $this->proposeGoalForApproval($user, $title, $description, $goalPriority);

                    if ($approval) {
                        $createdGoals[] = [
                            'id' => 'pending_' . $approval->id,
                            'title' => $title,
                            'status' => 'pending_approval',
                            'approval_id' => $approval->id,
                        ];
                        $this->line("[Brain CRON]   🎯 Goal proposed for approval: {$title}");
                    }
                } else {
                    // Autonomous: create immediately
                    $goal = $goalPlanner->createGoal(
                        $user,
                        $title,
                        $description,
                        $goalPriority,
                        null,
                        null,
                    );

                    AiBrainActivityLog::logEvent($user->id, 'auto_goal_created', 'completed', null, [
                        'goal_id' => $goal->id,
                        'title' => $goal->title,
                        'source' => 'cron_analysis',
                    ]);

                    $createdGoals[] = [
                        'id' => $goal->id,
                        'title' => $goal->title,
                        'status' => 'created',
                    ];

                    $this->line("[Brain CRON]   🎯 Auto-created goal: {$goal->title}");
                }
            } catch (\Exception $e) {
                Log::warning('Auto-goal creation failed', [
                    'title' => $priority['title'] ?? '',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $createdGoals;
    }

    /**
     * Propose a goal for Telegram approval in semi-auto mode.
     */
    private function proposeGoalForApproval(User $user, string $title, ?string $description, string $priority): ?AiPendingApproval
    {
        $settings = AiBrainSettings::getForUser($user->id);

        // Store the goal data in the approval record so we can create it when approved
        $approval = AiPendingApproval::create([
            'user_id' => $user->id,
            'channel' => 'telegram',
            'status' => 'pending',
            'summary' => json_encode([
                'type' => 'goal_proposal',
                'title' => $title,
                'description' => $description,
                'priority' => $priority,
            ]),
            'expires_at' => now()->addHours(48),
        ]);

        // Send to Telegram
        if ($settings->isTelegramConnected() && !empty($settings->telegram_chat_id)) {
            try {
                $telegram = app(TelegramBotService::class);

                $priorityIcon = match ($priority) {
                    'urgent' => '🔴',
                    'high' => '🟠',
                    'medium' => '🟡',
                    'low' => '🟢',
                    default => '⚪',
                };

                $message = "🎯 *" . __('brain.goal_proposal_title') . "*\n\n";
                $message .= "*{$title}*\n";
                if ($description) {
                    $message .= "{$description}\n";
                }
                $message .= "\n{$priorityIcon} " . __('brain.monitor.priority') . ": {$priority}\n";
                $message .= "⏰ " . __('brain.approval_expiry');

                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '✅ ' . __('brain.approve'), 'callback_data' => "approve_goal:{$approval->id}"],
                            ['text' => '❌ ' . __('brain.reject'), 'callback_data' => "reject_goal:{$approval->id}"],
                        ],
                    ],
                ];

                $telegram->sendMessage($settings->telegram_chat_id, $message, $keyboard);
            } catch (\Exception $e) {
                Log::warning('Telegram goal proposal failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $approval;
    }

    /**
     * Enrich knowledge base from situation analysis insights.
     */
    private function enrichKnowledgeBase(User $user, ?array $analysisReport): bool
    {
        if (!$analysisReport || empty($analysisReport['summary'])) {
            return false;
        }

        try {
            $kb = app(KnowledgeBaseService::class);

            // Save the analysis summary as a knowledge entry
            $content = "📊 Sytuacja na " . now()->format('Y-m-d H:i') . ":\n\n";
            $content .= $analysisReport['summary'] . "\n";

            if (!empty($analysisReport['priorities'])) {
                $content .= "\nPriorytety:\n";
                foreach ($analysisReport['priorities'] as $p) {
                    $content .= "- [{$p['priority']}] {$p['title']}: {$p['reasoning']}\n";
                }
            }

            $kb->addEntry(
                $user,
                'auto_analysis',
                'Analiza sytuacji — ' . now()->format('d.m.Y H:i'),
                $content,
                'cron_analysis'
            );

            $this->line("[Brain CRON]   📚 Analysis saved to knowledge base.");
            return true;
        } catch (\Exception $e) {
            Log::warning('KB enrichment from CRON failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Send a single task approval request to Telegram with inline buttons.
     */
    private function sendTaskApprovalToTelegram(
        AiBrainSettings $settings,
        User $user,
        AiActionPlan $plan,
        AiPendingApproval $approval,
        array $task,
    ): void {
        if (!$settings->isTelegramConnected() || empty($settings->telegram_chat_id)) {
            return;
        }

        try {
            $telegram = app(TelegramBotService::class);

            $message = "🤖 *" . __('brain.monitor.cron_approval_title') . "*\n\n";
            $message .= "📋 *{$plan->title}*\n";
            if ($plan->description) {
                $message .= "{$plan->description}\n";
            }
            $message .= "\n🎯 Agent: {$task['agent']}\n";
            $message .= "📊 " . __('brain.monitor.priority') . ": {$task['priority']}\n";
            $message .= "\n⏰ " . __('brain.approval_expiry');

            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '✅ ' . __('brain.approve'), 'callback_data' => "approve:{$approval->id}"],
                        ['text' => '❌ ' . __('brain.reject'), 'callback_data' => "reject:{$approval->id}"],
                    ],
                ],
            ];

            $telegram->sendMessage($settings->telegram_chat_id, $message, $keyboard);
        } catch (\Exception $e) {
            Log::warning('Telegram approval message failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get accepted priority levels based on minimum threshold.
     */
    private function getAcceptedPriorities(string $minPriority): array
    {
        return match ($minPriority) {
            'low' => ['low', 'medium', 'high'],
            'medium' => ['medium', 'high'],
            'high' => ['high'],
            default => ['high'],
        };
    }

    /**
     * Send a summary report to Telegram (if connected).
     */
    private function sendTelegramReport(
        AiBrainSettings $settings,
        User $user,
        $tasks,
        array $taskResults,
        array $cronResults,
    ): void {
        if (!$settings->isTelegramConnected() || empty($settings->telegram_chat_id)) {
            return;
        }

        try {
            $telegram = app(TelegramBotService::class);
            $interval = (int) $settings->cron_interval_minutes;
            $nextRun = now()->addMinutes($interval)->format('H:i');
            $workMode = $settings->work_mode;

            $lines = ["🧠 *Brain CRON Report*\n"];

            // Include AI situation analysis summary
            $analysisReport = $cronResults['analysis'] ?? null;
            if ($analysisReport && !empty($analysisReport['summary'])) {
                $lines[] = "📋 " . $analysisReport['summary'] . "\n";
            }

            // Report auto-created goals
            $goalsCreated = $cronResults['goals_created'] ?? [];
            if (!empty($goalsCreated)) {
                $lines[] = "🎯 *" . __('brain.monitor.goals_auto_created', ['count' => count($goalsCreated)]) . "*";
                foreach ($goalsCreated as $goal) {
                    $lines[] = "  • {$goal['title']}";
                }
                $lines[] = "";
            }

            // Report knowledge enrichment
            if ($cronResults['knowledge_saved'] ?? false) {
                $lines[] = "📚 " . __('brain.monitor.kb_enriched');
            }

            // Report tasks
            if ($tasks->isEmpty()) {
                $lines[] = __('brain.monitor.cron_no_tasks');
            } else {
                $lines[] = __('brain.monitor.cron_tasks_found', ['count' => $tasks->count()]) . "\n";

                if ($workMode === ModeController::MODE_SEMI_AUTO) {
                    // Semi-auto: report pending approvals
                    $pendingTasks = $cronResults['tasks_pending_approval'] ?? [];
                    foreach ($tasks as $i => $task) {
                        $isPending = collect($pendingTasks)->contains('title', $task['title']);
                        $icon = $isPending ? '📩' : '⏭️';
                        $lines[] = "{$icon} {$task['title']}";
                    }

                    if (!empty($pendingTasks)) {
                        $lines[] = "\n📩 " . __('brain.monitor.cron_pending_approval', ['count' => count($pendingTasks)]);
                    }
                } elseif ($workMode === ModeController::MODE_AUTONOMOUS) {
                    // Autonomous: report executed tasks
                    foreach ($tasks as $i => $task) {
                        $status = $taskResults[$i] ?? 'skipped';
                        $icon = $status === 'success' ? '✅' : ($status === 'error' ? '❌' : '⏭️');
                        $lines[] = "{$icon} {$task['title']}";
                    }

                    $successCount = collect($taskResults)->filter(fn($r) => $r === 'success')->count();
                    $lines[] = "\n📊 " . __('brain.monitor.cron_executed', [
                        'success' => $successCount,
                        'total' => $tasks->count(),
                    ]);
                } else {
                    // Manual mode: just list suggestions
                    foreach ($tasks as $task) {
                        $lines[] = "💡 {$task['title']}";
                    }
                    $lines[] = "\n📝 " . __('brain.monitor.cron_manual_mode');
                }
            }

            $lines[] = "⏰ " . __('brain.monitor.cron_next_run', ['time' => $nextRun]);

            $message = implode("\n", $lines);
            $telegram->sendMessage($settings->telegram_chat_id, $message);
        } catch (\Exception $e) {
            Log::warning('Brain CRON Telegram report failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
