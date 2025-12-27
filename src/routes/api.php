<?php

use App\Http\Controllers\Api\V1\ContactListController;
use App\Http\Controllers\Api\V1\EmailController;
use App\Http\Controllers\Api\V1\SmsController;
use App\Http\Controllers\Api\V1\SubscriberController;
use App\Http\Controllers\Api\V1\TagController;
use App\Http\Controllers\Api\V1\ExportController;
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

Route::prefix('v1')->middleware(['api.key', 'throttle:api'])->group(function () {

    // Subscribers (CRUD)
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

    // Webhooks (Triggers)
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
