<?php

namespace App\Http\Controllers;

use App\Models\AiIntegration;
use Illuminate\Http\JsonResponse;

class ActiveAiModelsController extends Controller
{
    /**
     * Get all active AI integrations with their available models.
     * Used by AI assistants to populate model selection dropdowns.
     */
    public function index(): JsonResponse
    {
        $integrations = AiIntegration::with('models')
            ->active()
            ->where(function ($query) {
                $query->whereNotNull('api_key')
                    ->orWhere('provider', 'ollama');
            })
            ->get()
            ->map(function ($integration) {
                // availableModels() merges the default catalog with stored/fetched
                // models so newly released models are always available.
                $models = collect($integration->availableModels())
                    ->map(fn (array $model) => [
                        'id' => $model['model_id'],
                        'name' => $model['display_name'],
                    ])
                    ->all();

                return [
                    'id' => $integration->id,
                    'provider' => $integration->provider,
                    'name' => $integration->name,
                    'default_model' => $integration->default_model,
                    'models' => $models,
                ];
            });

        return response()->json([
            'integrations' => $integrations->values()->all(),
        ]);
    }
}
