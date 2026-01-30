<?php

namespace Database\Seeders;

use App\Models\AutomationRule;
use App\Models\User;
use Illuminate\Database\Seeder;

class DefaultAutomationsSeeder extends Seeder
{
    /**
     * Default automation definitions
     */
    protected array $defaultAutomations = [
        [
            'system_key' => 'cold_lead_detection',
            'name' => '❄️ Cold Lead Detection',
            'description' => 'Oznacz nieaktywnych subskrybentów do kampanii reaktywacyjnej.',
            'trigger_event' => 'subscriber_inactive',
            'trigger_config' => [
                'days' => 30,
            ],
            'conditions' => [],
            'condition_logic' => 'all',
            'actions' => [
                [
                    'type' => 'add_tag',
                    'config' => ['tag' => 'inactive'],
                ],
                [
                    'type' => 'add_score',
                    'config' => ['points' => -10],
                ],
            ],
            'is_active' => false,
        ],
        [
            'system_key' => 'engaged_reader',
            'name' => '📧 Engaged Reader',
            'description' => 'Nagradzaj aktywnych czytelników, którzy otwierają emaile.',
            'trigger_event' => 'email_opened',
            'trigger_config' => [],
            'conditions' => [],
            'condition_logic' => 'all',
            'actions' => [
                [
                    'type' => 'add_tag',
                    'config' => ['tag' => 'engaged_reader'],
                ],
                [
                    'type' => 'add_score',
                    'config' => ['points' => 5],
                ],
            ],
            'is_active' => false,
        ],
        [
            'system_key' => 'purchase_behavior',
            'name' => '🛒 Purchase Behavior',
            'description' => 'Taguj klientów po zakończeniu zakupu.',
            'trigger_event' => 'purchase',
            'trigger_config' => [],
            'conditions' => [],
            'condition_logic' => 'all',
            'actions' => [
                [
                    'type' => 'add_tag',
                    'config' => ['tag' => 'customer'],
                ],
                [
                    'type' => 'add_score',
                    'config' => ['points' => 25],
                ],
            ],
            'is_active' => false,
        ],
        [
            'system_key' => 'click_champion',
            'name' => '🎯 Click Champion',
            'description' => 'Identyfikuj subskrybentów aktywnie klikających w linki.',
            'trigger_event' => 'email_clicked',
            'trigger_config' => [],
            'conditions' => [],
            'condition_logic' => 'all',
            'actions' => [
                [
                    'type' => 'add_tag',
                    'config' => ['tag' => 'clicker'],
                ],
                [
                    'type' => 'add_score',
                    'config' => ['points' => 10],
                ],
            ],
            'is_active' => false,
        ],
        [
            'system_key' => 'welcome_sequence',
            'name' => '👋 Welcome Sequence Trigger',
            'description' => 'Oznacz nowych subskrybentów do sekwencji onboardingowej.',
            'trigger_event' => 'subscriber_signup',
            'trigger_config' => [],
            'conditions' => [],
            'condition_logic' => 'all',
            'actions' => [
                [
                    'type' => 'add_tag',
                    'config' => ['tag' => 'new_subscriber'],
                ],
            ],
            'is_active' => false,
        ],
        [
            'system_key' => 'cart_abandonment',
            'name' => '🛒 Cart Abandonment',
            'description' => 'Taguj subskrybentów, którzy porzucili koszyk.',
            'trigger_event' => 'pixel_cart_abandoned',
            'trigger_config' => [],
            'conditions' => [],
            'condition_logic' => 'all',
            'actions' => [
                [
                    'type' => 'add_tag',
                    'config' => ['tag' => 'cart_abandoned'],
                ],
                [
                    'type' => 'add_score',
                    'config' => ['points' => 15],
                ],
            ],
            'is_active' => false,
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            $this->seedForUser($user);
        }
    }

    /**
     * Seed default automations for a specific user.
     */
    public function seedForUser(User $user): void
    {
        foreach ($this->defaultAutomations as $automation) {
            $systemKey = $automation['system_key'];

            // Skip if this system automation already exists for this user
            $exists = AutomationRule::where('user_id', $user->id)
                ->where('system_key', $systemKey)
                ->exists();

            if ($exists) {
                continue;
            }

            AutomationRule::create([
                'user_id' => $user->id,
                'name' => $automation['name'],
                'description' => $automation['description'],
                'trigger_event' => $automation['trigger_event'],
                'trigger_config' => $automation['trigger_config'],
                'conditions' => $automation['conditions'],
                'condition_logic' => $automation['condition_logic'],
                'actions' => $automation['actions'],
                'is_active' => $automation['is_active'],
                'is_system' => true,
                'system_key' => $systemKey,
                'limit_per_subscriber' => true,
                'limit_count' => 1,
                'limit_period' => 'day',
            ]);
        }
    }

    /**
     * Restore default automations for a user (delete and recreate).
     */
    public function restoreForUser(User $user): int
    {
        // Delete existing system automations
        $deleted = AutomationRule::where('user_id', $user->id)
            ->where('is_system', true)
            ->delete();

        // Recreate them
        foreach ($this->defaultAutomations as $automation) {
            AutomationRule::create([
                'user_id' => $user->id,
                'name' => $automation['name'],
                'description' => $automation['description'],
                'trigger_event' => $automation['trigger_event'],
                'trigger_config' => $automation['trigger_config'],
                'conditions' => $automation['conditions'],
                'condition_logic' => $automation['condition_logic'],
                'actions' => $automation['actions'],
                'is_active' => $automation['is_active'],
                'is_system' => true,
                'system_key' => $automation['system_key'],
                'limit_per_subscriber' => true,
                'limit_count' => 1,
                'limit_period' => 'day',
            ]);
        }

        return count($this->defaultAutomations);
    }

    /**
     * Get count of default automations.
     */
    public function getDefaultCount(): int
    {
        return count($this->defaultAutomations);
    }
}
