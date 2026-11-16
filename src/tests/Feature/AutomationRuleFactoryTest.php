<?php

namespace Tests\Feature;

use App\Models\AutomationRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationRuleFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_automation_rule_can_be_created_and_persisted(): void
    {
        $rule = AutomationRule::factory()->create();

        $this->assertNotNull($rule);
        $this->assertModelExists($rule);
        $this->assertDatabaseHas('automation_rules', [
            'id' => $rule->id,
            'user_id' => $rule->user_id,
            'name' => $rule->name,
            'trigger_event' => 'subscriber_signup',
            'is_active' => true,
        ]);
        $this->assertTrue($rule->is_active);
    }

    public function test_automation_rule_user_relationship_loads_correctly(): void
    {
        $rule = AutomationRule::factory()->create();

        $this->assertInstanceOf(User::class, $rule->user);
        $this->assertSame($rule->user_id, $rule->user->id);
    }

    public function test_default_trigger_and_actions_structure(): void
    {
        $rule = AutomationRule::factory()->create();

        $this->assertSame('subscriber_signup', $rule->trigger_event);
        $this->assertIsArray($rule->actions);
        $this->assertNotEmpty($rule->actions);

        foreach ($rule->actions as $action) {
            $this->assertArrayHasKey('type', $action);
            $this->assertArrayHasKey('config', $action);
            $this->assertArrayHasKey($action['type'], AutomationRule::ACTION_TYPES);
        }
    }

    public function test_each_trigger_state_produces_a_valid_trigger_event(): void
    {
        $states = [
            'subscriberSignup' => 'subscriber_signup',
            'tagAdded' => 'tag_added',
            'tagRemoved' => 'tag_removed',
            'emailOpened' => 'email_opened',
            'emailClicked' => 'email_clicked',
            'fieldUpdated' => 'field_updated',
        ];

        foreach ($states as $method => $expectedEvent) {
            /** @var AutomationRule $rule */
            $rule = AutomationRule::factory()->{$method}()->create();

            $this->assertSame($expectedEvent, $rule->trigger_event);
            $this->assertArrayHasKey($rule->trigger_event, AutomationRule::TRIGGER_EVENTS);
            $this->assertDatabaseHas('automation_rules', [
                'id' => $rule->id,
                'trigger_event' => $expectedEvent,
            ]);
        }
    }
}
