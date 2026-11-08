<?php

namespace Tests\Feature;

use App\Models\AiBrainSettings;
use App\Models\AiIntegration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Brain's daily token budget: the counter belongs to the day it was started on, so a day
 * that ended over the limit does not keep Brain blocked on the days after.
 */
class BrainTokenLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    private function settings(int $used, ?string $resetDate): AiBrainSettings
    {
        $settings = AiBrainSettings::getForUser($this->user->id);
        $settings->update([
            'daily_token_limit' => 100000,
            'tokens_used_today' => $used,
            'token_reset_date' => $resetDate,
        ]);

        return $settings->fresh();
    }

    public function test_todays_count_over_the_limit_blocks(): void
    {
        $settings = $this->settings(120000, today()->toDateString());

        $this->assertSame(120000, $settings->tokensUsedToday());
        $this->assertTrue($settings->isTokenLimitReached());
    }

    public function test_a_count_left_from_an_earlier_day_no_longer_blocks(): void
    {
        $settings = $this->settings(120000, today()->subDays(96)->toDateString());

        $this->assertSame(0, $settings->tokensUsedToday());
        $this->assertFalse($settings->isTokenLimitReached());
    }

    public function test_a_counter_never_started_counts_nothing(): void
    {
        $settings = $this->settings(5000, null);

        $this->assertSame(0, $settings->tokensUsedToday());
        $this->assertFalse($settings->isTokenLimitReached());
    }

    public function test_charging_on_a_new_day_starts_the_count_again(): void
    {
        $settings = $this->settings(120000, today()->subDay()->toDateString());

        $settings->addTokensUsed(300);
        $settings->refresh();

        $this->assertSame(300, $settings->tokens_used_today);
        $this->assertTrue($settings->token_reset_date->isToday());

        $settings->addTokensUsed(200);
        $this->assertSame(500, $settings->fresh()->tokensUsedToday());
    }

    public function test_the_chat_answers_again_the_day_after_the_limit_was_hit(): void
    {
        AiIntegration::create([
            'provider' => 'openai',
            'name' => 'OpenAI',
            'api_key' => 'sk-test',
            'is_active' => true,
            'is_default' => true,
        ]);
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'Hello from Brain']]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            ]),
        ]);

        $limitMessage = __('brain.token_limit_reached');

        // Over the limit today: blocked, and no AI call is made
        $this->settings(120000, today()->toDateString());
        $blocked = $this->actingAs($this->user)
            ->postJson(route('brain.api.chat'), ['message' => 'What is our refund policy?', 'force_new' => true])
            ->assertOk()
            ->json();
        $this->assertSame($limitMessage, $blocked['message'] ?? null);
        Http::assertNothingSent();

        // The same count, but from yesterday: Brain asks the AI again
        $this->settings(120000, today()->subDay()->toDateString());
        $answer = $this->actingAs($this->user)
            ->postJson(route('brain.api.chat'), ['message' => 'What is our refund policy?', 'force_new' => true])
            ->assertOk()
            ->json();
        $this->assertNotSame($limitMessage, $answer['message'] ?? null);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.openai.com'));
    }
}
