<?php

use App\Http\Controllers\Api\V1\ContactListController;
use App\Http\Controllers\Api\V1\EmailController;
use App\Http\Controllers\Api\V1\SmsController;
use App\Http\Controllers\Api\V1\SubscriberController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\AbTestController;
use App\Http\Controllers\Api\V1\FunnelController;
use App\Http\Controllers\Api\McpController;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| NetSendo Public API v1
| All routes require API key authentication via Bearer token
| Rate limited to 60 requests per minute per API key
|
*/

Route::prefix('v1')->middleware(['api.key', 'throttle:api', \App\Http\Middleware\LogApiRequest::class])->group(function () {

    // Account Info (for plugin connection testing)
    Route::get('account', [\App\Http\Controllers\Api\V1\AccountController::class, 'show'])
        ->name('api.v1.account');

    // Subscribers (CRUD)
    Route::post('subscribers/batch', [SubscriberController::class, 'batch'])
        ->name('api.v1.subscribers.batch');
    Route::get('subscribers/by-email/{email}', [SubscriberController::class, 'findByEmail'])
        ->name('api.v1.subscribers.by-email');
    Route::post('subscribers/{subscriber}/tags', [SubscriberController::class, 'syncTags'])
        ->name('api.v1.subscribers.sync-tags');
    Route::apiResource('subscribers', SubscriberController::class)
        ->names([
            'index' => 'api.v1.subscribers.index',
            'store' => 'api.v1.subscribers.store',
            'show' => 'api.v1.subscribers.show',
            'update' => 'api.v1.subscribers.update',
            'destroy' => 'api.v1.subscribers.destroy',
        ]);

    // Contact Lists (read-only)
    Route::get('lists', [ContactListController::class, 'index'])
        ->name('api.v1.lists.index');
    Route::get('lists/{list}', [ContactListController::class, 'show'])
        ->name('api.v1.lists.show');
    Route::get('lists/{list}/subscribers', [ContactListController::class, 'subscribers'])
        ->name('api.v1.lists.subscribers');

    // Tags (read-only)
    Route::get('tags', [TagController::class, 'index'])
        ->name('api.v1.tags.index');
    Route::get('tags/{tag}', [TagController::class, 'show'])
        ->name('api.v1.tags.show');

    // Export
    Route::post('lists/{list}/export', [ExportController::class, 'export'])
        ->name('api.v1.lists.export');

    // Custom Fields
    Route::get('custom-fields', [\App\Http\Controllers\Api\V1\CustomFieldController::class, 'index'])
        ->name('api.v1.custom-fields.index');
    Route::get('custom-fields/placeholders', [\App\Http\Controllers\Api\V1\CustomFieldController::class, 'placeholders'])
        ->name('api.v1.custom-fields.placeholders');
    Route::get('custom-fields/{id}', [\App\Http\Controllers\Api\V1\CustomFieldController::class, 'show'])
        ->name('api.v1.custom-fields.show');

    // Email Operations
    Route::post('email/send', [EmailController::class, 'send'])
        ->name('api.v1.email.send');
    Route::post('email/batch', [EmailController::class, 'batch'])
        ->name('api.v1.email.batch');
    Route::get('email/status/{id}', [EmailController::class, 'status'])
        ->name('api.v1.email.status');
    Route::get('email/mailboxes', [EmailController::class, 'mailboxes'])
        ->name('api.v1.email.mailboxes');

    // SMS Operations
    Route::post('sms/send', [SmsController::class, 'send'])
        ->name('api.v1.sms.send');
    Route::post('sms/batch', [SmsController::class, 'batch'])
        ->name('api.v1.sms.batch');
    Route::get('sms/status/{id}', [SmsController::class, 'status'])
        ->name('api.v1.sms.status');
    Route::get('sms/providers', [SmsController::class, 'providers'])
        ->name('api.v1.sms.providers');

    // Campaigns (Messages)
    Route::post('messages/{message}/lists', [MessageController::class, 'setLists'])
        ->name('api.v1.messages.lists');
    Route::post('messages/{message}/exclusions', [MessageController::class, 'setExclusions'])
        ->name('api.v1.messages.exclusions');
    Route::post('messages/{message}/schedule', [MessageController::class, 'schedule'])
        ->name('api.v1.messages.schedule');
    Route::post('messages/{message}/send', [MessageController::class, 'send'])
        ->name('api.v1.messages.send');
    Route::get('messages/{message}/stats', [MessageController::class, 'stats'])
        ->name('api.v1.messages.stats');
    Route::apiResource('messages', MessageController::class)
        ->names([
            'index' => 'api.v1.messages.index',
            'store' => 'api.v1.messages.store',
            'show' => 'api.v1.messages.show',
            'update' => 'api.v1.messages.update',
            'destroy' => 'api.v1.messages.destroy',
        ]);

    // Campaigns (alias for messages - backward compatibility with external MCP clients)
    // External applications may use /campaigns instead of /messages
    Route::post('campaigns/{campaign}/lists', [MessageController::class, 'setLists'])
        ->name('api.v1.campaigns.lists');
    Route::post('campaigns/{campaign}/exclusions', [MessageController::class, 'setExclusions'])
        ->name('api.v1.campaigns.exclusions');
    Route::post('campaigns/{campaign}/schedule', [MessageController::class, 'schedule'])
        ->name('api.v1.campaigns.schedule');
    Route::post('campaigns/{campaign}/send', [MessageController::class, 'send'])
        ->name('api.v1.campaigns.send');
    Route::get('campaigns/{campaign}/stats', [MessageController::class, 'stats'])
        ->name('api.v1.campaigns.stats');
    Route::apiResource('campaigns', MessageController::class)
        ->names([
            'index' => 'api.v1.campaigns.index',
            'store' => 'api.v1.campaigns.store',
            'show' => 'api.v1.campaigns.show',
            'update' => 'api.v1.campaigns.update',
            'destroy' => 'api.v1.campaigns.destroy',
        ]);

    // A/B Tests
    Route::post('ab-tests/{ab_test}/variants', [AbTestController::class, 'addVariant'])
        ->name('api.v1.ab-tests.variants');
    Route::post('ab-tests/{ab_test}/start', [AbTestController::class, 'start'])
        ->name('api.v1.ab-tests.start');
    Route::post('ab-tests/{ab_test}/end', [AbTestController::class, 'end'])
        ->name('api.v1.ab-tests.end');
    Route::get('ab-tests/{ab_test}/results', [AbTestController::class, 'results'])
        ->name('api.v1.ab-tests.results');
    Route::apiResource('ab-tests', AbTestController::class)
        ->names([
            'index' => 'api.v1.ab-tests.index',
            'store' => 'api.v1.ab-tests.store',
            'show' => 'api.v1.ab-tests.show',
            'update' => 'api.v1.ab-tests.update',
            'destroy' => 'api.v1.ab-tests.destroy',
        ]);

    // Funnels (Automation Sequences)
    Route::post('funnels/{funnel}/steps', [FunnelController::class, 'addStep'])
        ->name('api.v1.funnels.steps');
    Route::post('funnels/{funnel}/activate', [FunnelController::class, 'activate'])
        ->name('api.v1.funnels.activate');
    Route::post('funnels/{funnel}/pause', [FunnelController::class, 'pause'])
        ->name('api.v1.funnels.pause');
    Route::get('funnels/{funnel}/stats', [FunnelController::class, 'stats'])
        ->name('api.v1.funnels.stats');
    Route::apiResource('funnels', FunnelController::class)
        ->names([
            'index' => 'api.v1.funnels.index',
            'store' => 'api.v1.funnels.store',
            'show' => 'api.v1.funnels.show',
            'update' => 'api.v1.funnels.update',
            'destroy' => 'api.v1.funnels.destroy',
        ]);

    Route::get('webhooks/events', [\App\Http\Controllers\Api\V1\WebhookController::class, 'availableEvents'])
        ->name('api.v1.webhooks.events');
    Route::post('webhooks/{webhook}/test', [\App\Http\Controllers\Api\V1\WebhookController::class, 'test'])
        ->name('api.v1.webhooks.test');
    Route::post('webhooks/{webhook}/regenerate-secret', [\App\Http\Controllers\Api\V1\WebhookController::class, 'regenerateSecret'])
        ->name('api.v1.webhooks.regenerate-secret');
    Route::apiResource('webhooks', \App\Http\Controllers\Api\V1\WebhookController::class)
        ->names([
            'index' => 'api.v1.webhooks.index',
            'store' => 'api.v1.webhooks.store',
            'show' => 'api.v1.webhooks.show',
            'update' => 'api.v1.webhooks.update',
            'destroy' => 'api.v1.webhooks.destroy',
        ]);

    // External Pages (read-only, for integrations like WooCommerce)
    Route::get('external-pages', [\App\Http\Controllers\Api\V1\ExternalPageController::class, 'index'])
        ->name('api.v1.external-pages.index');

    // Plugin Version Management
    Route::get('plugin/check-version', [\App\Http\Controllers\Api\V1\PluginVersionController::class, 'check'])
        ->name('api.v1.plugin.check-version');
    Route::post('plugin/heartbeat', [\App\Http\Controllers\Api\V1\PluginVersionController::class, 'heartbeat'])
        ->name('api.v1.plugin.heartbeat');
    Route::get('plugin/connections', [\App\Http\Controllers\Api\V1\PluginVersionController::class, 'connections'])
        ->name('api.v1.plugin.connections');
    Route::delete('plugin/connections/{id}', [\App\Http\Controllers\Api\V1\PluginVersionController::class, 'destroy'])
        ->name('api.v1.plugin.connections.destroy');
    Route::post('plugin/connections/{id}/disconnect', [\App\Http\Controllers\Api\V1\PluginVersionController::class, 'disconnect'])
        ->name('api.v1.plugin.connections.disconnect');
});

// Public Download Route (Signed)
Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('exports/download', [ExportController::class, 'download'])
        ->name('api.v1.exports.download');
});

// List-level subscription API (uses list-specific API key, not global)
Route::prefix('v1/lists/{list}')->middleware('throttle:api')->group(function () {
    Route::post('subscribe', [\App\Http\Controllers\Api\ListSubscriptionController::class, 'subscribe'])
        ->name('api.v1.lists.subscribe');
    Route::post('unsubscribe', [\App\Http\Controllers\Api\ListSubscriptionController::class, 'unsubscribe'])
        ->name('api.v1.lists.unsubscribe');
});

// MCP Connection Test (for AI assistants and external tools)
Route::get('mcp/test', [McpController::class, 'test'])
    ->middleware(['api.key', 'throttle:api'])
    ->name('api.mcp.test');

// ============================================================================
// External Webhook Endpoints (Public, No Auth)
// ============================================================================

// Calendly Webhook (for receiving booking notifications)
Route::post('webhooks/calendly', [\App\Http\Controllers\Webhooks\CalendlyController::class, 'handle'])
    ->name('api.webhooks.calendly');

// AutoTag Pro - Purchase Webhook (for e-commerce integrations)
// Requires API key authentication for security
Route::post('webhooks/purchase', \App\Http\Controllers\Api\PurchaseWebhookController::class)
    ->middleware(['api.key', 'throttle:api'])
    ->name('api.webhooks.purchase');

// ============================================================================
// Telegram Bot Webhook (Public, No Auth - verified by Telegram)
// ============================================================================
Route::post('telegram/webhook', [\App\Http\Controllers\TelegramController::class, 'webhook'])
    ->name('api.telegram.webhook');

// ============================================================================
// NetSendo Brain API (Authenticated)
// ============================================================================
Route::prefix('v1/brain')->middleware(['api.key', 'throttle:api'])->group(function () {

    // Chat
    Route::post('chat', [\App\Http\Controllers\BrainController::class, 'chat'])
        ->name('api.v1.brain.chat');

    // Conversations
    Route::get('conversations', [\App\Http\Controllers\BrainController::class, 'conversations'])
        ->name('api.v1.brain.conversations');
    Route::get('conversations/{id}', [\App\Http\Controllers\BrainController::class, 'conversation'])
        ->name('api.v1.brain.conversations.show');

    // Knowledge Base
    Route::get('knowledge', [\App\Http\Controllers\BrainController::class, 'knowledge'])
        ->name('api.v1.brain.knowledge.index');
    Route::post('knowledge', [\App\Http\Controllers\BrainController::class, 'storeKnowledge'])
        ->name('api.v1.brain.knowledge.store');
    Route::put('knowledge/{id}', [\App\Http\Controllers\BrainController::class, 'updateKnowledge'])
        ->name('api.v1.brain.knowledge.update');
    Route::delete('knowledge/{id}', [\App\Http\Controllers\BrainController::class, 'deleteKnowledge'])
        ->name('api.v1.brain.knowledge.destroy');

    // Action Plans
    Route::get('plans', [\App\Http\Controllers\BrainController::class, 'plans'])
        ->name('api.v1.brain.plans.index');
    Route::get('plans/{id}', [\App\Http\Controllers\BrainController::class, 'plan'])
        ->name('api.v1.brain.plans.show');
    Route::post('plans/{id}/approve', [\App\Http\Controllers\BrainController::class, 'approvePlan'])
        ->name('api.v1.brain.plans.approve');

    // Settings
    Route::get('settings', [\App\Http\Controllers\BrainController::class, 'settings'])
        ->name('api.v1.brain.settings');
    Route::put('settings', [\App\Http\Controllers\BrainController::class, 'updateSettings'])
        ->name('api.v1.brain.settings.update');

    // Telegram Integration
    Route::post('telegram/link-code', [\App\Http\Controllers\BrainController::class, 'generateTelegramLinkCode'])
        ->name('api.v1.brain.telegram.link-code');
    Route::post('telegram/disconnect', [\App\Http\Controllers\BrainController::class, 'disconnectTelegram'])
        ->name('api.v1.brain.telegram.disconnect');
    Route::post('telegram/test', [\App\Http\Controllers\BrainController::class, 'testTelegramBot'])
        ->name('api.v1.brain.telegram.test');
    Route::post('telegram/set-webhook', [\App\Http\Controllers\TelegramController::class, 'setWebhook'])
        ->name('api.v1.brain.telegram.set-webhook');
});

