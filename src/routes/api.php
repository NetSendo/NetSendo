<?php

use App\Http\Controllers\Api\V1\ContactListController;
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
});

// Public Download Route (Signed)
Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::get('exports/download', [ExportController::class, 'download'])
        ->name('api.v1.exports.download');
});
