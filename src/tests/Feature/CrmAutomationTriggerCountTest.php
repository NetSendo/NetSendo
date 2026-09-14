<?php

namespace Tests\Feature;

use App\Events\CrmActivityLogged;
use App\Events\CrmContactCreated;
use App\Events\CrmContactStatusChanged;
use App\Events\CrmDealCreated;
use App\Events\CrmDealIdle;
use App\Events\CrmDealStageChanged;
use App\Events\CrmScoreThresholdReached;
use App\Events\CrmTaskCompleted;
use App\Events\CrmTaskOverdue;
use App\Models\AutomationRule;
use App\Models\AutomationRuleLog;
use App\Models\CrmContact;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmStage;
use App\Models\CrmTask;
use App\Models\Subscriber;
use App\Models\User;
use App\Notifications\DealStageChangedNotification;
use App\Notifications\TaskOverdueNotification;
use App\Services\Automation\AutomationActionExecutor;
use App\Services\Automation\AutomationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * TriggerAutomationsListener, CrmAutomationListener and CrmEventListener each
 * passed the same CRM events to AutomationService::processEvent, so a matching
 * rule ran its actions three times per deal stage change and twice per other
 * CRM event. Each CRM event must reach every trigger it maps to exactly once.
 */
class CrmAutomationTriggerCountTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private CrmStage $lead;
    private CrmStage $negotiation;
    private CrmStage $won;
    private CrmStage $lost;
    private CrmContact $contact;
    private CrmDeal $deal;
    private CrmTask $task;

    protected function setUp(): void
    {
        parent::setUp();

        // Run queued listeners inline, so a trigger fired from the queue counts too.
        config(['queue.default' => 'sync']);
        Notification::fake();

        $this->user = User::factory()->create();

        $pipeline = CrmPipeline::create(['user_id' => $this->user->id, 'name' => 'Sales']);
        $stage = fn (string $name, int $order, array $flags = []) => CrmStage::create([
            'crm_pipeline_id' => $pipeline->id,
            'name' => $name,
            'order' => $order,
        ] + $flags);

        $this->lead = $stage('Lead', 1);
        $this->negotiation = $stage('Negotiation', 2);
        $this->won = $stage('Won', 3, ['is_won' => true]);
        $this->lost = $stage('Lost', 4, ['is_lost' => true]);

        $this->contact = CrmContact::create([
            'user_id' => $this->user->id,
            'subscriber_id' => Subscriber::create([
                'user_id' => $this->user->id,
                'email' => 'anna@example.com',
                'status' => 'active',
            ])->id,
            'status' => 'lead',
            'score' => 0,
        ]);

        $this->deal = CrmDeal::create([
            'user_id' => $this->user->id,
            'crm_pipeline_id' => $pipeline->id,
            'crm_stage_id' => $this->lead->id,
            'crm_contact_id' => $this->contact->id,
            'owner_id' => $this->user->id,
            'name' => 'Big Deal',
            'value' => 1000,
            'status' => 'open',
        ]);

        $this->task = CrmTask::create([
            'user_id' => $this->user->id,
            'owner_id' => $this->user->id,
            'crm_contact_id' => $this->contact->id,
            'title' => 'Call back',
            'type' => 'call',
            'priority' => 'high',
            'status' => 'pending',
        ]);
    }

    public function test_a_stage_change_fires_crm_deal_stage_changed_once(): void
    {
        $this->assertSame(
            ['crm_deal_stage_changed'],
            $this->triggersFiredBy(new CrmDealStageChanged($this->deal, $this->lead, $this->negotiation)),
        );
    }

    public function test_a_move_to_a_won_stage_also_fires_crm_deal_won_once(): void
    {
        $this->assertSame(
            ['crm_deal_stage_changed', 'crm_deal_won'],
            $this->triggersFiredBy(new CrmDealStageChanged($this->deal, $this->negotiation, $this->won)),
        );

        // CrmEventListener still tells the owner about the won deal.
        Notification::assertSentToTimes($this->user, DealStageChangedNotification::class, 1);
    }

    public function test_a_move_to_a_lost_stage_also_fires_crm_deal_lost_once(): void
    {
        $this->assertSame(
            ['crm_deal_stage_changed', 'crm_deal_lost'],
            $this->triggersFiredBy(new CrmDealStageChanged($this->deal, $this->negotiation, $this->lost)),
        );
    }

    public function test_every_other_crm_event_fires_its_trigger_once(): void
    {
        $activity = $this->contact->activities()->create([
            'user_id' => $this->user->id,
            'type' => 'note',
            'content' => 'Called, no answer',
        ]);

        $events = [
            'crm_deal_created' => new CrmDealCreated($this->deal),
            'crm_deal_idle' => new CrmDealIdle($this->deal, 14),
            'crm_task_completed' => new CrmTaskCompleted($this->task),
            'crm_task_overdue' => new CrmTaskOverdue($this->task),
            'crm_contact_created' => new CrmContactCreated($this->contact),
            'crm_contact_status_changed' => new CrmContactStatusChanged($this->contact, 'lead', 'prospect'),
            'crm_score_threshold' => new CrmScoreThresholdReached($this->contact, 40, 60, 50),
            'crm_activity_logged' => new CrmActivityLogged($activity),
        ];

        $expected = $fired = [];

        foreach ($events as $trigger => $event) {
            $expected[$trigger] = [$trigger];
            $fired[$trigger] = $this->triggersFiredBy($event);
        }

        $this->assertSame($expected, $fired);
    }

    public function test_crm_event_listener_still_notifies_the_task_owner(): void
    {
        $this->triggersFiredBy(new CrmTaskOverdue($this->task));

        Notification::assertSentToTimes($this->user, TaskOverdueNotification::class, 1);
    }

    public function test_winning_a_deal_runs_each_matching_rule_once(): void
    {
        // SQLite keeps the trigger enum of create_automation_rules_table; MySQL
        // gained the CRM triggers in expand_automation_trigger_events.
        Schema::table('automation_rules', fn (Blueprint $table) => $table->string('trigger_event')->change());

        $rule = fn (string $trigger, string $title) => AutomationRule::create([
            'user_id' => $this->user->id,
            'name' => $title,
            'trigger_event' => $trigger,
            'trigger_config' => [],
            'actions' => [['type' => 'crm_create_task', 'config' => ['title' => $title]]],
            'is_active' => true,
        ]);

        $stageChanged = $rule('crm_deal_stage_changed', 'Review stage change');
        $won = $rule('crm_deal_won', 'Start onboarding');

        $this->deal->moveToStage($this->won);

        $this->assertSame(1, AutomationRuleLog::where('automation_rule_id', $stageChanged->id)->count());
        $this->assertSame(1, AutomationRuleLog::where('automation_rule_id', $won->id)->count());
        $this->assertSame(1, CrmTask::where('title', 'Review stage change')->count());
        $this->assertSame(1, CrmTask::where('title', 'Start onboarding')->count());
    }

    /**
     * Dispatch the event against a recording AutomationService.
     *
     * @return list<string> trigger names passed to processEvent, in call order
     */
    private function triggersFiredBy(object $event): array
    {
        $recorder = new class(app(AutomationActionExecutor::class)) extends AutomationService {
            /** @var list<string> */
            public array $triggers = [];

            public function processEvent(string $triggerEvent, array $context): void
            {
                $this->triggers[] = $triggerEvent;
            }
        };

        $this->app->instance(AutomationService::class, $recorder);

        try {
            event($event);
        } finally {
            $this->app->forgetInstance(AutomationService::class);
        }

        return $recorder->triggers;
    }
}
