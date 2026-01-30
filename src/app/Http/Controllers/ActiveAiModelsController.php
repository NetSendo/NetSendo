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
                // Start with default models to ensure new models are always available
                $defaultModels = AiIntegration::getDefaultModels($integration->provider);
                $modelMap = collect($defaultModels)->keyBy('model_id')->map(function ($model) {
                    return [
                        'id' => $model['model_id'],
                        'name' => $model['display_name'],
                    ];
                });

                // Merge with stored models (stored models take precedence for display names)
                $integration->models->each(function ($model) use (&$modelMap) {
                    $modelMap[$model->model_id] = [
                        'id' => $model->model_id,
                        'name' => $model->display_name,
                    ];
                });

                $models = $modelMap->values();

                return [
                    'id' => $integration->id,
                    'provider' => $integration->provider,
                    'name' => $integration->name,
                    'default_model' => $integration->default_model,
                    'models' => $models->all(),
                ];
            });

        return response()->json([
            'integrations' => $integrations->values()->all(),
        ]);
    }
}
