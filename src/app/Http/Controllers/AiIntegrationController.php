<?php

namespace App\Http\Controllers;

use App\Models\AiIntegration;
use App\Models\AiModel;
use App\Services\AI\AiService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AiIntegrationController extends Controller
{
    public function __construct(
        protected AiService $aiService
    ) {}

    /**
     * Display the AI integrations settings page.
     */
    public function index()
    {
        $integrations = AiIntegration::with('models')->get();
        $providers = AiIntegration::getProviders();

        // Group integrations by provider
        $integrationsMap = $integrations->keyBy('provider');

        return Inertia::render('Settings/AiIntegrations/Index', [
            'integrations' => $integrations,
            'integrationsMap' => $integrationsMap,
            'providers' => $providers,
        ]);
    }

    /**
     * Get list of all providers with their configurations.
     */
    public function providers()
    {
        $providers = AiIntegration::getProviders();

        // Add default models to each provider
        foreach ($providers as $key => &$provider) {
            $provider['default_models'] = AiIntegration::getDefaultModels($key);
        }

        return response()->json($providers);
    }

    /**
     * Store a new AI integration.
     */
    public function store(Request $request)
    {
        $providers = array_keys(AiIntegration::getProviders());

        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in($providers)],
            'name' => ['required', 'string', 'max:255'],
            'api_key' => ['nullable', 'string'],
            'base_url' => ['nullable', 'url', 'max:500'],
            'default_model' => ['nullable', 'string', 'max:255'],
        ]);

        // Check if integration for this provider already exists
        $existing = AiIntegration::where('provider', $validated['provider'])->first();
        if ($existing) {
            return back()->withErrors([
                'provider' => 'Integracja z tym dostawcą już istnieje. Edytuj istniejącą zamiast tworzyć nową.',
            ]);
        }

        $integration = AiIntegration::create([
            'provider' => $validated['provider'],
            'name' => $validated['name'],
            'api_key' => $validated['api_key'] ?? null,
            'base_url' => $validated['base_url'] ?? null,
            'default_model' => $validated['default_model'] ?? null,
            'is_active' => true,
        ]);

        // Add default models
        $defaultModels = AiIntegration::getDefaultModels($validated['provider']);
        foreach ($defaultModels as $model) {
            $integration->models()->create([
                'model_id' => $model['model_id'],
                'display_name' => $model['display_name'],
                'is_custom' => false,
            ]);
        }

        return back()->with('success', 'Integracja AI została dodana.');
    }

    /**
     * Update an existing AI integration.
     */
    public function update(Request $request, AiIntegration $integration)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'api_key' => ['nullable', 'string'],
            'base_url' => ['nullable', 'url', 'max:500'],
            'default_model' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        // Only update api_key if provided (don't clear it if empty)
        if (array_key_exists('api_key', $validated) && empty($validated['api_key'])) {
            unset($validated['api_key']);
        }

        $integration->update($validated);

        return back()->with('success', 'Integracja AI została zaktualizowana.');
    }

    /**
     * Delete an AI integration.
     */
    public function destroy(AiIntegration $integration)
    {
        $integration->delete();

        return back()->with('success', 'Integracja AI została usunięta.');
    }

    /**
     * Test the connection for an integration.
     */
    public function testConnection(AiIntegration $integration)
    {
        $result = $this->aiService->testConnection($integration);

        return response()->json($result);
    }

    /**
     * Fetch available models from the provider API.
     */
    public function fetchModels(AiIntegration $integration)
    {
        $models = $this->aiService->fetchModels($integration);

        // Update stored models if we got new ones from API
        if (!empty($models)) {
            // Get existing custom models
            $customModels = $integration->models()->where('is_custom', true)->get();

            // Clear non-custom models and add new ones
            $integration->models()->where('is_custom', false)->delete();

            foreach ($models as $model) {
                $integration->models()->updateOrCreate(
                    ['model_id' => $model['model_id']],
                    [
                        'display_name' => $model['display_name'],
                        'is_custom' => false,
                    ]
                );
            }
        }

        return response()->json([
            'success' => true,
            'models' => $models,
        ]);
    }

    /**
     * Add a custom model to an integration.
     */
    public function addModel(Request $request, AiIntegration $integration)
    {
        $validated = $request->validate([
            'model_id' => ['required', 'string', 'max:255'],
            'display_name' => ['required', 'string', 'max:255'],
        ]);

        $model = $integration->models()->create([
            'model_id' => $validated['model_id'],
            'display_name' => $validated['display_name'],
            'is_custom' => true,
        ]);

        return response()->json([
            'success' => true,
            'model' => $model,
        ]);
    }

    /**
     * Remove a custom model from an integration.
     */
    public function removeModel(AiIntegration $integration, AiModel $model)
    {
        if ($model->ai_integration_id !== $integration->id) {
            abort(403);
        }

        $model->delete();

        return response()->json(['success' => true]);
    }
}
