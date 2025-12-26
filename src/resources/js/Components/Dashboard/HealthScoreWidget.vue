<script setup>
import { ref, onMounted } from "vue";
import { Link } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import axios from "axios";

const { t } = useI18n();

const loading = ref(true);
const widgetData = ref(null);

const fetchWidgetData = async () => {
    try {
        const response = await axios.get(
            route("api.campaign-auditor.dashboard-widget")
        );
        widgetData.value = response.data;
    } catch (error) {
        console.error("Failed to fetch auditor widget:", error);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchWidgetData();
});

// Score color computation
const scoreColor = (score) => {
    if (score >= 80) return "text-emerald-500";
    if (score >= 60) return "text-green-500";
    if (score >= 40) return "text-amber-500";
    return "text-red-500";
};

const scoreGradient = (score) => {
    if (score >= 80) return "from-emerald-500 to-green-500";
    if (score >= 60) return "from-green-500 to-lime-500";
    if (score >= 40) return "from-amber-500 to-yellow-500";
    return "from-red-500 to-orange-500";
};

const scoreBg = (score) => {
    if (score >= 80) return "bg-emerald-50 dark:bg-emerald-900/20";
    if (score >= 60) return "bg-green-50 dark:bg-green-900/20";
    if (score >= 40) return "bg-amber-50 dark:bg-amber-900/20";
    return "bg-red-50 dark:bg-red-900/20";
};
</script>

<template>
    <div
        class="overflow-hidden rounded-2xl bg-white shadow-lg dark:bg-gray-800"
    >
        <!-- Header -->
        <div
            class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-700"
        >
            <div class="flex items-center gap-2">
                <div
                    class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-purple-500"
                >
                    <svg
                        class="h-4 w-4 text-white"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                        />
                    </svg>
                </div>
                <h3 class="font-semibold text-gray-900 dark:text-white">
                    {{ $t("campaign_auditor.health_score") }}
                </h3>
            </div>
            <Link
                :href="route('campaign-auditor.index')"
                class="text-sm font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
            >
                {{ $t("campaign_auditor.view_details") }}
            </Link>
        </div>

        <!-- Content -->
        <div class="p-5">
            <!-- Loading -->
            <div v-if="loading" class="flex items-center justify-center py-8">
                <svg
                    class="h-8 w-8 animate-spin text-indigo-500"
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
            </div>

            <!-- No Audit -->
            <div v-else-if="!widgetData?.hasAudit" class="text-center py-6">
                <div
                    class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700"
                >
                    <svg
                        class="h-6 w-6 text-gray-400"
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
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                    {{ $t("campaign_auditor.no_audit_widget") }}
                </p>
                <Link
                    :href="route('campaign-auditor.index')"
                    class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700"
                >
                    {{ $t("campaign_auditor.run_audit") }}
                </Link>
            </div>

            <!-- Audit Data -->
            <div v-else class="space-y-4">
                <!-- Score Display -->
                <div class="flex items-center gap-4">
                    <div class="relative">
                        <svg class="h-20 w-20 -rotate-90 transform">
                            <circle
                                cx="40"
                                cy="40"
                                r="32"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="8"
                                class="text-gray-200 dark:text-gray-700"
                            />
                            <circle
                                cx="40"
                                cy="40"
                                r="32"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="8"
                                stroke-linecap="round"
                                :stroke-dasharray="`${
                                    (widgetData.score / 100) * 201
                                } 201`"
                                :class="scoreColor(widgetData.score)"
                            />
                        </svg>
                        <div
                            class="absolute inset-0 flex items-center justify-center"
                        >
                            <span
                                class="text-xl font-bold"
                                :class="scoreColor(widgetData.score)"
                            >
                                {{ widgetData.score }}
                            </span>
                        </div>
                    </div>
                    <div>
                        <p
                            class="text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                $t(
                                    `campaign_auditor.score.${widgetData.scoreLabel}`
                                )
                            }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ widgetData.lastAuditHuman }}
                        </p>
                    </div>
                </div>

                <!-- Issue Summary -->
                <div class="grid grid-cols-3 gap-2">
                    <div
                        class="rounded-lg bg-red-50 px-3 py-2 text-center dark:bg-red-900/20"
                    >
                        <p
                            class="text-lg font-bold text-red-600 dark:text-red-400"
                        >
                            {{ widgetData.criticalCount }}
                        </p>
                        <p class="text-xs text-red-600/70 dark:text-red-400/70">
                            {{ $t("campaign_auditor.critical") }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg bg-amber-50 px-3 py-2 text-center dark:bg-amber-900/20"
                    >
                        <p
                            class="text-lg font-bold text-amber-600 dark:text-amber-400"
                        >
                            {{ widgetData.warningCount }}
                        </p>
                        <p
                            class="text-xs text-amber-600/70 dark:text-amber-400/70"
                        >
                            {{ $t("campaign_auditor.warnings") }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg bg-blue-50 px-3 py-2 text-center dark:bg-blue-900/20"
                    >
                        <p
                            class="text-lg font-bold text-blue-600 dark:text-blue-400"
                        >
                            {{ widgetData.infoCount }}
                        </p>
                        <p
                            class="text-xs text-blue-600/70 dark:text-blue-400/70"
                        >
                            {{ $t("campaign_auditor.info") }}
                        </p>
                    </div>
                </div>

                <!-- Stale Warning -->
                <div
                    v-if="widgetData.isStale"
                    class="flex items-center gap-2 rounded-lg bg-amber-50 px-3 py-2 dark:bg-amber-900/20"
                >
                    <svg
                        class="h-4 w-4 text-amber-500"
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
                    <p class="text-xs text-amber-600 dark:text-amber-400">
                        {{ $t("campaign_auditor.audit_stale") }}
                    </p>
                </div>

                <!-- AI Summary Short -->
                <div
                    v-if="widgetData.aiSummaryShort"
                    class="mt-3 rounded-lg bg-gradient-to-br from-indigo-50 to-purple-50 p-3 dark:from-indigo-900/20 dark:to-purple-900/20"
                >
                    <div class="flex items-start gap-2">
                        <svg
                            class="h-4 w-4 flex-shrink-0 text-indigo-500 mt-0.5"
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
                        <p
                            class="text-xs text-gray-700 dark:text-gray-300 leading-relaxed"
                        >
                            {{ widgetData.aiSummaryShort }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
