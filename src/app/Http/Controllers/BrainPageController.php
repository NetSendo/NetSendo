<?php

namespace App\Http\Controllers;

use App\Models\AiBrainSettings;
use App\Models\AiConversation;
use App\Models\AiIntegration;
use App\Models\KnowledgeEntry;
use App\Services\Brain\ModeController;
use App\Services\Brain\Telegram\TelegramAuthService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BrainPageController extends Controller
{
    public function __construct(
        protected ModeController $modeController,
        protected TelegramAuthService $telegramAuth,
    ) {}

    /**
     * Brain Chat — main page.
     * GET /brain
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $settings = AiBrainSettings::getForUser($user->id);

        $conversations = AiConversation::forUser($user->id)
            ->orderByDesc('last_activity_at')
            ->with(['messages' => function ($q) {
                $q->orderByDesc('created_at')->limit(1);
            }])
            ->limit(30)
            ->get();

        return Inertia::render('Brain/Index', [
            'conversations' => $conversations,
            'settings' => [
                'work_mode' => $settings->work_mode ?? 'semi_auto',
            ],
        ]);
    }

    /**
     * Brain Settings page.
     * GET /brain/settings
     */
    public function settings(Request $request): Response
    {
        $user = $request->user();
        $settings = AiBrainSettings::getForUser($user->id);

        $knowledgeEntries = KnowledgeEntry::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        // Get available AI integrations with their models
        $integrations = AiIntegration::active()
            ->whereNotNull('api_key')
            ->orWhere('provider', 'ollama')
            ->get()
            ->map(function ($integration) {
                return [
                    'id' => $integration->id,
                    'provider' => $integration->provider,
                    'name' => $integration->name,
                    'default_model' => $integration->default_model,
                    'models' => AiIntegration::getDefaultModels($integration->provider),
                ];
            });

        return Inertia::render('Brain/Settings', [
            'settings' => $settings,
            'modes' => [
                [
                    'value' => 'autonomous',
                    'label' => $this->modeController->getModeLabel('autonomous'),
                    'description' => $this->modeController->getModeDescription('autonomous'),
                ],
                [
                    'value' => 'semi_auto',
                    'label' => $this->modeController->getModeLabel('semi_auto'),
                    'description' => $this->modeController->getModeDescription('semi_auto'),
                ],
                [
                    'value' => 'manual',
                    'label' => $this->modeController->getModeLabel('manual'),
                    'description' => $this->modeController->getModeDescription('manual'),
                ],
            ],
            'telegramConnected' => $settings->isTelegramConnected(),
            'knowledgeEntries' => $knowledgeEntries,
            'knowledgeCategories' => KnowledgeEntry::CATEGORIES,
            'aiIntegrations' => $integrations,
            'modelRoutingTasks' => collect(AiBrainSettings::MODEL_ROUTING_TASKS)->map(fn($v, $k) => 'brain.task.' . $k),
        ]);
    }
}
