<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WebhookResource;
use App\Models\Webhook;
use App\Services\WebhookDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WebhookController extends Controller
{
    public function __construct(
        protected WebhookDispatcher $dispatcher
    ) {}

    /**
     * List all webhooks for the authenticated user
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $webhooks = Webhook::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 25));

        return WebhookResource::collection($webhooks);
    }

    /**
     * Create a new webhook
     */
    public function store(Request $request): WebhookResource|JsonResponse
    {
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('webhooks:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have webhooks:write permission',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:2048',
            'events' => 'required|array|min:1',
            'events.*' => 'string|in:' . implode(',', Webhook::EVENTS),
            'is_active' => 'nullable|boolean',
        ]);

        $webhook = Webhook::createWithSecret([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'url' => $validated['url'],
            'events' => $validated['events'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        // Return the secret only on creation (never shown again)
        $resource = new WebhookResource($webhook);
        $data = $resource->toArray($request);
        $data['secret'] = $webhook->secret;

        return response()->json([
            'data' => $data,
            'message' => 'Webhook created successfully. Save the secret - it will not be shown again.',
        ], 201);
    }

    /**
     * Get a single webhook
     */
    public function show(Request $request, int $id): WebhookResource|JsonResponse
    {
        $webhook = Webhook::where('user_id', $request->user()->id)->find($id);

        if (!$webhook) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Webhook not found',
            ], 404);
        }

        return new WebhookResource($webhook);
    }

    /**
     * Update a webhook
     */
    public function update(Request $request, int $id): WebhookResource|JsonResponse
    {
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('webhooks:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have webhooks:write permission',
            ], 403);
        }

        $webhook = Webhook::where('user_id', $request->user()->id)->find($id);

        if (!$webhook) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Webhook not found',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'url' => 'sometimes|url|max:2048',
            'events' => 'sometimes|array|min:1',
            'events.*' => 'string|in:' . implode(',', Webhook::EVENTS),
            'is_active' => 'nullable|boolean',
        ]);

        $webhook->update($validated);

        return new WebhookResource($webhook);
    }

    /**
     * Delete a webhook
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('webhooks:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have webhooks:write permission',
            ], 403);
        }

        $webhook = Webhook::where('user_id', $request->user()->id)->find($id);

        if (!$webhook) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Webhook not found',
            ], 404);
        }

        $webhook->delete();

        return response()->json([
            'message' => 'Webhook deleted successfully',
        ]);
    }

    /**
     * Test a webhook
     */
    public function test(Request $request, int $id): JsonResponse
    {
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('webhooks:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have webhooks:write permission',
            ], 403);
        }

        $webhook = Webhook::where('user_id', $request->user()->id)->find($id);

        if (!$webhook) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Webhook not found',
            ], 404);
        }

        $result = $this->dispatcher->sendTest($webhook);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Get list of available webhook events
     */
    public function availableEvents(): JsonResponse
    {
        return response()->json([
            'events' => Webhook::EVENTS,
        ]);
    }

    /**
     * Regenerate webhook secret
     */
    public function regenerateSecret(Request $request, int $id): JsonResponse
    {
        $apiKey = $request->get('api_key');
        if (!$apiKey->hasPermission('webhooks:write')) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'API key does not have webhooks:write permission',
            ], 403);
        }

        $webhook = Webhook::where('user_id', $request->user()->id)->find($id);

        if (!$webhook) {
            return response()->json([
                'error' => 'Not Found',
                'message' => 'Webhook not found',
            ], 404);
        }

        $newSecret = \Illuminate\Support\Str::random(64);
        $webhook->update(['secret' => $newSecret]);

        return response()->json([
            'secret' => $newSecret,
            'message' => 'Secret regenerated successfully. Save the new secret - it will not be shown again.',
        ]);
    }
}
