<?php

namespace App\Http\Controllers;

use App\Models\Webinar;
use App\Models\WebinarProduct;
use App\Services\Webinar\WebinarChatService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WebinarProductController extends Controller
{
    public function __construct(protected WebinarChatService $chatService) {}

    public function index(Webinar $webinar): JsonResponse
    {
        $this->authorize('view', $webinar);

        return response()->json([
            'products' => $webinar->products()->orderBy('sort_order')->get()
                ->map(fn($p) => $p->toDisplayArray()),
        ]);
    }

    public function store(Request $request, Webinar $webinar): JsonResponse
    {
        $this->authorize('update', $webinar);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'string|size:3',
            'original_price' => 'nullable|numeric|min:0',
            'stripe_product_id' => 'nullable|exists:stripe_products,id',
            'polar_product_id' => 'nullable|exists:polar_products,id',
            'external_checkout_url' => 'nullable|url',
            'image_url' => 'nullable|url',
            'cta_text' => 'string|max:50',
            'cta_color' => 'string|max:7',
            'pin_at_seconds' => 'nullable|integer|min:0',
            'unpin_at_seconds' => 'nullable|integer|min:0',
            'show_countdown' => 'boolean',
            'countdown_minutes' => 'nullable|integer|min:1',
            'limited_quantity' => 'nullable|integer|min:1',
            'bonuses' => 'nullable|array',
        ]);

        $product = $webinar->products()->create($validated);

        return response()->json([
            'product' => $product->toDisplayArray(),
        ], 201);
    }

    public function update(Request $request, Webinar $webinar, WebinarProduct $product): JsonResponse
    {
        $this->authorize('update', $webinar);

        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'price' => 'numeric|min:0',
            'original_price' => 'nullable|numeric|min:0',
            'cta_text' => 'string|max:50',
            'cta_color' => 'string|max:7',
            'pin_at_seconds' => 'nullable|integer|min:0',
            'unpin_at_seconds' => 'nullable|integer|min:0',
            'show_countdown' => 'boolean',
            'countdown_minutes' => 'nullable|integer|min:1',
            'limited_quantity' => 'nullable|integer|min:1',
            'bonuses' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        $product->update($validated);

        return response()->json([
            'product' => $product->fresh()->toDisplayArray(),
        ]);
    }

    public function destroy(Webinar $webinar, WebinarProduct $product): JsonResponse
    {
        $this->authorize('update', $webinar);

        $product->delete();

        return response()->json(['success' => true]);
    }

    public function pin(Webinar $webinar, WebinarProduct $product): JsonResponse
    {
        $this->authorize('update', $webinar);

        $session = $webinar->sessions()->where('status', 'live')->first();
        $this->chatService->pinProduct($webinar, $product, $session);

        return response()->json([
            'product' => $product->fresh()->toDisplayArray(),
        ]);
    }

    public function unpin(Webinar $webinar, WebinarProduct $product): JsonResponse
    {
        $this->authorize('update', $webinar);

        $this->chatService->unpinProduct($product);

        return response()->json(['success' => true]);
    }

    public function reorder(Request $request, Webinar $webinar): JsonResponse
    {
        $this->authorize('update', $webinar);

        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:webinar_products,id',
        ]);

        foreach ($validated['order'] as $index => $productId) {
            WebinarProduct::where('id', $productId)
                ->where('webinar_id', $webinar->id)
                ->update(['sort_order' => $index]);
        }

        return response()->json(['success' => true]);
    }
}
