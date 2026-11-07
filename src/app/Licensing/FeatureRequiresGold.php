<?php

namespace App\Licensing;

use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A GOLD feature was used on an installation without an active GOLD licence.
 *
 * Rendered per caller: the public API and JSON calls get 402 Payment Required with the
 * feature and the licence page, Inertia visits go back with a `gold_required` error and
 * flash (the flash opens the upgrade dialog), and plain page loads land on the licence page.
 */
class FeatureRequiresGold extends RuntimeException implements ShouldntReport
{
    public function __construct(public readonly Feature $feature)
    {
        parent::__construct(__('license.gating.requires_gold', ['feature' => $feature->label()]));
    }

    public function render(Request $request): Response
    {
        // As an error too, so a form's onError runs instead of its onSuccess
        if ($request->header('X-Inertia')) {
            return back()
                ->withErrors(['gold_required' => $this->getMessage()])
                ->with('gold_required', $this->feature->value);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'error' => 'Payment Required',
                'code' => 'gold_required',
                'message' => $this->getMessage(),
                'feature' => $this->feature->value,
                'required_plan' => $this->feature->plan(),
                'upgrade_url' => route('license.index'),
            ], Response::HTTP_PAYMENT_REQUIRED);
        }

        return redirect()->route('license.index')->with('gold_required', $this->feature->value);
    }
}
