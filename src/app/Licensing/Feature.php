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

    /** Split A/B steps in funnels (funnels themselves are SILVER). */
    case FunnelAbSplit = 'funnel_ab_split';

    /**
     * NetSendo Brain working without approval: the semi-automatic and autonomous modes, the
     * autonomous cron, goals, the weekly digest, Telegram, web research and voice input.
     * The Brain chat in manual mode is SILVER.
     */
    case BrainAutonomy = 'brain_autonomy';

    /**
     * Semantic search in the Brain knowledge base: entries are split into chunks and embedded,
     * and Brain picks the chunks that match the task at hand. Entries can be longer. On SILVER
     * Brain reads the knowledge base by category; vectors made under GOLD are kept.
     */
    case SemanticKnowledge = 'semantic_knowledge';

    /** AI campaign audits (Campaign Auditor, daily audit) and AI analysis in campaign stats. */
    case CampaignAuditor = 'campaign_auditor';

    /** Deliverability Shield: domain DNS monitoring, alerts, DMARC upgrades, record generators, InboxPassport. */
    case DeliverabilityShield = 'deliverability_shield';

    /**
     * NetSendo Mail Infrastructure: own MTA, IP pools, dedicated IPs, warming, DKIM.
     * Sending through an NMI set up earlier keeps working without GOLD.
     */
    case MailInfrastructure = 'mail_infrastructure';

    /** The segmentation dashboard (AutoTag Pro); audience filters in campaigns are SILVER. */
    case SegmentationDashboard = 'segmentation_dashboard';

    /** More team members than SILVER allows (LicenseService::SILVER_TEAM_MEMBER_LIMIT). */
    case UnlimitedTeamMembers = 'unlimited_team_members';

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
