<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\VersionController;
use App\Http\Controllers\LocaleController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // License routes
    Route::get('/license', [LicenseController::class, 'index'])->name('license.index');
    Route::post('/license/request-silver', [LicenseController::class, 'requestSilverLicense'])->name('license.request-silver');
    Route::post('/license/validate', [LicenseController::class, 'validateLicense'])->name('license.validate');
    Route::post('/license/activate', [LicenseController::class, 'activate'])->name('license.activate');
    
    // Version check routes
    Route::get('/api/version/check', [VersionController::class, 'check'])->name('api.version.check');
    Route::get('/api/version/refresh', [VersionController::class, 'refresh'])->name('api.version.refresh');
    Route::get('/api/version/current', [VersionController::class, 'current'])->name('api.version.current');
    
    // Two-Factor Authentication routes
    Route::get('/profile/2fa', [\App\Http\Controllers\TwoFactorController::class, 'index'])->name('profile.2fa.index');
    Route::get('/profile/2fa/enable', [\App\Http\Controllers\TwoFactorController::class, 'enable'])->name('profile.2fa.enable');
    Route::post('/profile/2fa/confirm', [\App\Http\Controllers\TwoFactorController::class, 'confirm'])->name('profile.2fa.confirm');
    Route::post('/profile/2fa/disable', [\App\Http\Controllers\TwoFactorController::class, 'disable'])->name('profile.2fa.disable');

    // Mailing Lists
    Route::resource('mailing-lists', \App\Http\Controllers\MailingListController::class);
    Route::resource('sms-lists', \App\Http\Controllers\SmsListController::class);

    // Subscribers
    Route::get('subscribers/import', [\App\Http\Controllers\SubscriberController::class, 'importForm'])->name('subscribers.import');
    Route::post('subscribers/import', [\App\Http\Controllers\SubscriberController::class, 'import'])->name('subscribers.import.store');
    Route::resource('subscribers', \App\Http\Controllers\SubscriberController::class);
    
    // Subscriber Tags
    Route::post('subscribers/{subscriber}/tags', [\App\Http\Controllers\SubscriberController::class, 'syncTags'])->name('subscribers.tags.sync');
    Route::post('subscribers/{subscriber}/tags/{tag}', [\App\Http\Controllers\SubscriberController::class, 'attachTag'])->name('subscribers.tags.attach');
    Route::delete('subscribers/{subscriber}/tags/{tag}', [\App\Http\Controllers\SubscriberController::class, 'detachTag'])->name('subscribers.tags.detach');
    
    // Groups & Tags
    Route::resource('groups', \App\Http\Controllers\ContactListGroupController::class);
    Route::resource('tags', \App\Http\Controllers\TagController::class)->except(['create', 'edit', 'show']);

    // Templates & Messages
    // Inserts routes must be defined before resource to avoid conflict
    Route::prefix('templates/inserts')->name('inserts.')->group(function () {
        Route::get('/', [\App\Http\Controllers\InsertController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\InsertController::class, 'store'])->name('store');
        Route::put('/{template}', [\App\Http\Controllers\InsertController::class, 'update'])->name('update');
        Route::delete('/{template}', [\App\Http\Controllers\InsertController::class, 'destroy'])->name('destroy');
        Route::get('/variables', [\App\Http\Controllers\InsertController::class, 'variables'])->name('variables');
    });
    Route::resource('templates', \App\Http\Controllers\TemplateController::class)->except(['show']);
    Route::get('templates/{template}/preview', [\App\Http\Controllers\TemplateController::class, 'preview'])->name('templates.preview');
    Route::post('templates/{template}/duplicate', [\App\Http\Controllers\TemplateController::class, 'duplicate'])->name('templates.duplicate');
    Route::get('templates/{template}/export', [\App\Http\Controllers\TemplateController::class, 'export'])->name('templates.export');
    Route::post('templates/import', [\App\Http\Controllers\TemplateController::class, 'import'])->name('templates.import');

    // Template Builder API
    Route::prefix('api/templates')->name('api.templates.')->group(function () {
        Route::post('{template}/structure', [\App\Http\Controllers\TemplateBuilderController::class, 'saveStructure'])->name('structure');
        Route::post('compile', [\App\Http\Controllers\TemplateBuilderController::class, 'compile'])->name('compile');
        Route::post('upload-image', [\App\Http\Controllers\TemplateBuilderController::class, 'uploadImage'])->name('upload-image');
        Route::post('{template}/thumbnail', [\App\Http\Controllers\TemplateBuilderController::class, 'generateThumbnail'])->name('thumbnail');
        Route::get('block-defaults', [\App\Http\Controllers\TemplateBuilderController::class, 'getBlockDefaults'])->name('block-defaults');
        Route::post('upload-thumbnail', [\App\Http\Controllers\TemplateBuilderController::class, 'uploadThumbnail'])->name('upload-thumbnail');
    });

    // Template AI API
    Route::prefix('api/templates/ai')->name('api.templates.ai.')->group(function () {
        Route::post('generate-content', [\App\Http\Controllers\TemplateAiController::class, 'generateContent'])->name('content');
        Route::post('generate-section', [\App\Http\Controllers\TemplateAiController::class, 'generateSection'])->name('section');
        Route::post('generate-message-content', [\App\Http\Controllers\TemplateAiController::class, 'generateMessageContent'])->name('message-content');
        Route::post('improve-text', [\App\Http\Controllers\TemplateAiController::class, 'improveText'])->name('improve');
        Route::post('generate-subject', [\App\Http\Controllers\TemplateAiController::class, 'generateSubject'])->name('subject');
        Route::post('generate-product', [\App\Http\Controllers\TemplateAiController::class, 'generateProductDescription'])->name('product');
        Route::post('suggest-improvements', [\App\Http\Controllers\TemplateAiController::class, 'suggestImprovements'])->name('suggestions');
        Route::get('check', [\App\Http\Controllers\TemplateAiController::class, 'checkAvailability'])->name('check');
    });

    // Active AI Models API (for model selection in AI assistants)
    Route::get('api/ai/active-models', [\App\Http\Controllers\ActiveAiModelsController::class, 'index'])->name('api.ai.active-models');

    // Template Blocks
    Route::resource('template-blocks', \App\Http\Controllers\TemplateBlockController::class)->except(['create', 'edit', 'show']);
    Route::get('api/template-blocks/defaults', [\App\Http\Controllers\TemplateBlockController::class, 'defaults'])->name('api.template-blocks.defaults');

    Route::get('messages/{message}/stats', [\App\Http\Controllers\MessageController::class, 'stats'])->name('messages.stats');
    Route::post('messages/test', [\App\Http\Controllers\MessageController::class, 'test'])->name('messages.test');
    Route::post('messages/{message}/duplicate', [\App\Http\Controllers\MessageController::class, 'duplicate'])->name('messages.duplicate');
    Route::get('templates/{template}/compiled', [\App\Http\Controllers\TemplateController::class, 'compiled'])->name('templates.compiled');
    Route::resource('messages', \App\Http\Controllers\MessageController::class);
    Route::resource('sms', \App\Http\Controllers\SmsController::class);
    Route::resource('external-pages', \App\Http\Controllers\Automation\ExternalPageController::class);

    // Subscription Forms
    Route::resource('forms', \App\Http\Controllers\SubscriptionFormController::class);
    Route::post('forms/{form}/duplicate', [\App\Http\Controllers\SubscriptionFormController::class, 'duplicate'])->name('forms.duplicate');
    Route::get('forms/{form}/code', [\App\Http\Controllers\SubscriptionFormController::class, 'code'])->name('forms.code');
    Route::get('forms/{form}/stats', [\App\Http\Controllers\SubscriptionFormController::class, 'stats'])->name('forms.stats');
    
    // Form Integrations (webhooks)
    Route::prefix('forms/{form}/integrations')->name('forms.integrations.')->group(function () {
        Route::get('/', [\App\Http\Controllers\FormIntegrationController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\FormIntegrationController::class, 'store'])->name('store');
        Route::put('/{integration}', [\App\Http\Controllers\FormIntegrationController::class, 'update'])->name('update');
        Route::delete('/{integration}', [\App\Http\Controllers\FormIntegrationController::class, 'destroy'])->name('destroy');
        Route::post('/{integration}/test', [\App\Http\Controllers\FormIntegrationController::class, 'test'])->name('test');
    });

    // Email Funnels
    Route::resource('funnels', \App\Http\Controllers\FunnelController::class);
    Route::post('funnels/{funnel}/duplicate', [\App\Http\Controllers\FunnelController::class, 'duplicate'])->name('funnels.duplicate');
    Route::get('funnels/{funnel}/stats', [\App\Http\Controllers\FunnelController::class, 'stats'])->name('funnels.stats');
    Route::post('funnels/{funnel}/toggle-status', [\App\Http\Controllers\FunnelController::class, 'toggleStatus'])->name('funnels.toggle-status');
    Route::get('funnels/{funnel}/validate', [\App\Http\Controllers\FunnelController::class, 'validate'])->name('funnels.validate');

    // Automations (Triggers & Rules)
    Route::resource('automations', \App\Http\Controllers\AutomationController::class)->parameters([
        'automations' => 'automation'
    ]);
    Route::post('automations/{automation}/duplicate', [\App\Http\Controllers\AutomationController::class, 'duplicate'])->name('automations.duplicate');
    Route::post('automations/{automation}/toggle-status', [\App\Http\Controllers\AutomationController::class, 'toggleStatus'])->name('automations.toggle-status');
    Route::get('automations/{automation}/logs', [\App\Http\Controllers\AutomationController::class, 'logs'])->name('automations.logs');
    Route::get('api/automations/stats', [\App\Http\Controllers\AutomationController::class, 'stats'])->name('api.automations.stats');

    // AI Integrations
    Route::prefix('settings/ai-integrations')->name('settings.ai-integrations.')->group(function () {
        Route::get('/', [\App\Http\Controllers\AiIntegrationController::class, 'index'])->name('index');
        Route::get('/providers', [\App\Http\Controllers\AiIntegrationController::class, 'providers'])->name('providers');
        Route::post('/', [\App\Http\Controllers\AiIntegrationController::class, 'store'])->name('store');
        Route::put('/{integration}', [\App\Http\Controllers\AiIntegrationController::class, 'update'])->name('update');
        Route::delete('/{integration}', [\App\Http\Controllers\AiIntegrationController::class, 'destroy'])->name('destroy');
        Route::post('/{integration}/test', [\App\Http\Controllers\AiIntegrationController::class, 'testConnection'])->name('test');
        Route::get('/{integration}/models', [\App\Http\Controllers\AiIntegrationController::class, 'fetchModels'])->name('models');
        Route::post('/{integration}/models', [\App\Http\Controllers\AiIntegrationController::class, 'addModel'])->name('models.add');
        Route::delete('/{integration}/models/{model}', [\App\Http\Controllers\AiIntegrationController::class, 'removeModel'])->name('models.remove');
    });

    // Integrations Settings (Google OAuth, etc.)
    Route::prefix('settings/integrations')->name('settings.integrations.')->group(function () {
        Route::get('/', [\App\Http\Controllers\IntegrationSettingsController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\IntegrationSettingsController::class, 'store'])->name('store');
        Route::delete('/{integration}', [\App\Http\Controllers\IntegrationSettingsController::class, 'destroy'])->name('destroy');
        Route::get('/{integration}/verify', [\App\Http\Controllers\IntegrationSettingsController::class, 'verify'])->name('verify');
    });

    // System Messages
    Route::prefix('settings/system-messages')->name('settings.system-messages.')->group(function () {
        Route::get('/', [\App\Http\Controllers\SystemMessageController::class, 'index'])->name('index');
        Route::get('/{system_message}/edit', [\App\Http\Controllers\SystemMessageController::class, 'edit'])->name('edit');
        Route::put('/{system_message}', [\App\Http\Controllers\SystemMessageController::class, 'update'])->name('update');
    });

    // Mailboxes (Email Providers)
    Route::prefix('settings/mailboxes')->name('settings.mailboxes.')->group(function () {
        Route::get('/gmail/connect/{mailbox}', [\App\Http\Controllers\GmailOAuthController::class, 'connect'])->name('gmail.connect');
        Route::get('/gmail/callback', [\App\Http\Controllers\GmailOAuthController::class, 'callback'])->name('gmail.callback');
        Route::post('/gmail/disconnect/{mailbox}', [\App\Http\Controllers\GmailOAuthController::class, 'disconnect'])->name('gmail.disconnect');

        // CRUD routes must be after specific routes to avoid ID collisions
        Route::get('/', [\App\Http\Controllers\MailboxController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\MailboxController::class, 'store'])->name('store');
        Route::put('/{mailbox}', [\App\Http\Controllers\MailboxController::class, 'update'])->name('update');
        Route::delete('/{mailbox}', [\App\Http\Controllers\MailboxController::class, 'destroy'])->name('destroy');
        Route::post('/{mailbox}/test', [\App\Http\Controllers\MailboxController::class, 'test'])->name('test');
        Route::post('/{mailbox}/default', [\App\Http\Controllers\MailboxController::class, 'setDefault'])->name('default');
    });

    // CRON Settings
    Route::prefix('settings/cron')->name('settings.cron.')->group(function () {
        Route::get('/', [\App\Http\Controllers\CronSettingsController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\CronSettingsController::class, 'store'])->name('store');
        Route::get('/stats', [\App\Http\Controllers\CronSettingsController::class, 'stats'])->name('stats');
        Route::get('/logs', [\App\Http\Controllers\CronSettingsController::class, 'logs'])->name('logs');
        Route::get('/status', [\App\Http\Controllers\CronSettingsController::class, 'cronStatus'])->name('status');
        Route::post('/clear-logs', [\App\Http\Controllers\CronSettingsController::class, 'clearLogs'])->name('clear-logs');
        Route::post('/test', [\App\Http\Controllers\CronSettingsController::class, 'testDispatch'])->name('test');
    });

    // Custom Fields (Settings > Zarządzanie Polami)
    Route::prefix('settings/fields')->name('settings.fields.')->group(function () {
        Route::get('/', [\App\Http\Controllers\CustomFieldController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\CustomFieldController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\CustomFieldController::class, 'store'])->name('store');
        Route::get('/{field}/edit', [\App\Http\Controllers\CustomFieldController::class, 'edit'])->name('edit');
        Route::put('/{field}', [\App\Http\Controllers\CustomFieldController::class, 'update'])->name('update');
        Route::delete('/{field}', [\App\Http\Controllers\CustomFieldController::class, 'destroy'])->name('destroy');
        Route::post('/update-order', [\App\Http\Controllers\CustomFieldController::class, 'updateOrder'])->name('update-order');
    });

    // Placeholders API
    Route::get('/api/placeholders', [\App\Http\Controllers\CustomFieldController::class, 'placeholders'])->name('api.placeholders');
    Route::get('/api/lists/{list}/fields', [\App\Http\Controllers\CustomFieldController::class, 'listFields'])->name('api.list-fields');

    // Global Defaults
    Route::get('/defaults', [\App\Http\Controllers\DefaultSettingsController::class, 'index'])->name('defaults.index');
    Route::post('/defaults', [\App\Http\Controllers\DefaultSettingsController::class, 'store'])->name('defaults.store');

    // API Keys Management
    Route::prefix('settings/api-keys')->name('settings.api-keys.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ApiKeyController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\ApiKeyController::class, 'store'])->name('store');
        Route::delete('/{apiKey}', [\App\Http\Controllers\ApiKeyController::class, 'destroy'])->name('destroy');
    });

    // Backup Management
    Route::prefix('settings/backup')->name('settings.backup.')->group(function () {
        Route::get('/', [\App\Http\Controllers\BackupController::class, 'index'])->name('index');
        Route::post('/create', [\App\Http\Controllers\BackupController::class, 'create'])->name('create');
        Route::get('/download/{filename}', [\App\Http\Controllers\BackupController::class, 'download'])->name('download');
        Route::delete('/{filename}', [\App\Http\Controllers\BackupController::class, 'destroy'])->name('destroy');
    });

    // Global Stats (Analytics)
    Route::prefix('settings/stats')->name('settings.stats.')->group(function () {
        Route::get('/', [\App\Http\Controllers\GlobalStatsController::class, 'index'])->name('index');
        Route::get('/monthly/{year}/{month}', [\App\Http\Controllers\GlobalStatsController::class, 'getMonthlyStats'])->name('monthly');
        Route::get('/export/{year}/{month}', [\App\Http\Controllers\GlobalStatsController::class, 'export'])->name('export');
    });

    // Dashboard Stats API
    Route::get('/api/dashboard/stats', [\App\Http\Controllers\GlobalStatsController::class, 'getDashboardStats'])->name('api.dashboard.stats');

    // Activity Logs (Audit Log)
    Route::prefix('settings/activity-logs')->name('settings.activity-logs.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ActivityLogController::class, 'index'])->name('index');
        Route::get('/export', [\App\Http\Controllers\ActivityLogController::class, 'export'])->name('export');
        Route::delete('/cleanup', [\App\Http\Controllers\ActivityLogController::class, 'cleanup'])->name('cleanup');
    });

    // Tracked Links Dashboard
    Route::prefix('settings/tracked-links')->name('settings.tracked-links.')->group(function () {
        Route::get('/', [\App\Http\Controllers\TrackedLinksController::class, 'index'])->name('index');
        Route::get('/show', [\App\Http\Controllers\TrackedLinksController::class, 'show'])->name('show');
        Route::get('/export', [\App\Http\Controllers\TrackedLinksController::class, 'export'])->name('export');
    });

    // Updates/Changelog
    Route::get('/update', [\App\Http\Controllers\UpdatesController::class, 'index'])->name('update.index');
});


// API route for license status (no auth required for setup checks)
Route::get('/api/license/status', [LicenseController::class, 'status'])->name('license.status');

// Webhook for automatic license activation from external system (no auth, public endpoint)
Route::post('/api/license/webhook', [LicenseController::class, 'webhookActivate'])->name('license.webhook');

// Locale switching (works for guests and authenticated users)
Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');


// Tracking Routes
Route::get('/t/open/{message}/{subscriber}/{hash}', [\App\Http\Controllers\TrackingController::class, 'trackOpen'])->name('tracking.open');
Route::get('/t/click/{message}/{subscriber}/{hash}', [\App\Http\Controllers\TrackingController::class, 'trackClick'])->name('tracking.click');
Route::get('/unsubscribe/{subscriber}', [\App\Http\Controllers\SubscriberController::class, 'unsubscribe'])->name('unsubscribe')->middleware('signed');

// External Pages (Public)
Route::get('/p/{externalPage}', [\App\Http\Controllers\Public\ExternalPageHandlerController::class, 'show'])->name('page.show');

// Public Subscription
Route::post('/subscribe/{contactList}', [\App\Http\Controllers\Public\SubscriptionController::class, 'store'])->name('subscribe');

// Public Subscription Forms (no auth)
Route::prefix('subscribe')->name('subscribe.')->group(function () {
    Route::get('/form/{slug}', [\App\Http\Controllers\PublicFormController::class, 'show'])->name('form');
    Route::post('/{slug}', [\App\Http\Controllers\PublicFormController::class, 'submit'])->name('submit');
    Route::get('/js/{slug}', [\App\Http\Controllers\PublicFormController::class, 'javascript'])->name('js');
    Route::get('/success/{slug}', [\App\Http\Controllers\PublicFormController::class, 'success'])->name('success');
    Route::get('/error/{slug}', [\App\Http\Controllers\PublicFormController::class, 'error'])->name('error');
});

// CRON Webhook (public, authenticated via token)
Route::match(['get', 'post'], '/api/cron/webhook', [\App\Http\Controllers\CronSettingsController::class, 'webhookTrigger'])->name('cron.webhook');

// CRON Webhook Settings (requires auth)
Route::middleware(['auth'])->group(function () {
    Route::get('/settings/cron/webhook', [\App\Http\Controllers\CronSettingsController::class, 'webhookSettings'])->name('settings.cron.webhook');
    Route::post('/settings/cron/webhook/generate', [\App\Http\Controllers\CronSettingsController::class, 'generateWebhookToken'])->name('settings.cron.webhook.generate');
});

// Email Bounce Webhooks (public, provider-authenticated)
Route::prefix('webhooks/bounce')->name('webhooks.bounce.')->group(function () {
    Route::post('/sendgrid', [\App\Http\Controllers\Webhooks\BounceController::class, 'sendgrid'])->name('sendgrid');
    Route::post('/postmark', [\App\Http\Controllers\Webhooks\BounceController::class, 'postmark'])->name('postmark');
    Route::post('/mailgun', [\App\Http\Controllers\Webhooks\BounceController::class, 'mailgun'])->name('mailgun');
    Route::post('/generic', [\App\Http\Controllers\Webhooks\BounceController::class, 'generic'])->name('generic');
});

require __DIR__.'/auth.php';


