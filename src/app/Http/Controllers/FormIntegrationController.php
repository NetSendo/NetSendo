<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionForm;
use App\Models\FormIntegration;
use Illuminate\Http\Request;

class FormIntegrationController extends Controller
{
    /**
     * List integrations for a form.
     */
    public function index(SubscriptionForm $form)
    {
        $this->authorize('view', $form);

        $integrations = $form->integrations()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'integrations' => $integrations,
        ]);
    }

    /**
     * Store a new integration.
     */
    public function store(Request $request, SubscriptionForm $form)
    {
        $this->authorize('update', $form);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:webhook,wordpress,elementor,zapier,make,n8n',
            'status' => 'in:active,disabled',
            'webhook_url' => 'required_if:type,webhook,zapier,make,n8n|nullable|url|max:500',
            'webhook_method' => 'in:POST,GET',
            'webhook_headers' => 'nullable|array',
            'webhook_format' => 'in:json,form',
            'auth_type' => 'in:none,bearer,basic,api_key',
            'auth_token' => 'nullable|string',
            'api_key_name' => 'nullable|string|max:50',
            'api_key_value' => 'nullable|string',
            'trigger_on' => 'nullable|array',
        ]);

        $integration = $form->integrations()->create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Integracja została dodana',
            'integration' => $integration,
        ]);
    }

    /**
     * Update an integration.
     */
    public function update(Request $request, SubscriptionForm $form, FormIntegration $integration)
    {
        $this->authorize('update', $form);

        // Ensure integration belongs to form
        if ($integration->subscription_form_id !== $form->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:webhook,wordpress,elementor,zapier,make,n8n',
            'status' => 'in:active,disabled',
            'webhook_url' => 'required_if:type,webhook,zapier,make,n8n|nullable|url|max:500',
            'webhook_method' => 'in:POST,GET',
            'webhook_headers' => 'nullable|array',
            'webhook_format' => 'in:json,form',
            'auth_type' => 'in:none,bearer,basic,api_key',
            'auth_token' => 'nullable|string',
            'api_key_name' => 'nullable|string|max:50',
            'api_key_value' => 'nullable|string',
            'trigger_on' => 'nullable|array',
        ]);

        // Don't overwrite encrypted fields if not provided
        if (empty($validated['auth_token'])) {
            unset($validated['auth_token']);
        }
        if (empty($validated['api_key_value'])) {
            unset($validated['api_key_value']);
        }

        $integration->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Integracja została zaktualizowana',
            'integration' => $integration->fresh(),
        ]);
    }

    /**
     * Delete an integration.
     */
    public function destroy(SubscriptionForm $form, FormIntegration $integration)
    {
        $this->authorize('update', $form);

        if ($integration->subscription_form_id !== $form->id) {
            abort(404);
        }

        $integration->delete();

        return response()->json([
            'success' => true,
            'message' => 'Integracja została usunięta',
        ]);
    }

    /**
     * Test webhook integration.
     */
    public function test(SubscriptionForm $form, FormIntegration $integration)
    {
        $this->authorize('view', $form);

        if ($integration->subscription_form_id !== $form->id) {
            abort(404);
        }

        $result = $integration->sendTest();

        return response()->json($result);
    }
}
