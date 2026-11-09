<?php

namespace Tests\Feature\Api;

use App\Models\AiBrainSettings;
use App\Models\ApiKey;
use App\Models\ContactList;
use App\Models\KnowledgeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Concerns\ActivatesGold;
use Tests\TestCase;

/**
 * The Brain API accepted any valid key whatever its scopes: a key restricted to
 * subscribers:read could read the settings — with the Telegram bot token and the research
 * keys decrypted in the JSON — and change settings, knowledge and plans. Brain routes now
 * need brain:read / brain:write, and the stored secrets are never serialized.
 */
class BrainApiKeyScopeTest extends TestCase
{
    use ActivatesGold;
    use RefreshDatabase;

    private const TELEGRAM = '123456789:AAE-telegram-secret-wxyz';

    private const PERPLEXITY = 'pplx-perplexity-secret-7788';

    private const SERPAPI = 'serpapi-secret-value-5566';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        AiBrainSettings::getForUser($this->user->id)->update([
            'telegram_bot_token' => self::TELEGRAM,
            'perplexity_api_key' => self::PERPLEXITY,
            'serpapi_api_key' => self::SERPAPI,
            'telegram_link_code' => 'LINK-CODE-42',
            'preferred_language' => 'pl',
        ]);
    }

    private function api(array $permissions)
    {
        $key = ApiKey::generate($this->user->id, 'Integration', $permissions)['key'];

        return $this->withHeaders(['Authorization' => 'Bearer ' . $key]);
    }

    private function assertNoSecrets(string $body): void
    {
        foreach ([self::TELEGRAM, self::PERPLEXITY, self::SERPAPI, 'LINK-CODE-42'] as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }
    }

    public function test_a_key_without_a_brain_scope_is_refused_on_every_brain_route(): void
    {
        $api = $this->api(['subscribers:read']);
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'api.v1.brain.'));

        $this->assertGreaterThanOrEqual(15, $routes->count());

        foreach ($routes as $route) {
            $method = $route->methods()[0];
            $uri = '/' . str_replace('{id}', '1', $route->uri());

            $response = $api->json($method, $uri, ['preferred_language' => 'fr']);

            $this->assertSame(403, $response->status(), "{$method} {$uri} answered {$response->status()}");
            $this->assertNoSecrets($response->getContent());
        }

        $this->assertSame('pl', AiBrainSettings::getForUser($this->user->id)->preferred_language);
    }

    public function test_the_same_key_still_cannot_write_subscribers(): void
    {
        $list = ContactList::create(['user_id' => $this->user->id, 'name' => 'Readers', 'type' => 'email', 'is_public' => true]);

        $this->api(['subscribers:read'])
            ->postJson('/api/v1/subscribers', ['email' => 'new@example.com', 'contact_list_id' => $list->id])
            ->assertStatus(403);
    }

    public function test_a_read_key_reads_the_settings_without_the_secrets_and_cannot_change_brain(): void
    {
        $api = $this->api(['brain:read']);

        $response = $api->getJson('/api/v1/brain/settings')
            ->assertOk()
            ->assertJsonPath('settings.preferred_language', 'pl')
            ->assertJsonMissingPath('settings.telegram_bot_token')
            ->assertJsonMissingPath('settings.perplexity_api_key')
            ->assertJsonMissingPath('settings.serpapi_api_key')
            ->assertJsonMissingPath('settings.telegram_link_code')
            ->assertJsonPath('settings.secret_hints.telegram_bot_token', 'wxyz')
            ->assertJsonPath('settings.secret_hints.perplexity_api_key', '7788')
            ->assertJsonPath('settings.secret_hints.serpapi_api_key', '5566');
        $this->assertNoSecrets($response->getContent());

        $api->getJson('/api/v1/brain/knowledge')->assertOk();

        $api->putJson('/api/v1/brain/settings', ['preferred_language' => 'fr'])->assertStatus(403);
        $api->postJson('/api/v1/brain/knowledge', ['category' => 'products', 'title' => 'Price', 'content' => '297 EUR'])
            ->assertStatus(403);
        $api->postJson('/api/v1/brain/chat', ['message' => 'Plan a campaign'])->assertStatus(403);

        $this->assertSame('pl', AiBrainSettings::getForUser($this->user->id)->preferred_language);
        $this->assertSame(0, KnowledgeEntry::count());
    }

    public function test_a_write_key_changes_settings_and_gets_no_secrets_back(): void
    {
        $api = $this->api(['brain:write']);

        $response = $api->putJson('/api/v1/brain/settings', ['preferred_language' => 'fr'])
            ->assertOk()
            ->assertJsonPath('preferred_language', 'fr')
            ->assertJsonMissingPath('perplexity_api_key');
        $this->assertNoSecrets($response->getContent());

        // brain:write includes brain:read
        $this->assertNoSecrets($api->getJson('/api/v1/brain/settings')->assertOk()->getContent());

        $this->assertSame('fr', AiBrainSettings::getForUser($this->user->id)->preferred_language);
        // The secrets themselves are untouched
        $this->assertSame(self::PERPLEXITY, AiBrainSettings::getForUser($this->user->id)->perplexity_api_key);
    }

    public function test_the_settings_page_gets_hints_not_secrets(): void
    {
        $response = $this->actingAs($this->user)->get(route('brain.settings'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Brain/Settings')
                ->missing('settings.perplexity_api_key')
                ->missing('settings.telegram_bot_token')
                ->where('settings.secret_hints.perplexity_api_key', '7788')
                ->where('settings.secret_hints.telegram_bot_token', 'wxyz'));

        $this->assertNoSecrets($response->getContent());
    }

    public function test_the_research_test_uses_the_saved_key_when_the_panel_sends_none(): void
    {
        $this->activateGold();
        Http::fake(['api.perplexity.ai/*' => Http::response(['choices' => [['message' => ['content' => 'OK']]]])]);

        $this->actingAs($this->user)
            ->postJson(route('brain.api.research.test'), ['provider' => 'perplexity'])
            ->assertOk()
            ->assertJsonPath('success', true);

        Http::assertSent(fn (HttpRequest $request) => $request->hasHeader('Authorization', 'Bearer ' . self::PERPLEXITY));

        AiBrainSettings::getForUser($this->user->id)->update(['serpapi_api_key' => null]);
        $this->actingAs($this->user)
            ->postJson(route('brain.api.research.test'), ['provider' => 'serpapi'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_the_migration_keeps_brain_only_for_full_access_keys(): void
    {
        $before = array_values(array_diff(ApiKey::PERMISSIONS, ['brain:read', 'brain:write']));
        $full = ApiKey::generate($this->user->id, 'Full', $before)['model'];
        $narrow = ApiKey::generate($this->user->id, 'Reports', ['subscribers:read'])['model'];
        $writer = ApiKey::generate($this->user->id, 'Shop', ['subscribers:read', 'subscribers:write', 'lists:write'])['model'];

        $migration = require database_path('migrations/2026_11_09_130000_grant_brain_scopes_to_full_access_api_keys.php');
        $migration->up();

        $this->assertTrue($full->fresh()->hasPermission('brain:write'));
        $this->assertTrue($full->fresh()->hasPermission('brain:read'));
        $this->assertFalse($narrow->fresh()->hasPermission('brain:read'));
        $this->assertFalse($writer->fresh()->hasPermission('brain:read'));

        $migration->down();
        $this->assertFalse($full->fresh()->hasPermission('brain:read'));
        $this->assertSame($before, $full->fresh()->permissions);
    }

    public function test_the_queued_list_export_needs_subscribers_read(): void
    {
        Queue::fake();
        $list = ContactList::create(['user_id' => $this->user->id, 'name' => 'Readers', 'type' => 'email', 'is_public' => true]);

        $this->api(['sms:read'])->postJson("/api/v1/lists/{$list->id}/export")->assertStatus(403);
        Queue::assertNothingPushed();

        $this->api(['subscribers:read'])->postJson("/api/v1/lists/{$list->id}/export")->assertStatus(202);
    }
}
