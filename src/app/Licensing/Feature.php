<?php

namespace App\Licensing;

/**
 * Features of the paid GOLD licence, one case per feature the application checks.
 *
 * Keep this list in step with the GOLD plan sold on netsendo.com (Pricing.tiers.gold):
 * every feature sold as GOLD there is checked through one of these cases, and nothing sold
 * as SILVER may appear here. Check a feature with LicenseService::allows() / ensureAllows(),
 * or on a route with the `licensed:<value>` middleware.
 */
enum Feature: string
{
    /** Campaign Architect beyond the SILVER limit of plans (LicenseService::SILVER_CAMPAIGN_LIMIT). */
    case UnlimitedAiCampaigns = 'unlimited_ai_campaigns';

    /** OpenRouter and Ollama (local models) as AI providers. */
    case AdvancedAi = 'advanced_ai';

    /** A/B tests of email campaigns. */
    case AbTests = 'ab_tests';

    /** Auto-webinars (evergreen) and their chat scenario builder. */
    case AutoWebinars = 'auto_webinars';

    /** Hybrid webinars. */
    case HybridWebinars = 'hybrid_webinars';

    /** The plan that unlocks the feature. */
    public function plan(): string
    {
        return 'GOLD';
    }

    /** Name shown to the user, e.g. in "A/B tests require the GOLD licence". */
    public function label(): string
    {
        return __('license.gating.features.' . $this->value);
    }
}
