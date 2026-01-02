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
})->middleware(['auth', 'verified', '2fa'])->name('dashboard');

Route::middleware(['auth', '2fa'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // License routes
    Route::get('/license', [LicenseController::class, 'index'])->name('license.index');
    Route::post('/license/request-silver', [LicenseController::class, 'requestSilverLicense'])->name('license.request-silver');
    Route::post('/license/validate', [LicenseController::class, 'validateLicense'])->name('license.validate');
    Route::post('/license/activate', [LicenseController::class, 'activate'])->name('license.activate');
    Route::post('/license/check-status', [LicenseController::class, 'checkLicenseStatus'])->name('license.check-status');

    // Version check routes
    Route::get('/api/version/check', [VersionController::class, 'check'])->name('api.version.check');
    Route::get('/api/version/refresh', [VersionController::class, 'refresh'])->name('api.version.refresh');
    Route::get('/api/version/current', [VersionController::class, 'current'])->name('api.version.current');
    Route::get('/api/version/changelog', [VersionController::class, 'changelog'])->name('api.version.changelog');

    // Two-Factor Authentication routes
    Route::get('/profile/2fa', [\App\Http\Controllers\TwoFactorController::class, 'index'])->name('profile.2fa.index');
    Route::get('/profile/2fa/enable', [\App\Http\Controllers\TwoFactorController::class, 'enable'])->name('profile.2fa.enable');
    Route::post('/profile/2fa/confirm', [\App\Http\Controllers\TwoFactorController::class, 'confirm'])->name('profile.2fa.confirm');
    Route::post('/profile/2fa/disable', [\App\Http\Controllers\TwoFactorController::class, 'disable'])->name('profile.2fa.disable');

    // Mailing Lists
    Route::resource('mailing-lists', \App\Http\Controllers\MailingListController::class);
    Route::post('mailing-lists/{mailing_list}/generate-api-key', [\App\Http\Controllers\MailingListController::class, 'generateApiKey'])->name('mailing-lists.generate-api-key');
    Route::post('mailing-lists/{mailing_list}/test-webhook', [\App\Http\Controllers\MailingListController::class, 'testWebhook'])->name('mailing-lists.test-webhook');
    Route::resource('sms-lists', \App\Http\Controllers\SmsListController::class)->except(['show']);
    Route::post('sms-lists/{sms_list}/generate-api-key', [\App\Http\Controllers\SmsListController::class, 'generateApiKey'])->name('sms-lists.generate-api-key');
    Route::post('sms-lists/{sms_list}/test-webhook', [\App\Http\Controllers\SmsListController::class, 'testWebhook'])->name('sms-lists.test-webhook');


    // Subscribers
    Route::get('subscribers/import', [\App\Http\Controllers\SubscriberController::class, 'importForm'])->name('subscribers.import');
    Route::post('subscribers/import', [\App\Http\Controllers\SubscriberController::class, 'import'])->name('subscribers.import.store');

    // Subscriber Bulk Actions (must be before resource to avoid route conflict)
    Route::post('subscribers/bulk-delete', [\App\Http\Controllers\SubscriberController::class, 'bulkDelete'])->name('subscribers.bulk-delete');
    Route::post('subscribers/bulk-move', [\App\Http\Controllers\SubscriberController::class, 'bulkMove'])->name('subscribers.bulk-move');
    Route::post('subscribers/bulk-status', [\App\Http\Controllers\SubscriberController::class, 'bulkChangeStatus'])->name('subscribers.bulk-status');

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
        Route::post('generate-sms-content', [\App\Http\Controllers\TemplateAiController::class, 'generateSmsContent'])->name('sms-content');
        Route::post('generate-product', [\App\Http\Controllers\TemplateAiController::class, 'generateProductDescription'])->name('product');
        Route::post('suggest-improvements', [\App\Http\Controllers\TemplateAiController::class, 'suggestImprovements'])->name('suggestions');
        Route::get('check', [\App\Http\Controllers\TemplateAiController::class, 'checkAvailability'])->name('check');
    });

    // Active AI Models API (for model selection in AI assistants)
    Route::get('api/ai/active-models', [\App\Http\Controllers\ActiveAiModelsController::class, 'index'])->name('api.ai.active-models');

    // Template Blocks
    Route::resource('template-blocks', \App\Http\Controllers\TemplateBlockController::class)->except(['create', 'edit', 'show']);
    Route::get('api/template-blocks/defaults', [\App\Http\Controllers\TemplateBlockController::class, 'defaults'])->name('api.template-blocks.defaults');

    Route::get('messages/statuses', [\App\Http\Controllers\MessageController::class, 'statuses'])->name('messages.statuses');
    Route::get('messages/{message}/stats', [\App\Http\Controllers\MessageController::class, 'stats'])->name('messages.stats');
    Route::post('messages/test', [\App\Http\Controllers\MessageController::class, 'test'])->name('messages.test');
    Route::post('messages/preview', [\App\Http\Controllers\MessageController::class, 'preview'])->name('messages.preview');
    Route::post('messages/preview-subscribers', [\App\Http\Controllers\MessageController::class, 'previewSubscribers'])->name('messages.preview-subscribers');
    Route::post('messages/{message}/duplicate', [\App\Http\Controllers\MessageController::class, 'duplicate'])->name('messages.duplicate');
    Route::post('messages/{message}/resend', [\App\Http\Controllers\MessageController::class, 'resend'])->name('messages.resend');
    Route::post('messages/{message}/toggle-active', [\App\Http\Controllers\MessageController::class, 'toggleActive'])->name('messages.toggle-active');
    Route::get('messages/{message}/queue-schedule-stats', [\App\Http\Controllers\MessageController::class, 'queueScheduleStats'])->name('messages.queue-schedule-stats');
    Route::post('messages/{message}/send-to-missed', [\App\Http\Controllers\MessageController::class, 'sendToMissedRecipients'])->name('messages.send-to-missed');
    Route::get('templates/{template}/compiled', [\App\Http\Controllers\TemplateController::class, 'compiled'])->name('templates.compiled');
    Route::resource('messages', \App\Http\Controllers\MessageController::class);
    Route::post('sms/{sms}/toggle-active', [\App\Http\Controllers\SmsController::class, 'toggleActive'])->name('sms.toggle-active');
    Route::post('sms/preview', [\App\Http\Controllers\SmsController::class, 'preview'])->name('sms.preview');
    Route::post('sms/preview-subscribers', [\App\Http\Controllers\SmsController::class, 'previewSubscribers'])->name('sms.preview-subscribers');
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

    // AI Campaign Architect
    Route::prefix('campaign-architect')->name('campaign-architect.')->group(function () {
        Route::get('/', [\App\Http\Controllers\CampaignArchitectController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\CampaignArchitectController::class, 'store'])->name('store');
        Route::get('/{plan}', [\App\Http\Controllers\CampaignArchitectController::class, 'show'])->name('show');
        Route::put('/{plan}', [\App\Http\Controllers\CampaignArchitectController::class, 'update'])->name('update');
        Route::delete('/{plan}', [\App\Http\Controllers\CampaignArchitectController::class, 'destroy'])->name('destroy');
        Route::post('/{plan}/generate', [\App\Http\Controllers\CampaignArchitectController::class, 'generateStrategy'])->name('generate');
        Route::post('/{plan}/forecast', [\App\Http\Controllers\CampaignArchitectController::class, 'updateForecast'])->name('forecast');
        Route::post('/{plan}/export', [\App\Http\Controllers\CampaignArchitectController::class, 'export'])->name('export');
    });

    Route::prefix('api/campaign-architect')->name('api.campaign-architect.')->group(function () {
        Route::get('/audience', [\App\Http\Controllers\CampaignArchitectController::class, 'getAudienceData'])->name('audience');
        Route::get('/benchmarks', [\App\Http\Controllers\CampaignArchitectController::class, 'getBenchmarks'])->name('benchmarks');
    });

    // AI Campaign Auditor
    Route::prefix('campaign-auditor')->name('campaign-auditor.')->group(function () {
        Route::get('/', [\App\Http\Controllers\CampaignAuditorController::class, 'index'])->name('index');
        Route::post('/run', [\App\Http\Controllers\CampaignAuditorController::class, 'runAudit'])->name('run');
        Route::get('/{audit}', [\App\Http\Controllers\CampaignAuditorController::class, 'show'])->name('show');
        Route::get('/{audit}/issues', [\App\Http\Controllers\CampaignAuditorController::class, 'issues'])->name('issues');
        Route::post('/issues/{issue}/mark-fixed', [\App\Http\Controllers\CampaignAuditorController::class, 'markFixed'])->name('mark-fixed');

        // Recommendations
        Route::get('/{audit}/recommendations', [\App\Http\Controllers\CampaignAuditorController::class, 'recommendations'])->name('recommendations');
        Route::post('/recommendations/{recommendation}/apply', [\App\Http\Controllers\CampaignAuditorController::class, 'applyRecommendation'])->name('recommendations.apply');
        Route::post('/recommendations/{recommendation}/measure', [\App\Http\Controllers\CampaignAuditorController::class, 'measureImpact'])->name('recommendations.measure');

        // Advisor Settings
        Route::get('/advisor/settings', [\App\Http\Controllers\CampaignAuditorController::class, 'getAdvisorSettings'])->name('advisor.settings');
        Route::put('/advisor/settings', [\App\Http\Controllers\CampaignAuditorController::class, 'updateAdvisorSettings'])->name('advisor.settings.update');
    });

    Route::prefix('api/campaign-auditor')->name('api.campaign-auditor.')->group(function () {
        Route::get('/dashboard-widget', [\App\Http\Controllers\CampaignAuditorController::class, 'dashboardWidget'])->name('dashboard-widget');
        Route::get('/statistics', [\App\Http\Controllers\CampaignAuditorController::class, 'statistics'])->name('statistics');
    });

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

    // System Pages (HTML pages shown after actions)
    Route::prefix('settings/system-pages')->name('settings.system-pages.')->group(function () {
        Route::get('/', [\App\Http\Controllers\SystemPageController::class, 'index'])->name('index');
        Route::get('/{systemPage}/edit', [\App\Http\Controllers\SystemPageController::class, 'edit'])->name('edit');
        Route::put('/{systemPage}', [\App\Http\Controllers\SystemPageController::class, 'update'])->name('update');
        Route::delete('/{systemPage}', [\App\Http\Controllers\SystemPageController::class, 'destroy'])->name('destroy');
    });

    // System Emails (email templates)
    Route::prefix('settings/system-emails')->name('settings.system-emails.')->group(function () {
        Route::get('/', [\App\Http\Controllers\SystemEmailController::class, 'index'])->name('index');
        Route::get('/{systemEmail}/edit', [\App\Http\Controllers\SystemEmailController::class, 'edit'])->name('edit');
        Route::put('/{systemEmail}', [\App\Http\Controllers\SystemEmailController::class, 'update'])->name('update');
        Route::post('/{systemEmail}/toggle', [\App\Http\Controllers\SystemEmailController::class, 'toggle'])->name('toggle');
        Route::delete('/{systemEmail}', [\App\Http\Controllers\SystemEmailController::class, 'destroy'])->name('destroy');
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

    // SMS Providers
    Route::prefix('settings/sms-providers')->name('settings.sms-providers.')->group(function () {
        Route::get('/', [\App\Http\Controllers\SmsProviderController::class, 'index'])->name('index');
        Route::get('/fields/{provider}', [\App\Http\Controllers\SmsProviderController::class, 'fields'])->name('fields');
        Route::post('/', [\App\Http\Controllers\SmsProviderController::class, 'store'])->name('store');
        Route::put('/{smsProvider}', [\App\Http\Controllers\SmsProviderController::class, 'update'])->name('update');
        Route::delete('/{smsProvider}', [\App\Http\Controllers\SmsProviderController::class, 'destroy'])->name('destroy');
        Route::post('/{smsProvider}/test', [\App\Http\Controllers\SmsProviderController::class, 'test'])->name('test');
        Route::post('/{smsProvider}/default', [\App\Http\Controllers\SmsProviderController::class, 'setDefault'])->name('default');
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

    // Notifications API
    Route::prefix('api/notifications')->name('api.notifications.')->group(function () {
        Route::get('/', [\App\Http\Controllers\NotificationController::class, 'index'])->name('index');
        Route::get('/unread-count', [\App\Http\Controllers\NotificationController::class, 'unreadCount'])->name('unread-count');
        Route::get('/recent', [\App\Http\Controllers\NotificationController::class, 'recent'])->name('recent');
        Route::post('/{notification}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('read');
        Route::post('/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('read-all');
        Route::delete('/{notification}', [\App\Http\Controllers\NotificationController::class, 'destroy'])->name('destroy');
    });

    // Activity Logs (Audit Log)
    Route::prefix('settings/activity-logs')->name('settings.activity-logs.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ActivityLogController::class, 'index'])->name('index');
        Route::get('/export', [\App\Http\Controllers\ActivityLogController::class, 'export'])->name('export');
        Route::delete('/cleanup', [\App\Http\Controllers\ActivityLogController::class, 'cleanup'])->name('cleanup');
    });

    // System Logs (Laravel Log Viewer)
    Route::prefix('settings/logs')->name('settings.logs.')->group(function () {
        Route::get('/', [\App\Http\Controllers\LogViewerController::class, 'index'])->name('index');
        Route::get('/content', [\App\Http\Controllers\LogViewerController::class, 'getLogContent'])->name('content');
        Route::post('/clear', [\App\Http\Controllers\LogViewerController::class, 'clearLog'])->name('clear');
        Route::get('/settings', [\App\Http\Controllers\LogViewerController::class, 'getSettings'])->name('settings');
        Route::post('/settings', [\App\Http\Controllers\LogViewerController::class, 'saveSettings'])->name('settings.save');
        // Webhook logs
        Route::get('/webhooks', [\App\Http\Controllers\LogViewerController::class, 'getWebhookLogs'])->name('webhooks');
        Route::delete('/webhooks/clear', [\App\Http\Controllers\LogViewerController::class, 'clearWebhookLogs'])->name('webhooks.clear');
    });

    // Tracked Links Dashboard
    Route::prefix('settings/tracked-links')->name('settings.tracked-links.')->group(function () {
        Route::get('/', [\App\Http\Controllers\TrackedLinksController::class, 'index'])->name('index');
        Route::get('/show', [\App\Http\Controllers\TrackedLinksController::class, 'show'])->name('show');
        Route::get('/export', [\App\Http\Controllers\TrackedLinksController::class, 'export'])->name('export');
    });

    // Updates/Changelog
    Route::get('/update', [\App\Http\Controllers\UpdatesController::class, 'index'])->name('update.index');

    // Marketplace (Coming Soon)
    Route::get('/marketplace', fn() => Inertia::render('Marketplace/Index'))->name('marketplace.index');
    Route::get('/marketplace/n8n', fn() => Inertia::render('Marketplace/N8n'))->name('marketplace.n8n');
    Route::get('/marketplace/stripe', fn() => Inertia::render('Marketplace/Stripe'))->name('marketplace.stripe');
    Route::get('/marketplace/woocommerce', fn() => Inertia::render('Marketplace/WooCommerce'))->name('marketplace.woocommerce');
    Route::get('/marketplace/shopify', fn() => Inertia::render('Marketplace/Shopify'))->name('marketplace.shopify');
    Route::get('/marketplace/woocommerce/download', function () {
        $path = public_path('plugins/woocommerce/netsendo-woocommerce.zip');
        if (!file_exists($path)) {
            abort(404, 'Plugin file not found');
        }
        return response()->download($path, 'netsendo-woocommerce.zip');
    })->name('marketplace.woocommerce.download');

    // WordPress Integration
    Route::get('/marketplace/wordpress', fn() => Inertia::render('Marketplace/WordPress'))->name('marketplace.wordpress');
    Route::get('/marketplace/wordpress/download', function () {
        $path = public_path('plugins/wordpress/netsendo-wordpress.zip');
        if (!file_exists($path)) {
            abort(404, 'Plugin file not found');
        }
        return response()->download($path, 'netsendo-wordpress.zip');
    })->name('marketplace.wordpress.download');


    // Stripe Settings
    Route::prefix('settings/stripe')->name('settings.stripe.')->group(function () {
        Route::get('/', [\App\Http\Controllers\StripeSettingsController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\StripeSettingsController::class, 'update'])->name('update');
        Route::post('/test-connection', [\App\Http\Controllers\StripeSettingsController::class, 'testConnection'])->name('test-connection');

        // OAuth routes
        Route::get('/oauth/authorize', [\App\Http\Controllers\StripeOAuthController::class, 'redirectToStripe'])->name('oauth.authorize');
        Route::get('/oauth/callback', [\App\Http\Controllers\StripeOAuthController::class, 'callback'])->name('oauth.callback');
        Route::post('/oauth/disconnect', [\App\Http\Controllers\StripeOAuthController::class, 'disconnect'])->name('oauth.disconnect');
    });

    // Stripe Products (Settings)
    Route::prefix('settings/stripe-products')->name('settings.stripe-products.')->group(function () {
        Route::get('/', [\App\Http\Controllers\StripeProductController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\StripeProductController::class, 'store'])->name('store');
        Route::put('/{product}', [\App\Http\Controllers\StripeProductController::class, 'update'])->name('update');
        Route::delete('/{product}', [\App\Http\Controllers\StripeProductController::class, 'destroy'])->name('destroy');
        Route::get('/{product}/transactions', [\App\Http\Controllers\StripeProductController::class, 'transactions'])->name('transactions');
        Route::post('/{product}/checkout-url', [\App\Http\Controllers\StripeProductController::class, 'checkoutUrl'])->name('checkout-url');
        Route::get('/all-transactions', [\App\Http\Controllers\StripeProductController::class, 'allTransactions'])->name('all-transactions');
    });

    // Polar Marketplace Page
    Route::get('/marketplace/polar', fn() => Inertia::render('Marketplace/Polar'))->name('marketplace.polar');

    // Polar Settings
    Route::prefix('settings/polar')->name('settings.polar.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PolarSettingsController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\PolarSettingsController::class, 'update'])->name('update');
        Route::post('/test-connection', [\App\Http\Controllers\PolarSettingsController::class, 'testConnection'])->name('test-connection');
    });

    // Polar Products (Settings)
    Route::prefix('settings/polar-products')->name('settings.polar-products.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PolarProductController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\PolarProductController::class, 'store'])->name('store');
        Route::put('/{product}', [\App\Http\Controllers\PolarProductController::class, 'update'])->name('update');
        Route::delete('/{product}', [\App\Http\Controllers\PolarProductController::class, 'destroy'])->name('destroy');
        Route::get('/{product}/transactions', [\App\Http\Controllers\PolarProductController::class, 'transactions'])->name('transactions');
        Route::post('/{product}/checkout-url', [\App\Http\Controllers\PolarProductController::class, 'checkoutUrl'])->name('checkout-url');
        Route::get('/all-transactions', [\App\Http\Controllers\PolarProductController::class, 'allTransactions'])->name('all-transactions');
    });

    // Sales Funnels (for external page product embedding)
    Route::prefix('settings/sales-funnels')->name('settings.sales-funnels.')->group(function () {
        Route::get('/', [\App\Http\Controllers\SalesFunnelController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\SalesFunnelController::class, 'store'])->name('store');
        Route::put('/{salesFunnel}', [\App\Http\Controllers\SalesFunnelController::class, 'update'])->name('update');
        Route::delete('/{salesFunnel}', [\App\Http\Controllers\SalesFunnelController::class, 'destroy'])->name('destroy');
        Route::get('/options', [\App\Http\Controllers\SalesFunnelController::class, 'getOptions'])->name('options');
        Route::post('/embed-code', [\App\Http\Controllers\SalesFunnelController::class, 'getEmbedCode'])->name('embed-code');
    });

    // User Management (Team Members)
    Route::prefix('settings/users')->name('settings.users.')->group(function () {
        Route::get('/', [\App\Http\Controllers\UserManagementController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\UserManagementController::class, 'store'])->name('store');
        Route::post('/create-user', [\App\Http\Controllers\UserManagementController::class, 'createUser'])->name('create-user');
        Route::put('/{user}/permissions', [\App\Http\Controllers\UserManagementController::class, 'updatePermissions'])->name('permissions');
        Route::delete('/{user}', [\App\Http\Controllers\UserManagementController::class, 'destroy'])->name('destroy');
        Route::delete('/invitation/{invitation}', [\App\Http\Controllers\UserManagementController::class, 'cancelInvitation'])->name('cancel-invitation');
    });
    // Webinars
    Route::prefix('webinars')->name('webinars.')->group(function () {
        Route::get('/', [\App\Http\Controllers\WebinarController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\WebinarController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\WebinarController::class, 'store'])->name('store');
        Route::get('/{webinar}', [\App\Http\Controllers\WebinarController::class, 'show'])->name('show');
        Route::get('/{webinar}/edit', [\App\Http\Controllers\WebinarController::class, 'edit'])->name('edit');
        Route::put('/{webinar}', [\App\Http\Controllers\WebinarController::class, 'update'])->name('update');
        Route::delete('/{webinar}', [\App\Http\Controllers\WebinarController::class, 'destroy'])->name('destroy');
        Route::post('/{webinar}/duplicate', [\App\Http\Controllers\WebinarController::class, 'duplicate'])->name('duplicate');
        Route::get('/{webinar}/studio', [\App\Http\Controllers\WebinarController::class, 'studio'])->name('studio');
        Route::post('/{webinar}/start', [\App\Http\Controllers\WebinarController::class, 'start'])->name('start');
        Route::post('/{webinar}/end', [\App\Http\Controllers\WebinarController::class, 'end'])->name('end');
        Route::get('/{webinar}/analytics', [\App\Http\Controllers\WebinarController::class, 'analytics'])->name('analytics');
        Route::post('/{webinar}/update-status', [\App\Http\Controllers\WebinarController::class, 'updateStatus'])->name('update-status');

        // Chat API
        Route::get('/{webinar}/chat', [\App\Http\Controllers\WebinarChatController::class, 'index'])->name('chat.index');
        Route::post('/{webinar}/chat', [\App\Http\Controllers\WebinarChatController::class, 'send'])->name('chat.send');
        Route::post('/{webinar}/chat/{message}/pin', [\App\Http\Controllers\WebinarChatController::class, 'pin'])->name('chat.pin');
        Route::post('/{webinar}/chat/{message}/unpin', [\App\Http\Controllers\WebinarChatController::class, 'unpin'])->name('chat.unpin');
        Route::delete('/{webinar}/chat/{message}', [\App\Http\Controllers\WebinarChatController::class, 'delete'])->name('chat.delete');
        Route::post('/{webinar}/chat/{message}/highlight', [\App\Http\Controllers\WebinarChatController::class, 'highlight'])->name('chat.highlight');
        Route::get('/{webinar}/chat/questions', [\App\Http\Controllers\WebinarChatController::class, 'questions'])->name('chat.questions');
        Route::post('/{webinar}/chat/{question}/answer', [\App\Http\Controllers\WebinarChatController::class, 'answer'])->name('chat.answer');
        Route::get('/{webinar}/chat/pending', [\App\Http\Controllers\WebinarChatController::class, 'pending'])->name('chat.pending');
        Route::post('/{webinar}/chat/{message}/approve', [\App\Http\Controllers\WebinarChatController::class, 'approve'])->name('chat.approve');

        // Products
        Route::get('/{webinar}/products', [\App\Http\Controllers\WebinarProductController::class, 'index'])->name('products.index');
        Route::post('/{webinar}/products', [\App\Http\Controllers\WebinarProductController::class, 'store'])->name('products.store');
        Route::put('/{webinar}/products/{product}', [\App\Http\Controllers\WebinarProductController::class, 'update'])->name('products.update');
        Route::delete('/{webinar}/products/{product}', [\App\Http\Controllers\WebinarProductController::class, 'destroy'])->name('products.destroy');
        Route::post('/{webinar}/products/{product}/pin', [\App\Http\Controllers\WebinarProductController::class, 'pin'])->name('products.pin');
        Route::post('/{webinar}/products/{product}/unpin', [\App\Http\Controllers\WebinarProductController::class, 'unpin'])->name('products.unpin');
        Route::post('/{webinar}/products/reorder', [\App\Http\Controllers\WebinarProductController::class, 'reorder'])->name('products.reorder');

        // Auto-Webinar Configuration
        Route::get('/{webinar}/auto-config', [\App\Http\Controllers\AutoWebinarController::class, 'config'])->name('auto.config');
        Route::post('/{webinar}/auto-config/schedule', [\App\Http\Controllers\AutoWebinarController::class, 'saveSchedule'])->name('auto.schedule');
        Route::post('/{webinar}/auto-config/import-chat', [\App\Http\Controllers\AutoWebinarController::class, 'importChat'])->name('auto.import-chat');
        Route::post('/{webinar}/auto-config/generate-chat', [\App\Http\Controllers\AutoWebinarController::class, 'generateChat'])->name('auto.generate-chat');
        Route::delete('/{webinar}/auto-config/chat', [\App\Http\Controllers\AutoWebinarController::class, 'clearChat'])->name('auto.clear-chat');
        Route::get('/{webinar}/auto-config/timeline', [\App\Http\Controllers\AutoWebinarController::class, 'previewTimeline'])->name('auto.timeline');
        Route::get('/{webinar}/auto-config/sessions', [\App\Http\Controllers\AutoWebinarController::class, 'getNextSessions'])->name('auto.sessions');
        Route::post('/{webinar}/auto-config/convert', [\App\Http\Controllers\AutoWebinarController::class, 'convert'])->name('auto.convert');

        // Chat Reactions API
        Route::post('/{webinar}/reactions', [\App\Http\Controllers\WebinarChatController::class, 'addReaction'])->name('reactions.add');
        Route::get('/{webinar}/reactions/stats', [\App\Http\Controllers\WebinarChatController::class, 'reactionStats'])->name('reactions.stats');
        Route::post('/{webinar}/chat/{message}/like', [\App\Http\Controllers\WebinarChatController::class, 'like'])->name('chat.like');

        // Host Control Panel API
        Route::get('/{webinar}/host/dashboard', [\App\Http\Controllers\WebinarHostController::class, 'dashboard'])->name('host.dashboard');
        Route::post('/{webinar}/host/chat-settings', [\App\Http\Controllers\WebinarHostController::class, 'updateChatSettings'])->name('host.chat-settings');
        Route::post('/{webinar}/host/announcement', [\App\Http\Controllers\WebinarHostController::class, 'sendAnnouncement'])->name('host.announcement');
        Route::post('/{webinar}/host/trigger-product', [\App\Http\Controllers\WebinarHostController::class, 'triggerProduct'])->name('host.trigger-product');
        Route::get('/{webinar}/host/viewers', [\App\Http\Controllers\WebinarHostController::class, 'viewersCount'])->name('host.viewers');
        Route::post('/{webinar}/host/bulk-approve', [\App\Http\Controllers\WebinarHostController::class, 'bulkApprove'])->name('host.bulk-approve');
        Route::post('/{webinar}/host/bulk-delete', [\App\Http\Controllers\WebinarHostController::class, 'bulkDelete'])->name('host.bulk-delete');

        // Scenario Builder API
        Route::get('/{webinar}/scripts', [\App\Http\Controllers\AutoWebinarScriptController::class, 'index'])->name('scripts.index');
        Route::get('/{webinar}/scripts/builder', [\App\Http\Controllers\AutoWebinarScriptController::class, 'builder'])->name('scripts.builder');
        Route::post('/{webinar}/scripts', [\App\Http\Controllers\AutoWebinarScriptController::class, 'store'])->name('scripts.store');
        Route::put('/{webinar}/scripts/{script}', [\App\Http\Controllers\AutoWebinarScriptController::class, 'update'])->name('scripts.update');
        Route::post('/{webinar}/scripts/generate', [\App\Http\Controllers\AutoWebinarScriptController::class, 'generateRandom'])->name('scripts.generate');
        Route::delete('/{webinar}/scripts/clear', [\App\Http\Controllers\AutoWebinarScriptController::class, 'clear'])->name('scripts.clear');
        Route::delete('/{webinar}/scripts/{script}', [\App\Http\Controllers\AutoWebinarScriptController::class, 'destroy'])->name('scripts.destroy');
    });
});

// Public Webinar Routes (no auth)
Route::prefix('webinar')->name('webinar.')->group(function () {
    Route::get('/{slug}', [\App\Http\Controllers\Public\PublicWebinarController::class, 'register'])->name('register');
    Route::post('/{slug}', [\App\Http\Controllers\Public\PublicWebinarController::class, 'submitRegistration'])->name('register.submit');
    Route::get('/{slug}/watch/{token}', [\App\Http\Controllers\Public\PublicWebinarController::class, 'watch'])->name('watch');
    Route::get('/{slug}/replay/{token}', [\App\Http\Controllers\Public\PublicWebinarController::class, 'replay'])->name('replay');
    Route::post('/{slug}/leave/{token}', [\App\Http\Controllers\Public\PublicWebinarController::class, 'leave'])->name('leave');
    Route::post('/{slug}/progress/{token}', [\App\Http\Controllers\Public\PublicWebinarController::class, 'trackProgress'])->name('progress');
    Route::get('/{slug}/auto/{subscriberToken}', [\App\Http\Controllers\Public\PublicWebinarController::class, 'autoRegister'])->name('auto-register')->middleware('signed');
});

// API route for license status (no auth required for setup checks)
Route::get('/api/license/status', [LicenseController::class, 'status'])->name('license.status');

// Webhook for automatic license activation from external system (no auth, public endpoint)
Route::post('/api/license/webhook', [LicenseController::class, 'webhookActivate'])->name('license.webhook');

// Locale switching (works for guests and authenticated users)
Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

// Team Invitation Acceptance (public, no auth required)
Route::get('/invitation/{token}', [\App\Http\Controllers\UserManagementController::class, 'acceptInvitation'])->name('invitation.accept');
Route::post('/invitation/{token}', [\App\Http\Controllers\UserManagementController::class, 'completeInvitation'])->name('invitation.complete');


// Tracking Routes
Route::get('/t/open/{message}/{subscriber}/{hash}', [\App\Http\Controllers\TrackingController::class, 'trackOpen'])->name('tracking.open');
Route::get('/t/click/{message}/{subscriber}/{hash}', [\App\Http\Controllers\TrackingController::class, 'trackClick'])->name('tracking.click');

// Read Session Tracking (for email read time)
Route::get('/t/read-start/{message}/{subscriber}/{hash}', [\App\Http\Controllers\TrackingController::class, 'startReadSession'])->name('tracking.read-start');
Route::post('/t/heartbeat', [\App\Http\Controllers\TrackingController::class, 'heartbeat'])->name('tracking.heartbeat');
Route::post('/t/read-end', [\App\Http\Controllers\TrackingController::class, 'endReadSession'])->name('tracking.read-end');

// Page Visit Tracking
Route::post('/t/page', [\App\Http\Controllers\PageVisitController::class, 'track'])->name('tracking.page');
Route::get('/t/page-script/{user}', [\App\Http\Controllers\PageVisitController::class, 'getTrackingScript'])->name('tracking.page-script');
Route::post('/t/link-visitor', [\App\Http\Controllers\PageVisitController::class, 'linkVisitor'])->name('tracking.link-visitor');

// Unsubscribe Routes (signed URLs from emails)
Route::get('/unsubscribe/{subscriber}/{list}', [\App\Http\Controllers\UnsubscribeController::class, 'confirm'])->name('subscriber.unsubscribe.confirm');
Route::get('/unsubscribe/{subscriber}/{list}/process', [\App\Http\Controllers\UnsubscribeController::class, 'process'])->name('subscriber.unsubscribe.process');
Route::get('/unsubscribe/{subscriber}', [\App\Http\Controllers\UnsubscribeController::class, 'globalUnsubscribe'])->name('subscriber.unsubscribe.global');

// Subscriber Activation Routes (signed URLs from system emails)
Route::get('/activate/{subscriber}/{list}', [\App\Http\Controllers\ActivationController::class, 'activate'])->name('subscriber.activate');
Route::get('/resubscribe/{subscriber}/{list}', [\App\Http\Controllers\ActivationController::class, 'resubscribe'])->name('subscriber.resubscribe');

// External Pages (Public)
Route::get('/p/{externalPage}', [\App\Http\Controllers\Public\ExternalPageHandlerController::class, 'show'])->name('page.show');

// Public Subscription Forms (no auth)
Route::prefix('subscribe')->name('subscribe.')->group(function () {
    Route::get('/form/{slug}', [\App\Http\Controllers\PublicFormController::class, 'show'])->name('form');
    Route::post('/{slug}', [\App\Http\Controllers\PublicFormController::class, 'submit'])->name('submit');
    Route::get('/js/{slug}', [\App\Http\Controllers\PublicFormController::class, 'javascript'])->name('js');
    Route::get('/success/{slug}', [\App\Http\Controllers\PublicFormController::class, 'success'])->name('success');
    Route::get('/error/{slug}', [\App\Http\Controllers\PublicFormController::class, 'error'])->name('error');
});

// Public Sales Funnel Checkout Routes (no auth)
Route::get('/checkout/{type}/{product}', [\App\Http\Controllers\Public\SalesFunnelCheckoutController::class, 'checkout'])->name('sales-funnel.checkout');
Route::get('/checkout/success/{funnel}', [\App\Http\Controllers\Public\SalesFunnelCheckoutController::class, 'success'])->name('sales-funnel.success');

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

// Stripe Webhook (public, Stripe-signature authenticated)
Route::post('/webhooks/stripe', [\App\Http\Controllers\Webhooks\StripeController::class, 'handle'])->name('webhooks.stripe');

// Polar Webhook (public, Polar-signature authenticated)
Route::post('/webhooks/polar', [\App\Http\Controllers\Webhooks\PolarController::class, 'handle'])->name('webhooks.polar');

// WooCommerce Webhook (public, API-key authenticated)
Route::post('/webhooks/woocommerce', [\App\Http\Controllers\Webhooks\WooCommerceController::class, 'handle'])->name('webhooks.woocommerce');

// Shopify Webhook (public, HMAC + API-key authenticated)
Route::post('/webhooks/shopify', [\App\Http\Controllers\Webhooks\ShopifyController::class, 'handle'])->name('webhooks.shopify');

// Funnel Task Completion Webhook (public, for external quiz/task systems)
Route::prefix('funnel/task')->name('funnel.task.')->group(function () {
    Route::post('/complete', [\App\Http\Controllers\Public\FunnelTaskController::class, 'complete'])->name('complete');
    Route::get('/status', [\App\Http\Controllers\Public\FunnelTaskController::class, 'status'])->name('status');
});


require __DIR__.'/auth.php';


