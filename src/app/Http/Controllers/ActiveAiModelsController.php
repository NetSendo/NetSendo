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
                $models = $integration->models->map(function ($model) {
                    return [
                        'id' => $model->model_id,
                        'name' => $model->display_name,
                    ];
                });

                // If no models stored, get defaults
                if ($models->isEmpty()) {
                    $defaultModels = AiIntegration::getDefaultModels($integration->provider);
                    $models = collect($defaultModels)->map(function ($model) {
                        return [
                            'id' => $model['model_id'],
                            'name' => $model['display_name'],
                        ];
                    });
                }

                return [
                    'id' => $integration->id,
                    'provider' => $integration->provider,
                    'name' => $integration->name,
                    'default_model' => $integration->default_model,
                    'models' => $models->values()->all(),
                ];
            });

        return response()->json([
            'integrations' => $integrations->values()->all(),
        ]);
    }
}
