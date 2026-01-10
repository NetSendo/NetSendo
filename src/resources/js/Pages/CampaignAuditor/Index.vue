<script setup>
import { ref, computed, onMounted } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";
import { useI18n } from "vue-i18n";
import axios from "axios";

const { t } = useI18n();

const props = defineProps({
    latestAudit: {
        type: Object,
        default: null,
    },
    hasRecentAudit: {
        type: Boolean,
        default: false,
    },
    auditHistory: {
        type: Array,
        default: () => [],
    },
    categories: {
        type: Object,
        default: () => ({}),
    },
    advisorSettings: {
        type: Object,
        default: () => ({
            weekly_improvement_target: 2.0,
            recommendation_count: 5,
            auto_prioritize: true,
            focus_areas: [],
            analysis_language: "en",
        }),
    },
    recommendationTypes: {
        type: Object,
        default: () => ({}),
    },
    effortLevels: {
        type: Object,
        default: () => ({}),
    },
    effectiveness: {
        type: Object,
        default: () => ({}),
    },
    aiIntegrations: {
        type: Array,
        default: () => [],
    },
});

const isLoading = ref(false);
const currentAudit = ref(props.latestAudit);
const activeCategory = ref("all");
const showDetails = ref(null);
const showRecommendationDetails = ref(null);
const showSettingsPanel = ref(false);
const settingsForm = ref({
    weekly_improvement_target: props.advisorSettings.weekly_improvement_target,
    recommendation_count: props.advisorSettings.recommendation_count,
    auto_prioritize: props.advisorSettings.auto_prioritize,
    focus_areas: props.advisorSettings.focus_areas || [],
    analysis_language: props.advisorSettings.analysis_language || "en",
    ai_integration_id: props.advisorSettings.ai_integration_id || null,
});
const isSavingSettings = ref(false);

// Error modal state
const showErrorModal = ref(false);
const errorMessage = ref("");
const isRateLimitError = ref(false);

// Run a new audit
const runAudit = async (type = "full") => {
    isLoading.value = true;
    try {
        const response = await axios.post(route("campaign-auditor.run"), {
            type,
        });
        if (response.data.success) {
            currentAudit.value = response.data.audit;
        } else {
            isRateLimitError.value = false;
            errorMessage.value =
                response.data.error || t("campaign_auditor.audit_failed");
            showErrorModal.value = true;
        }
    } catch (error) {
        console.error("Audit failed:", error);

        // Handle rate limit error (429)
        if (error.response?.status === 429) {
            isRateLimitError.value = true;
            errorMessage.value = t("campaign_auditor.rate_limit_exceeded");
        } else {
            isRateLimitError.value = false;
            errorMessage.value =
                error.response?.data?.error ||
                t("campaign_auditor.audit_failed");
        }
        showErrorModal.value = true;
    } finally {
        isLoading.value = false;
    }
};

// Computed: Filtered issues based on active category
const filteredIssues = computed(() => {
    if (!currentAudit.value?.issues) return [];
    if (activeCategory.value === "all") return currentAudit.value.issues;
    return currentAudit.value.issues.filter(
        (i) => i.category === activeCategory.value
    );
});

// Computed: Issues by severity
const criticalIssues = computed(() => {
    return filteredIssues.value.filter((i) => i.severity === "critical");
});

const warningIssues = computed(() => {
    return filteredIssues.value.filter((i) => i.severity === "warning");
});

const infoIssues = computed(() => {
    return filteredIssues.value.filter((i) => i.severity === "info");
});

// Score color computation
const scoreColor = computed(() => {
    const score = currentAudit.value?.overall_score ?? 0;
    if (score >= 80) return "text-emerald-500";
    if (score >= 60) return "text-green-500";
    if (score >= 40) return "text-amber-500";
    return "text-red-500";
});

const scoreGradient = computed(() => {
    const score = currentAudit.value?.overall_score ?? 0;
    if (score >= 80) return "from-emerald-500 to-green-500";
    if (score >= 60) return "from-green-500 to-lime-500";
    if (score >= 40) return "from-amber-500 to-yellow-500";
    return "from-red-500 to-orange-500";
});

const scoreLabel = computed(() => {
    const score = currentAudit.value?.overall_score ?? 0;
    if (score >= 80) return t("campaign_auditor.score.excellent");
    if (score >= 60) return t("campaign_auditor.score.good");
    if (score >= 40) return t("campaign_auditor.score.needs_attention");
    return t("campaign_auditor.score.critical");
});

// Category icon mapping
const getCategoryIcon = (category) => {
    const icons = {
        frequency: "M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z",
        content:
            "M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z",
        timing: "M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z",
        segmentation:
            "M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z",
        deliverability:
            "M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z",
        revenue:
            "M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z",
        automation:
            "M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z",
    };
    return (
        icons[category] ||
        "M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
    );
};

// Severity badge class
const getSeverityClass = (severity) => {
    return (
        {
            critical:
                "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400",
            warning:
                "bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400",
            info: "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400",
        }[severity] || "bg-gray-100 text-gray-800"
    );
};

// Format currency
const formatCurrency = (value) => {
    if (!value) return "$0";
    return new Intl.NumberFormat("en-US", {
        style: "currency",
        currency: "USD",
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value);
};

// Toggle issue details
const toggleDetails = (issueId) => {
    showDetails.value = showDetails.value === issueId ? null : issueId;
};

// Mark issue as fixed
const markAsFixed = async (issue) => {
    try {
        await axios.post(
            route("campaign-auditor.mark-fixed", { issue: issue.id })
        );
        issue.is_fixed = true;
        issue.fixed_at = new Date().toISOString();
    } catch (error) {
        console.error("Failed to mark as fixed:", error);
    }
};

// Categories for filter - using translation keys
const categoryKeys = [
    "frequency",
    "content",
    "timing",
    "segmentation",
    "deliverability",
    "revenue",
    "automation",
];

const categories = computed(() => {
    const cats = [{ key: "all", label: t("campaign_auditor.all_categories") }];
    categoryKeys.forEach((key) => {
        cats.push({ key, label: t(`campaign_auditor.categories.${key}`) });
    });
    return cats;
});

// Helper to get translated category label
const getCategoryLabel = (category) => {
    return t(`campaign_auditor.categories.${category}`);
};

// Recommendations computed
const recommendations = computed(() => {
    return currentAudit.value?.recommendations || [];
});

const quickWinRecommendations = computed(() => {
    return recommendations.value.filter((r) => r.type === "quick_win");
});

const strategicRecommendations = computed(() => {
    return recommendations.value.filter((r) => r.type === "strategic");
});

const growthRecommendations = computed(() => {
    return recommendations.value.filter((r) => r.type === "growth");
});

const totalExpectedImpact = computed(() => {
    return recommendations.value
        .filter((r) => !r.is_applied)
        .reduce((sum, r) => sum + parseFloat(r.expected_impact || 0), 0)
        .toFixed(1);
});

// Get type color class
const getTypeColor = (type) => {
    return (
        {
            quick_win: "emerald",
            strategic: "blue",
            growth: "purple",
        }[type] || "gray"
    );
};

const getEffortColor = (effort) => {
    return (
        {
            low: "green",
            medium: "amber",
            high: "red",
        }[effort] || "gray"
    );
};

// Apply recommendation
const applyRecommendation = async (recommendation) => {
    try {
        await axios.post(
            route("campaign-auditor.recommendations.apply", {
                recommendation: recommendation.id,
            })
        );
        recommendation.is_applied = true;
        recommendation.applied_at = new Date().toISOString();
    } catch (error) {
        console.error("Failed to apply recommendation:", error);
    }
};

// Toggle recommendation details
const toggleRecommendationDetails = (recId) => {
    showRecommendationDetails.value =
        showRecommendationDetails.value === recId ? null : recId;
};

// Save advisor settings
const saveAdvisorSettings = async () => {
    isSavingSettings.value = true;
    try {
        await axios.put(
            route("campaign-auditor.advisor.settings.update"),
            settingsForm.value
        );
        showSettingsPanel.value = false;
    } catch (error) {
        console.error("Failed to save settings:", error);
    } finally {
        isSavingSettings.value = false;
    }
};
</script>

<template>
    <Head :title="$t('campaign_auditor.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2
                        class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
                    >
                        {{ $t("campaign_auditor.title") }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $t("campaign_auditor.subtitle") }}
                    </p>
                </div>
                <span
                    class="rounded-full bg-gradient-to-r from-indigo-500 to-purple-500 px-3 py-1 text-xs font-semibold text-white shadow-lg"
                >
                    {{ $t("campaign_auditor.ai_badge") }}
                </span>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- No Audit State -->
                <div
                    v-if="!currentAudit"
                    class="rounded-2xl bg-white p-12 text-center shadow-xl dark:bg-gray-800"
                >
                    <div
                        class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-purple-500"
                    >
                        <svg
                            class="h-10 w-10 text-white"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
                            />
                        </svg>
                    </div>
                    <h3
                        class="mb-2 text-2xl font-bold text-gray-900 dark:text-white"
                    >
                        {{ $t("campaign_auditor.no_audit_title") }}
                    </h3>
                    <p class="mb-8 text-gray-500 dark:text-gray-400">
                        {{ $t("campaign_auditor.no_audit_description") }}
                    </p>
                    <button
                        @click="runAudit('full')"
                        :disabled="isLoading"
                        class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 px-8 py-4 text-lg font-semibold text-white shadow-lg transition-all hover:shadow-xl hover:shadow-indigo-500/25 disabled:opacity-50"
                    >
                        <svg
                            v-if="isLoading"
                            class="h-6 w-6 animate-spin"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            ></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>
                        <svg
                            v-else
                            class="h-6 w-6"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                            />
                        </svg>
                        {{
                            isLoading
                                ? $t("campaign_auditor.running")
                                : $t("campaign_auditor.run_first_audit")
                        }}
                    </button>
                </div>

                <!-- Audit Results -->
                <div v-else class="space-y-6">
                    <!-- Score Card -->
                    <div
                        class="overflow-hidden rounded-2xl bg-gradient-to-r p-0.5 shadow-xl"
                        :class="scoreGradient"
                    >
                        <div class="rounded-xl bg-white p-6 dark:bg-gray-800">
                            <div
                                class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between"
                            >
                                <!-- Score Display -->
                                <div class="flex items-center gap-6">
                                    <div class="relative">
                                        <svg
                                            class="h-32 w-32 -rotate-90 transform"
                                        >
                                            <circle
                                                cx="64"
                                                cy="64"
                                                r="56"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="12"
                                                class="text-gray-200 dark:text-gray-700"
                                            />
                                            <circle
                                                cx="64"
                                                cy="64"
                                                r="56"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="12"
                                                stroke-linecap="round"
                                                :stroke-dasharray="`${
                                                    (currentAudit.overall_score /
                                                        100) *
                                                    351.86
                                                } 351.86`"
                                                :class="scoreColor"
                                            />
                                        </svg>
                                        <div
                                            class="absolute inset-0 flex flex-col items-center justify-center"
                                        >
                                            <span
                                                class="text-3xl font-bold"
                                                :class="scoreColor"
                                                >{{
                                                    currentAudit.overall_score
                                                }}</span
                                            >
                                            <span
                                                class="text-xs text-gray-500 dark:text-gray-400"
                                                >/ 100</span
                                            >
                                        </div>
                                    </div>
                                    <div>
                                        <h3
                                            class="text-2xl font-bold text-gray-900 dark:text-white"
                                        >
                                            {{ scoreLabel }}
                                        </h3>
                                        <p
                                            class="text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            {{
                                                $t(
                                                    "campaign_auditor.last_audit"
                                                )
                                            }}:
                                            {{
                                                new Date(
                                                    currentAudit.created_at
                                                ).toLocaleDateString()
                                            }}
                                        </p>
                                        <div
                                            class="mt-2 flex items-center gap-4"
                                        >
                                            <span
                                                class="inline-flex items-center gap-1 text-sm text-gray-700 dark:text-gray-300"
                                            >
                                                <span
                                                    class="h-2 w-2 rounded-full bg-red-500"
                                                ></span>
                                                {{
                                                    currentAudit.critical_count
                                                }}
                                                {{
                                                    $t(
                                                        "campaign_auditor.critical"
                                                    )
                                                }}
                                            </span>
                                            <span
                                                class="inline-flex items-center gap-1 text-sm text-gray-700 dark:text-gray-300"
                                            >
                                                <span
                                                    class="h-2 w-2 rounded-full bg-amber-500"
                                                ></span>
                                                {{ currentAudit.warning_count }}
                                                {{
                                                    $t(
                                                        "campaign_auditor.warnings"
                                                    )
                                                }}
                                            </span>
                                            <span
                                                class="inline-flex items-center gap-1 text-sm text-gray-700 dark:text-gray-300"
                                            >
                                                <span
                                                    class="h-2 w-2 rounded-full bg-blue-500"
                                                ></span>
                                                {{ currentAudit.info_count }}
                                                {{
                                                    $t("campaign_auditor.info")
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Revenue Impact & Actions -->
                                <div class="flex flex-col items-end gap-4">
                                    <div
                                        v-if="
                                            currentAudit.estimated_revenue_loss >
                                            0
                                        "
                                        class="text-right"
                                    >
                                        <p
                                            class="text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            {{
                                                $t(
                                                    "campaign_auditor.revenue_loss"
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="text-3xl font-bold text-red-500"
                                        >
                                            {{
                                                formatCurrency(
                                                    currentAudit.estimated_revenue_loss
                                                )
                                            }}
                                        </p>
                                        <p class="text-xs text-gray-400">
                                            {{
                                                $t(
                                                    "campaign_auditor.estimated_monthly"
                                                )
                                            }}
                                        </p>
                                        <!-- Revenue data source indicator -->
                                        <p
                                            v-if="
                                                currentAudit.summary
                                                    ?.has_revenue_data
                                            "
                                            class="mt-1 flex items-center gap-1 text-xs text-green-600 dark:text-green-400"
                                        >
                                            <svg
                                                class="h-3 w-3"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                                />
                                            </svg>
                                            {{
                                                $t(
                                                    "campaign_auditor.based_on_real_data"
                                                )
                                            }}
                                        </p>
                                        <p
                                            v-else
                                            class="mt-1 flex items-center gap-1 text-xs text-gray-400"
                                        >
                                            <svg
                                                class="h-3 w-3"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                                />
                                            </svg>
                                            {{
                                                $t(
                                                    "campaign_auditor.based_on_estimates"
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <button
                                        @click="runAudit('full')"
                                        :disabled="isLoading"
                                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:opacity-50"
                                    >
                                        <svg
                                            v-if="isLoading"
                                            class="h-4 w-4 animate-spin"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                        >
                                            <circle
                                                class="opacity-25"
                                                cx="12"
                                                cy="12"
                                                r="10"
                                                stroke="currentColor"
                                                stroke-width="4"
                                            ></circle>
                                            <path
                                                class="opacity-75"
                                                fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                            ></path>
                                        </svg>
                                        <svg
                                            v-else
                                            class="h-4 w-4"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                            />
                                        </svg>
                                        {{
                                            isLoading
                                                ? $t("campaign_auditor.running")
                                                : $t(
                                                      "campaign_auditor.run_new_audit"
                                                  )
                                        }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- AI Summary Section -->
                    <div
                        v-if="currentAudit.ai_summary"
                        class="rounded-2xl bg-gradient-to-br from-indigo-50 via-white to-purple-50 p-6 shadow-xl ring-1 ring-indigo-100 dark:from-indigo-900/20 dark:via-gray-800 dark:to-purple-900/20 dark:ring-indigo-800"
                    >
                        <div class="flex items-start gap-4">
                            <div
                                class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-lg"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"
                                    />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3
                                    class="mb-3 text-lg font-semibold text-gray-900 dark:text-white"
                                >
                                    <span
                                        class="bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent"
                                    >
                                        {{
                                            $t(
                                                "campaign_auditor.ai_executive_summary"
                                            )
                                        }}
                                    </span>
                                </h3>
                                <div
                                    class="prose prose-sm prose-indigo max-w-none text-gray-700 dark:prose-invert dark:text-gray-300"
                                >
                                    <p
                                        v-for="(
                                            paragraph, idx
                                        ) in currentAudit.ai_summary.split(
                                            '\n\n'
                                        )"
                                        :key="idx"
                                        class="mb-3 last:mb-0"
                                    >
                                        {{ paragraph }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Category Filter -->
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="cat in categories"
                            :key="cat.key"
                            @click="activeCategory = cat.key"
                            :class="[
                                'rounded-lg px-4 py-2 text-sm font-medium transition',
                                activeCategory === cat.key
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700',
                            ]"
                        >
                            {{ cat.label }}
                        </button>
                    </div>

                    <!-- Critical Issues -->
                    <div v-if="criticalIssues.length > 0" class="space-y-3">
                        <h3
                            class="flex items-center gap-2 text-lg font-semibold text-red-600 dark:text-red-400"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                />
                            </svg>
                            {{
                                $t("campaign_auditor.sections.critical_issues")
                            }}
                            ({{ criticalIssues.length }})
                        </h3>
                        <div
                            v-for="issue in criticalIssues"
                            :key="issue.id"
                            class="overflow-hidden rounded-xl border border-red-200 bg-red-50 dark:border-red-900/50 dark:bg-red-900/20"
                        >
                            <div class="p-4">
                                <div
                                    class="flex items-start justify-between gap-4"
                                >
                                    <div class="flex items-start gap-3">
                                        <div
                                            class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-red-100 dark:bg-red-900/30"
                                        >
                                            <svg
                                                class="h-5 w-5 text-red-600 dark:text-red-400"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    :d="
                                                        getCategoryIcon(
                                                            issue.category
                                                        )
                                                    "
                                                />
                                            </svg>
                                        </div>
                                        <div>
                                            <p
                                                class="font-medium text-gray-900 dark:text-white"
                                            >
                                                {{ issue.message }}
                                            </p>
                                            <p
                                                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                            >
                                                {{
                                                    getCategoryLabel(
                                                        issue.category
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span
                                            v-if="issue.impact_score"
                                            class="text-sm font-medium text-red-600"
                                        >
                                            -{{ issue.impact_score }}%
                                        </span>
                                        <button
                                            @click="toggleDetails(issue.id)"
                                            class="text-gray-400 hover:text-gray-600"
                                        >
                                            <svg
                                                class="h-5 w-5 transition"
                                                :class="{
                                                    'rotate-180':
                                                        showDetails ===
                                                        issue.id,
                                                }"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M19 9l-7 7-7-7"
                                                />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div
                                    v-if="showDetails === issue.id"
                                    class="mt-4 rounded-lg bg-white p-4 dark:bg-gray-800"
                                >
                                    <h4
                                        class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{
                                            $t(
                                                "campaign_auditor.recommendation"
                                            )
                                        }}
                                    </h4>
                                    <p
                                        class="text-sm text-gray-600 dark:text-gray-400"
                                    >
                                        {{ issue.recommendation }}
                                    </p>
                                    <div
                                        v-if="
                                            issue.is_fixable && !issue.is_fixed
                                        "
                                        class="mt-4"
                                    >
                                        <button
                                            @click="markAsFixed(issue)"
                                            class="rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700"
                                        >
                                            {{
                                                $t(
                                                    "campaign_auditor.mark_as_fixed"
                                                )
                                            }}
                                        </button>
                                    </div>
                                    <div
                                        v-if="issue.is_fixed"
                                        class="mt-4 flex items-center gap-2 text-sm text-green-600"
                                    >
                                        <svg
                                            class="h-4 w-4"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M5 13l4 4L19 7"
                                            />
                                        </svg>
                                        {{ $t("campaign_auditor.fixed") }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Warning Issues -->
                    <div v-if="warningIssues.length > 0" class="space-y-3">
                        <h3
                            class="flex items-center gap-2 text-lg font-semibold text-amber-600 dark:text-amber-400"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                />
                            </svg>
                            {{ $t("campaign_auditor.sections.optimization") }}
                            ({{ warningIssues.length }})
                        </h3>
                        <div
                            v-for="issue in warningIssues"
                            :key="issue.id"
                            class="overflow-hidden rounded-xl border border-amber-200 bg-amber-50 dark:border-amber-900/50 dark:bg-amber-900/20"
                        >
                            <div class="p-4">
                                <div
                                    class="flex items-start justify-between gap-4"
                                >
                                    <div class="flex items-start gap-3">
                                        <div
                                            class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/30"
                                        >
                                            <svg
                                                class="h-5 w-5 text-amber-600 dark:text-amber-400"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    :d="
                                                        getCategoryIcon(
                                                            issue.category
                                                        )
                                                    "
                                                />
                                            </svg>
                                        </div>
                                        <div>
                                            <p
                                                class="font-medium text-gray-900 dark:text-white"
                                            >
                                                {{ issue.message }}
                                            </p>
                                            <p
                                                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                            >
                                                {{
                                                    getCategoryLabel(
                                                        issue.category
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span
                                            v-if="issue.impact_score"
                                            class="text-sm font-medium text-amber-600"
                                        >
                                            -{{ issue.impact_score }}%
                                        </span>
                                        <button
                                            @click="toggleDetails(issue.id)"
                                            class="text-gray-400 hover:text-gray-600"
                                        >
                                            <svg
                                                class="h-5 w-5 transition"
                                                :class="{
                                                    'rotate-180':
                                                        showDetails ===
                                                        issue.id,
                                                }"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M19 9l-7 7-7-7"
                                                />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <div
                                    v-if="showDetails === issue.id"
                                    class="mt-4 rounded-lg bg-white p-4 dark:bg-gray-800"
                                >
                                    <h4
                                        class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{
                                            $t(
                                                "campaign_auditor.recommendation"
                                            )
                                        }}
                                    </h4>
                                    <p
                                        class="text-sm text-gray-600 dark:text-gray-400"
                                    >
                                        {{ issue.recommendation }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Info Issues -->
                    <div v-if="infoIssues.length > 0" class="space-y-3">
                        <h3
                            class="flex items-center gap-2 text-lg font-semibold text-blue-600 dark:text-blue-400"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                            {{ $t("campaign_auditor.sections.suggestions") }}
                            ({{ infoIssues.length }})
                        </h3>
                        <div
                            v-for="issue in infoIssues"
                            :key="issue.id"
                            class="overflow-hidden rounded-xl border border-blue-200 bg-blue-50 dark:border-blue-900/50 dark:bg-blue-900/20"
                        >
                            <div class="p-4">
                                <div
                                    class="flex items-start justify-between gap-4"
                                >
                                    <div class="flex items-start gap-3">
                                        <div
                                            class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30"
                                        >
                                            <svg
                                                class="h-5 w-5 text-blue-600 dark:text-blue-400"
                                                fill="none"
                                                stroke="currentColor"
                                                viewBox="0 0 24 24"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    :d="
                                                        getCategoryIcon(
                                                            issue.category
                                                        )
                                                    "
                                                />
                                            </svg>
                                        </div>
                                        <div>
                                            <p
                                                class="font-medium text-gray-900 dark:text-white"
                                            >
                                                {{ issue.message }}
                                            </p>
                                            <p
                                                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                            >
                                                {{
                                                    getCategoryLabel(
                                                        issue.category
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                    <button
                                        @click="toggleDetails(issue.id)"
                                        class="text-gray-400 hover:text-gray-600"
                                    >
                                        <svg
                                            class="h-5 w-5 transition"
                                            :class="{
                                                'rotate-180':
                                                    showDetails === issue.id,
                                            }"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M19 9l-7 7-7-7"
                                            />
                                        </svg>
                                    </button>
                                </div>
                                <div
                                    v-if="showDetails === issue.id"
                                    class="mt-4 rounded-lg bg-white p-4 dark:bg-gray-800"
                                >
                                    <h4
                                        class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{
                                            $t(
                                                "campaign_auditor.recommendation"
                                            )
                                        }}
                                    </h4>
                                    <p
                                        class="text-sm text-gray-600 dark:text-gray-400"
                                    >
                                        {{ issue.recommendation }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- AI Advisor Recommendations Section -->
                    <div v-if="currentAudit" class="mt-8 space-y-6">
                        <!-- Section Header with Settings Toggle -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-purple-500 to-indigo-600"
                                >
                                    <svg
                                        class="h-5 w-5 text-white"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"
                                        />
                                    </svg>
                                </div>
                                <div>
                                    <h2
                                        class="text-xl font-bold text-gray-900 dark:text-white"
                                    >
                                        {{ $t("campaign_advisor.title") }}
                                    </h2>
                                    <p
                                        class="text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        {{ $t("campaign_advisor.subtitle") }}
                                        <span
                                            class="font-medium text-indigo-600"
                                            >+{{ totalExpectedImpact }}%</span
                                        >
                                        {{ $t("campaign_advisor.potential") }}
                                    </p>
                                </div>
                            </div>
                            <button
                                @click="showSettingsPanel = !showSettingsPanel"
                                class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                            >
                                <svg
                                    class="h-4 w-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                    />
                                </svg>
                                {{ $t("campaign_advisor.settings") }}
                            </button>
                        </div>

                        <!-- Settings Panel -->
                        <div
                            v-if="showSettingsPanel"
                            class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
                        >
                            <h3
                                class="mb-4 text-lg font-semibold text-gray-900 dark:text-white"
                            >
                                {{ $t("campaign_advisor.settings_title") }}
                            </h3>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label
                                        class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{
                                            $t("campaign_advisor.weekly_target")
                                        }}
                                    </label>
                                    <div class="flex items-center gap-3">
                                        <input
                                            type="range"
                                            v-model="
                                                settingsForm.weekly_improvement_target
                                            "
                                            min="1"
                                            max="10"
                                            step="0.5"
                                            class="h-2 w-full cursor-pointer appearance-none rounded-lg bg-gray-200 dark:bg-gray-700"
                                        />
                                        <span
                                            class="min-w-[3rem] text-right font-semibold text-indigo-600"
                                        >
                                            {{
                                                settingsForm.weekly_improvement_target
                                            }}%
                                        </span>
                                    </div>
                                </div>
                                <div>
                                    <label
                                        class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{
                                            $t(
                                                "campaign_advisor.recommendation_count"
                                            )
                                        }}
                                    </label>
                                    <select
                                        v-model="
                                            settingsForm.recommendation_count
                                        "
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    >
                                        <option :value="3">3</option>
                                        <option :value="5">5</option>
                                        <option :value="7">7</option>
                                        <option :value="10">10</option>
                                    </select>
                                </div>
                                <div class="col-span-2">
                                    <label
                                        class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{
                                            $t(
                                                "campaign_advisor.analysis_language"
                                            )
                                        }}
                                    </label>
                                    <select
                                        v-model="settingsForm.analysis_language"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    >
                                        <option value="en">English</option>
                                        <option value="pl">Polski</option>
                                        <option value="de">Deutsch</option>
                                        <option value="es">Español</option>
                                        <option value="fr">Français</option>
                                        <option value="it">Italiano</option>
                                        <option value="pt">Português</option>
                                        <option value="nl">Nederlands</option>
                                    </select>
                                </div>
                                <div class="col-span-2">
                                    <label
                                        class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{ $t("campaign_advisor.ai_model") }}
                                    </label>
                                    <select
                                        v-model="settingsForm.ai_integration_id"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    >
                                        <option :value="null">
                                            {{ $t("common.default") }}
                                        </option>
                                        <option
                                            v-for="integration in aiIntegrations"
                                            :key="integration.id"
                                            :value="integration.id"
                                        >
                                            {{ integration.name }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-4 flex justify-end gap-2">
                                <button
                                    @click="showSettingsPanel = false"
                                    class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    {{ $t("common.cancel") }}
                                </button>
                                <button
                                    @click="saveAdvisorSettings"
                                    :disabled="isSavingSettings"
                                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    {{ $t("common.save") }}
                                </button>
                            </div>
                        </div>

                        <!-- Quick Wins -->
                        <div
                            v-if="quickWinRecommendations.length > 0"
                            class="space-y-3"
                        >
                            <h3
                                class="flex items-center gap-2 text-lg font-semibold text-emerald-600 dark:text-emerald-400"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M13 10V3L4 14h7v7l9-11h-7z"
                                    />
                                </svg>
                                {{ $t("campaign_advisor.quick_wins") }}
                                ({{ quickWinRecommendations.length }})
                            </h3>
                            <div
                                v-for="rec in quickWinRecommendations"
                                :key="rec.id"
                                class="overflow-hidden rounded-xl border border-emerald-200 bg-emerald-50 dark:border-emerald-900/50 dark:bg-emerald-900/20"
                            >
                                <div class="p-4">
                                    <div
                                        class="flex items-start justify-between gap-4"
                                    >
                                        <div class="flex-1">
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <p
                                                    class="font-medium text-gray-900 dark:text-white"
                                                >
                                                    {{ rec.title }}
                                                </p>
                                                <span
                                                    v-if="rec.is_applied"
                                                    class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400"
                                                >
                                                    {{
                                                        $t(
                                                            "campaign_advisor.applied"
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                            <p
                                                class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                                            >
                                                {{ rec.description }}
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <div class="text-right">
                                                <span
                                                    class="text-lg font-bold text-emerald-600"
                                                >
                                                    +{{ rec.expected_impact }}%
                                                </span>
                                                <p
                                                    class="text-xs text-gray-500"
                                                >
                                                    {{
                                                        $t(
                                                            "campaign_advisor.expected_impact"
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                            <span
                                                :class="`rounded-full px-2 py-1 text-xs font-medium bg-${getEffortColor(
                                                    rec.effort_level
                                                )}-100 text-${getEffortColor(
                                                    rec.effort_level
                                                )}-800 dark:bg-${getEffortColor(
                                                    rec.effort_level
                                                )}-900/30 dark:text-${getEffortColor(
                                                    rec.effort_level
                                                )}-400`"
                                            >
                                                {{ rec.effort_level }}
                                            </span>
                                        </div>
                                    </div>
                                    <div
                                        v-if="
                                            showRecommendationDetails === rec.id
                                        "
                                        class="mt-4 rounded-lg bg-white p-4 dark:bg-gray-800"
                                    >
                                        <h4
                                            class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-300"
                                        >
                                            {{
                                                $t(
                                                    "campaign_advisor.action_steps"
                                                )
                                            }}
                                        </h4>
                                        <ul
                                            class="list-inside list-disc space-y-1 text-sm text-gray-600 dark:text-gray-400"
                                        >
                                            <li
                                                v-for="(
                                                    step, idx
                                                ) in rec.action_steps"
                                                :key="idx"
                                            >
                                                {{ step }}
                                            </li>
                                        </ul>
                                    </div>
                                    <div class="mt-3 flex items-center gap-2">
                                        <button
                                            @click="
                                                toggleRecommendationDetails(
                                                    rec.id
                                                )
                                            "
                                            class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300"
                                        >
                                            {{
                                                showRecommendationDetails ===
                                                rec.id
                                                    ? $t("common.hide_details")
                                                    : $t("common.show_details")
                                            }}
                                        </button>
                                        <button
                                            v-if="!rec.is_applied"
                                            @click="applyRecommendation(rec)"
                                            class="ml-auto rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700"
                                        >
                                            {{ $t("campaign_advisor.apply") }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Strategic Recommendations -->
                        <div
                            v-if="strategicRecommendations.length > 0"
                            class="space-y-3"
                        >
                            <h3
                                class="flex items-center gap-2 text-lg font-semibold text-blue-600 dark:text-blue-400"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                                    />
                                </svg>
                                {{ $t("campaign_advisor.strategic") }}
                                ({{ strategicRecommendations.length }})
                            </h3>
                            <div
                                v-for="rec in strategicRecommendations"
                                :key="rec.id"
                                class="overflow-hidden rounded-xl border border-blue-200 bg-blue-50 dark:border-blue-900/50 dark:bg-blue-900/20"
                            >
                                <div class="p-4">
                                    <div
                                        class="flex items-start justify-between gap-4"
                                    >
                                        <div class="flex-1">
                                            <p
                                                class="font-medium text-gray-900 dark:text-white"
                                            >
                                                {{ rec.title }}
                                            </p>
                                            <p
                                                class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                                            >
                                                {{ rec.description }}
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <span
                                                class="text-lg font-bold text-blue-600"
                                            >
                                                +{{ rec.expected_impact }}%
                                            </span>
                                        </div>
                                    </div>
                                    <div class="mt-3 flex items-center gap-2">
                                        <button
                                            @click="
                                                toggleRecommendationDetails(
                                                    rec.id
                                                )
                                            "
                                            class="text-sm text-gray-500 hover:text-gray-700"
                                        >
                                            {{
                                                showRecommendationDetails ===
                                                rec.id
                                                    ? $t("common.hide_details")
                                                    : $t("common.show_details")
                                            }}
                                        </button>
                                        <button
                                            v-if="!rec.is_applied"
                                            @click="applyRecommendation(rec)"
                                            class="ml-auto rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700"
                                        >
                                            {{ $t("campaign_advisor.apply") }}
                                        </button>
                                    </div>
                                    <div
                                        v-if="
                                            showRecommendationDetails === rec.id
                                        "
                                        class="mt-4 rounded-lg bg-white p-4 dark:bg-gray-800"
                                    >
                                        <ul
                                            class="list-inside list-disc space-y-1 text-sm text-gray-600 dark:text-gray-400"
                                        >
                                            <li
                                                v-for="(
                                                    step, idx
                                                ) in rec.action_steps"
                                                :key="idx"
                                            >
                                                {{ step }}
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Growth Recommendations -->
                        <div
                            v-if="growthRecommendations.length > 0"
                            class="space-y-3"
                        >
                            <h3
                                class="flex items-center gap-2 text-lg font-semibold text-purple-600 dark:text-purple-400"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
                                    />
                                </svg>
                                {{ $t("campaign_advisor.growth") }}
                                ({{ growthRecommendations.length }})
                            </h3>
                            <div
                                v-for="rec in growthRecommendations"
                                :key="rec.id"
                                class="overflow-hidden rounded-xl border border-purple-200 bg-purple-50 dark:border-purple-900/50 dark:bg-purple-900/20"
                            >
                                <div class="p-4">
                                    <div
                                        class="flex items-start justify-between gap-4"
                                    >
                                        <div class="flex-1">
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <span
                                                    class="rounded bg-purple-200 px-1.5 py-0.5 text-xs font-medium text-purple-700 dark:bg-purple-900/50 dark:text-purple-300"
                                                >
                                                    AI
                                                </span>
                                                <p
                                                    class="font-medium text-gray-900 dark:text-white"
                                                >
                                                    {{ rec.title }}
                                                </p>
                                            </div>
                                            <p
                                                class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                                            >
                                                {{ rec.description }}
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <span
                                                class="text-lg font-bold text-purple-600"
                                            >
                                                +{{ rec.expected_impact }}%
                                            </span>
                                        </div>
                                    </div>
                                    <div class="mt-3 flex items-center gap-2">
                                        <button
                                            @click="
                                                toggleRecommendationDetails(
                                                    rec.id
                                                )
                                            "
                                            class="text-sm text-gray-500 hover:text-gray-700"
                                        >
                                            {{
                                                showRecommendationDetails ===
                                                rec.id
                                                    ? $t("common.hide_details")
                                                    : $t("common.show_details")
                                            }}
                                        </button>
                                        <button
                                            v-if="!rec.is_applied"
                                            @click="applyRecommendation(rec)"
                                            class="ml-auto rounded-lg bg-purple-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-purple-700"
                                        >
                                            {{ $t("campaign_advisor.apply") }}
                                        </button>
                                    </div>
                                    <div
                                        v-if="
                                            showRecommendationDetails ===
                                                rec.id &&
                                            rec.action_steps?.length
                                        "
                                        class="mt-4 rounded-lg bg-white p-4 dark:bg-gray-800"
                                    >
                                        <ul
                                            class="list-inside list-disc space-y-1 text-sm text-gray-600 dark:text-gray-400"
                                        >
                                            <li
                                                v-for="(
                                                    step, idx
                                                ) in rec.action_steps"
                                                :key="idx"
                                            >
                                                {{ step }}
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- No Issues State -->
                    <div
                        v-if="filteredIssues.length === 0"
                        class="rounded-2xl bg-white p-12 text-center shadow-xl dark:bg-gray-800"
                    >
                        <div
                            class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900/30"
                        >
                            <svg
                                class="h-8 w-8 text-green-600 dark:text-green-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M5 13l4 4L19 7"
                                />
                            </svg>
                        </div>
                        <h3
                            class="text-xl font-semibold text-gray-900 dark:text-white"
                        >
                            {{ $t("campaign_auditor.no_issues") }}
                        </h3>
                        <p class="mt-2 text-gray-500 dark:text-gray-400">
                            {{ $t("campaign_auditor.no_issues_description") }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error Modal -->
        <Modal
            :show="showErrorModal"
            @close="showErrorModal = false"
            max-width="md"
        >
            <div class="p-6">
                <div class="flex items-center gap-4">
                    <div
                        :class="[
                            'flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full',
                            isRateLimitError
                                ? 'bg-amber-100 dark:bg-amber-900/30'
                                : 'bg-red-100 dark:bg-red-900/30',
                        ]"
                    >
                        <svg
                            v-if="isRateLimitError"
                            class="h-6 w-6 text-amber-600 dark:text-amber-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                        <svg
                            v-else
                            class="h-6 w-6 text-red-600 dark:text-red-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                            />
                        </svg>
                    </div>
                    <div>
                        <h3
                            :class="[
                                'text-lg font-semibold',
                                isRateLimitError
                                    ? 'text-amber-800 dark:text-amber-200'
                                    : 'text-red-800 dark:text-red-200',
                            ]"
                        >
                            {{
                                isRateLimitError
                                    ? $t("campaign_auditor.rate_limit_title")
                                    : $t("campaign_auditor.error_title")
                            }}
                        </h3>
                        <p
                            class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                        >
                            {{ errorMessage }}
                        </p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button
                        @click="showErrorModal = false"
                        class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
                    >
                        {{ $t("common.close") }}
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
