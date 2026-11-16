<?php

namespace Tests\Feature;

use App\Jobs\ExecuteBrainPlanJob;
use App\Models\AiActionPlan;
use App\Models\AiBrainSettings;
use App\Models\AiPendingApproval;
use App\Models\User;
use App\Services\Brain\Telegram\TelegramBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Concerns\ActivatesGold;
use Tests\TestCase;

/**
 * The public Telegram webhook took any JSON as an update from Telegram,
 * so anyone could act as a linked chat, and a plan button decided any account's plan by id.
 * Updates now need the secret token NetSendo gives Telegram in setWebhook, a linked chat must
 * come through the bot of its own account, and a button decides only that account's plans.
 */
class TelegramWebhookSecurityTest extends TestCase
{
    use RefreshDatabase;
    use ActivatesGold;

    private const SHARED_BOT = '111:shared-bot-token';

    private const OWN_BOT = '222:own-bot-token';

    private User $alice;

    private User $bob;

    /** @var list<Request> Requests sent to the fake Telegram API. */
    private array $sent = [];

    /** What the fake getWebhookInfo answers, by bot token. */
    private array $webhookUrls = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->activateGold();
        config(['services.telegram.bot_token' => self::SHARED_BOT, 'app.url' => 'https://mail.example.com']);

        Http::fake(function (Request $request) {
            $this->sent[] = $request;

            if (str_contains($request->url(), '/getWebhookInfo')) {
                $token = str_contains($request->url(), self::OWN_BOT) ? self::OWN_BOT : self::SHARED_BOT;

                return Http::response(['ok' => true, 'result' => ['url' => $this->webhookUrls[$token] ?? '']]);
            }

            return Http::response(['ok' => true, 'result' => true]);
        });

        // Two accounts on the shared bot, each with a linked chat
        $this->alice = User::factory()->create();
        $this->bob = User::factory()->create();
        $this->link($this->alice, '1001');
        $this->link($this->bob, '2002');
    }

    private function link(User $user, string $chatId): void
    {
        AiBrainSettings::getForUser($user->id)->update([
            'telegram_chat_id' => $chatId,
            'telegram_linked_at' => now(),
            'preferred_language' => 'en',
        ]);
    }

    /** @return array{0: AiActionPlan, 1: AiPendingApproval} */
    private function planAwaitingApproval(User $user): array
    {
        $plan = AiActionPlan::create([
            'user_id' => $user->id, 'agent_type' => 'campaign', 'intent' => 'Spring sale', 'title' => 'Spring sale',
            'plan_data' => ['steps' => []], 'work_mode' => 'semi_auto', 'status' => 'pending_approval', 'total_steps' => 0,
        ]);
        $approval = AiPendingApproval::create([
            'ai_action_plan_id' => $plan->id, 'user_id' => $user->id, 'channel' => 'telegram',
            'status' => 'pending', 'summary' => 'Spring sale', 'expires_at' => now()->addDay(),
        ]);

        return [$plan, $approval];
    }

    private function buttonPress(string $chatId, string $data): array
    {
        return ['update_id' => 1, 'callback_query' => ['id' => 'cb-1', 'message' => ['message_id' => 5, 'chat' => ['id' => (int) $chatId]], 'data' => $data]];
    }

    private function message(string $chatId, string $text): array
    {
        return ['update_id' => 1, 'message' => ['message_id' => 5, 'chat' => ['id' => (int) $chatId], 'from' => ['username' => 'someone'], 'text' => $text]];
    }

    private function webhook(array $update, ?string $botToken)
    {
        $headers = $botToken ? [TelegramBotService::SECRET_HEADER => TelegramBotService::webhookSecret($botToken)] : [];

        return $this->postJson('/api/telegram/webhook', $update, $headers);
    }

    /** Texts the bot sent to a chat. */
    private function textsTo(string $chatId): array
    {
        return collect($this->sent)
            ->filter(fn (Request $r) => str_ends_with($r->url(), '/sendMessage') && (string) ($r['chat_id'] ?? '') === $chatId)
            ->map(fn (Request $r) => $r['text'])
            ->values()->all();
    }

    // --- Forged updates ---

    public function test_a_forged_callback_without_the_secret_is_refused_and_changes_nothing(): void
    {
        [$plan, $approval] = $this->planAwaitingApproval($this->bob);

        // The report: Alice's own chat, Bob's approval id, no proof it came from Telegram
        $this->webhook($this->buttonPress('1001', "reject:{$approval->id}"), null)->assertForbidden();
        $this->webhook($this->buttonPress('2002', "reject:{$approval->id}"), null)->assertForbidden();

        $this->assertSame('pending', $approval->fresh()->status);
        $this->assertSame('pending_approval', $plan->fresh()->status);
        $this->assertSame([], $this->sent, 'nothing is sent to Telegram for a forged update');
    }

    public function test_a_forged_message_or_command_with_a_wrong_secret_is_refused(): void
    {
        $headers = [TelegramBotService::SECRET_HEADER => str_repeat('a', 64)];

        $this->postJson('/api/telegram/webhook', $this->message('2002', '/mode autonomous'), $headers)->assertForbidden();
        $this->postJson('/api/telegram/webhook', $this->message('2002', '/disconnect'), $headers)->assertForbidden();

        $settings = AiBrainSettings::getForUser($this->bob->id);
        $this->assertSame('semi_auto', $settings->work_mode);
        $this->assertSame('2002', $settings->telegram_chat_id);
    }

    public function test_the_secret_of_one_accounts_own_bot_does_not_speak_for_another_accounts_chat(): void
    {
        // Alice runs her own bot: she can learn its secret by pointing it at a server of hers
        AiBrainSettings::getForUser($this->alice->id)->update(['telegram_bot_token' => self::OWN_BOT]);
        [$plan, $approval] = $this->planAwaitingApproval($this->bob);

        $this->webhook($this->message('2002', '/mode autonomous'), self::OWN_BOT)->assertForbidden();
        $this->webhook($this->buttonPress('2002', "approve:{$approval->id}"), self::OWN_BOT)->assertForbidden();

        $this->assertSame('semi_auto', AiBrainSettings::getForUser($this->bob->id)->work_mode);
        $this->assertSame('pending', $approval->fresh()->status);

        // Her own chat through her own bot still works
        $this->webhook($this->message('1001', '/mode autonomous'), self::OWN_BOT)->assertOk();
        $this->assertSame('autonomous', AiBrainSettings::getForUser($this->alice->id)->work_mode);
    }

    public function test_a_plan_button_decides_only_a_plan_of_the_chats_own_account(): void
    {
        [$plan, $approval] = $this->planAwaitingApproval($this->bob);

        // A real update from Alice's chat carrying Bob's approval id
        $this->webhook($this->buttonPress('1001', "reject:{$approval->id}"), self::SHARED_BOT)->assertOk();
        $this->webhook($this->buttonPress('1001', "approve:{$approval->id}"), self::SHARED_BOT)->assertOk();

        $this->assertSame('pending', $approval->fresh()->status);
        $this->assertSame('pending_approval', $plan->fresh()->status);
        $this->assertSame(['⚠️ ' . __('brain.plan_not_pending', [], 'en'), '⚠️ ' . __('brain.plan_not_pending', [], 'en')], $this->textsTo('1001'));
        $this->assertSame([], $this->textsTo('2002'));
    }

    // --- The genuine flow ---

    public function test_the_owner_rejects_their_plan_from_telegram(): void
    {
        [$plan, $approval] = $this->planAwaitingApproval($this->bob);

        $this->webhook($this->buttonPress('2002', "reject:{$approval->id}"), self::SHARED_BOT)->assertOk();

        $this->assertSame('rejected', $approval->fresh()->status);
        $this->assertSame('Rejected via Telegram', $approval->fresh()->rejection_reason);
        $this->assertSame('cancelled', $plan->fresh()->status);
        $this->assertSame(['❌ Plan rejected.'], $this->textsTo('2002'));
    }

    public function test_the_owner_approves_their_plan_from_telegram_once(): void
    {
        Queue::fake();
        [$plan, $approval] = $this->planAwaitingApproval($this->bob);

        $this->webhook($this->buttonPress('2002', "approve:{$approval->id}"), self::SHARED_BOT)->assertOk();

        $this->assertSame('approved', $approval->fresh()->status);
        $this->assertSame('approved', $plan->fresh()->status);
        Queue::assertPushed(ExecuteBrainPlanJob::class, 1);

        // Pressing the button again does not queue the plan a second time
        $this->webhook($this->buttonPress('2002', "approve:{$approval->id}"), self::SHARED_BOT)->assertOk();
        Queue::assertPushed(ExecuteBrainPlanJob::class, 1);
        $this->assertSame('⚠️ ' . __('brain.plan_not_pending', [], 'en'), last($this->textsTo('2002')));
    }

    public function test_a_chat_not_linked_yet_connects_through_any_bot_configured_here(): void
    {
        AiBrainSettings::getForUser($this->alice->id)->update(['telegram_bot_token' => self::OWN_BOT]);
        $carol = User::factory()->create();
        $code = app(\App\Services\Brain\Telegram\TelegramAuthService::class)->generateLinkCode($carol);

        $this->webhook($this->message('3003', "/connect {$code}"), null)->assertForbidden();
        $this->assertNull(AiBrainSettings::getForUser($carol->id)->telegram_chat_id);

        $this->webhook($this->message('3003', "/connect {$code}"), self::OWN_BOT)->assertOk();
        $this->assertSame('3003', AiBrainSettings::getForUser($carol->id)->telegram_chat_id);

        $this->webhook($this->message('4004', '/start'), self::SHARED_BOT)->assertOk();
    }

    // --- Registering the webhook ---

    public function test_the_webhook_is_registered_with_the_secret_and_the_shared_bot_always_points_here(): void
    {
        // Bob is on the shared bot: the address he sends is not used
        $this->actingAs($this->bob)
            ->postJson(route('brain.api.telegram.set-webhook'), ['url' => 'https://evil.example/collect'])
            ->assertOk()->assertJsonPath('ok', true);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.telegram.org/bot' . self::SHARED_BOT . '/setWebhook'
            && $r['url'] === 'https://mail.example.com/api/telegram/webhook'
            && $r['secret_token'] === TelegramBotService::webhookSecret(self::SHARED_BOT)
            && $r['allowed_updates'] === ['message', 'callback_query']);
        Http::assertNotSent(fn (Request $r) => ($r['url'] ?? null) === 'https://evil.example/collect');

        // Alice's own bot goes where she says, with its own secret
        AiBrainSettings::getForUser($this->alice->id)->update(['telegram_bot_token' => self::OWN_BOT]);
        $this->actingAs($this->alice)
            ->postJson(route('brain.api.telegram.set-webhook'), ['url' => 'https://alice.example/api/telegram/webhook'])
            ->assertOk();

        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.telegram.org/bot' . self::OWN_BOT . '/setWebhook'
            && $r['url'] === 'https://alice.example/api/telegram/webhook'
            && $r['secret_token'] === TelegramBotService::webhookSecret(self::OWN_BOT));

        $this->assertNotSame(TelegramBotService::webhookSecret(self::SHARED_BOT), TelegramBotService::webhookSecret(self::OWN_BOT));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{1,256}$/', TelegramBotService::webhookSecret(self::OWN_BOT), 'characters Telegram allows');
    }

    public function test_saving_a_bot_token_registers_its_webhook_with_the_secret(): void
    {
        $this->actingAs($this->alice)
            ->putJson(route('brain.api.settings.update'), ['telegram_bot_token' => self::OWN_BOT])
            ->assertOk();

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), self::OWN_BOT . '/setWebhook')
            && $r['url'] === 'https://mail.example.com/api/telegram/webhook'
            && $r['secret_token'] === TelegramBotService::webhookSecret(self::OWN_BOT));
    }

    public function test_webhooks_set_before_the_secret_are_set_again_once(): void
    {
        AiBrainSettings::getForUser($this->alice->id)->update(['telegram_bot_token' => self::OWN_BOT]);
        $this->webhookUrls = [
            self::SHARED_BOT => 'https://mail.example.com/api/telegram/webhook',
            self::OWN_BOT => 'https://somewhere-else.example/api/telegram/webhook',
        ];

        $this->artisan('brain:secure-telegram-webhooks')
            ->expectsOutputToContain('secured: 1, left as they are: 1, failed: 0')
            ->assertSuccessful();

        $registered = collect($this->sent)->filter(fn (Request $r) => str_ends_with($r->url(), '/setWebhook'))->values();
        $this->assertCount(1, $registered, 'a webhook pointing at another server is left alone');
        $this->assertSame('https://mail.example.com/api/telegram/webhook', $registered[0]['url']);
        $this->assertSame(TelegramBotService::webhookSecret(self::SHARED_BOT), $registered[0]['secret_token']);

        // Done once: the next run asks Telegram nothing
        $this->sent = [];
        $this->artisan('brain:secure-telegram-webhooks')->assertSuccessful();
        $this->assertSame([], $this->sent);
    }

    public function test_the_secure_command_is_scheduled(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'brain:secure-telegram-webhooks'));

        $this->assertCount(1, $events);
    }
}
