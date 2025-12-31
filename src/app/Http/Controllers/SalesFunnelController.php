<?php

namespace App\Http\Controllers;

use App\Models\ContactList;
use App\Models\ExternalPage;
use App\Models\SalesFunnel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SalesFunnelController extends Controller
{
    /**
     * Get all sales funnels for the authenticated user.
     */
    public function index(): JsonResponse
    {
        $funnels = SalesFunnel::forUser(Auth::id())
            ->with(['targetList:id,name', 'thankYouPage:id,name'])
            ->withCount(['stripeProducts', 'polarProducts'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'funnels' => $funnels,
        ]);
    }

    /**
     * Store a new sales funnel.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'thank_you_page_id' => ['nullable', 'exists:external_pages,id'],
            'thank_you_url' => ['nullable', 'url', 'max:500'],
            'target_list_id' => ['nullable', 'exists:contact_lists,id'],
            'purchase_tag' => ['nullable', 'string', 'max:100'],
            'embed_settings' => ['nullable', 'array'],
            'embed_settings.button_text' => ['nullable', 'string', 'max:50'],
            'embed_settings.button_color' => ['nullable', 'string', 'max:20'],
            'embed_settings.button_text_color' => ['nullable', 'string', 'max:20'],
            'embed_settings.button_style' => ['nullable', 'string', 'in:rounded,square,pill'],
        ]);

        // Verify ownership of external page
        if (isset($validated['thank_you_page_id'])) {
            $page = ExternalPage::where('id', $validated['thank_you_page_id'])
                ->where('user_id', Auth::id())
                ->first();
            if (!$page) {
                return response()->json(['error' => 'Invalid external page'], 422);
            }
        }

        // Verify ownership of contact list
        if (isset($validated['target_list_id'])) {
            $list = ContactList::where('id', $validated['target_list_id'])
                ->where('user_id', Auth::id())
                ->first();
            if (!$list) {
                return response()->json(['error' => 'Invalid contact list'], 422);
            }
        }

        $funnel = SalesFunnel::create([
            ...$validated,
            'user_id' => Auth::id(),
        ]);

        return response()->json([
            'funnel' => $funnel->load(['targetList:id,name', 'thankYouPage:id,name']),
            'message' => __('sales_funnels.created'),
        ], 201);
    }

    /**
     * Update a sales funnel.
     */
    public function update(Request $request, SalesFunnel $salesFunnel): JsonResponse
    {
        $this->authorize('update', $salesFunnel);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'thank_you_page_id' => ['nullable', 'exists:external_pages,id'],
            'thank_you_url' => ['nullable', 'url', 'max:500'],
            'target_list_id' => ['nullable', 'exists:contact_lists,id'],
            'purchase_tag' => ['nullable', 'string', 'max:100'],
            'embed_settings' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        // Verify ownership of external page
        if (isset($validated['thank_you_page_id'])) {
            $page = ExternalPage::where('id', $validated['thank_you_page_id'])
                ->where('user_id', Auth::id())
                ->first();
            if (!$page) {
                return response()->json(['error' => 'Invalid external page'], 422);
            }
        }

        // Verify ownership of contact list
        if (isset($validated['target_list_id'])) {
            $list = ContactList::where('id', $validated['target_list_id'])
                ->where('user_id', Auth::id())
                ->first();
            if (!$list) {
                return response()->json(['error' => 'Invalid contact list'], 422);
            }
        }

        $salesFunnel->update($validated);

        return response()->json([
            'funnel' => $salesFunnel->fresh()->load(['targetList:id,name', 'thankYouPage:id,name']),
            'message' => __('sales_funnels.updated'),
        ]);
    }

    /**
     * Delete a sales funnel.
     */
    public function destroy(SalesFunnel $salesFunnel): JsonResponse
    {
        $this->authorize('delete', $salesFunnel);

        // Detach from products first
        $salesFunnel->stripeProducts()->update(['sales_funnel_id' => null]);
        $salesFunnel->polarProducts()->update(['sales_funnel_id' => null]);

        $salesFunnel->delete();

        return response()->json([
            'message' => __('sales_funnels.deleted'),
        ]);
    }

    /**
     * Generate embed code for a product with sales funnel.
     */
    public function getEmbedCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_type' => ['required', 'in:stripe,polar'],
            'product_id' => ['required', 'integer'],
            'funnel_id' => ['required', 'exists:sales_funnels,id'],
        ]);

        $funnel = SalesFunnel::where('id', $validated['funnel_id'])
            ->where('user_id', Auth::id())
            ->first();

        if (!$funnel) {
            return response()->json(['error' => 'Invalid funnel'], 422);
        }

        $settings = $funnel->embed_settings_with_defaults;
        $checkoutUrl = route('sales-funnel.checkout', [
            'type' => $validated['product_type'],
            'product' => $validated['product_id'],
        ]);

        // Generate HTML embed code
        $buttonStyles = $this->generateButtonStyles($settings);

        $embedCode = <<<HTML
<!-- NetSendo Sales Funnel Button -->
<a href="{$checkoutUrl}"
   style="{$buttonStyles}"
   target="_blank"
   rel="noopener">
   {$settings['button_text']}
</a>
HTML;

        // Generate JavaScript embed code (alternative)
        $jsEmbedCode = <<<HTML
<!-- NetSendo Sales Funnel (JavaScript) -->
<script src="{$this->getAssetUrl()}/sales-funnel.js" defer></script>
<div class="netsendo-buy-button"
     data-product-type="{$validated['product_type']}"
     data-product-id="{$validated['product_id']}"
     data-button-text="{$settings['button_text']}"
     data-button-color="{$settings['button_color']}"
     data-button-text-color="{$settings['button_text_color']}"
     data-button-style="{$settings['button_style']}">
</div>
HTML;

        return response()->json([
            'embed_code' => $embedCode,
            'js_embed_code' => $jsEmbedCode,
            'checkout_url' => $checkoutUrl,
            'settings' => $settings,
        ]);
    }

    /**
     * Get options for creating/editing funnels.
     */
    public function getOptions(): JsonResponse
    {
        $userId = Auth::id();

        return response()->json([
            'external_pages' => ExternalPage::where('user_id', $userId)
                ->select('id', 'name', 'url')
                ->orderBy('name')
                ->get(),
            'contact_lists' => ContactList::where('user_id', $userId)
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
            'default_embed_settings' => SalesFunnel::DEFAULT_EMBED_SETTINGS,
        ]);
    }

    /**
     * Generate inline CSS styles for the button.
     */
    private function generateButtonStyles(array $settings): string
    {
        $borderRadius = match ($settings['button_style']) {
            'pill' => '9999px',
            'square' => '0',
            default => '8px',
        };

        return implode('; ', [
            "display: inline-block",
            "padding: 12px 24px",
            "background-color: {$settings['button_color']}",
            "color: {$settings['button_text_color']}",
            "text-decoration: none",
            "font-weight: 600",
            "font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
            "font-size: 16px",
            "border-radius: {$borderRadius}",
            "transition: opacity 0.2s",
            "cursor: pointer",
        ]);
    }

    /**
     * Get the public asset URL.
     */
    private function getAssetUrl(): string
    {
        return config('app.url');
    }
}
