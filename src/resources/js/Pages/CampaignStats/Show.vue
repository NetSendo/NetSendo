<script setup>
import { ref, computed } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { useI18n } from "vue-i18n";
import axios from "axios";

const { t } = useI18n();

const props = defineProps({
    tag: {
        type: Object,
        required: true,
    },
    stats: {
        type: Object,
        required: true,
    },
    messages: {
        type: Array,
        default: () => [],
    },
    trends: {
        type: Object,
        default: () => ({}),
    },
});

// AI Analysis
const isLoadingAnalysis = ref(false);
const aiAnalysis = ref(null);
const analysisError = ref(null);

const generateAiAnalysis = async () => {
    isLoadingAnalysis.value = true;
    analysisError.value = null;

    try {
        const response = await axios.post(route("campaign-stats.ai-analysis", props.tag.id));
        if (response.data.success) {
            aiAnalysis.value = response.data.analysis;
        } else {
            analysisError.value = response.data.error || t("campaign_stats.ai_analysis.error");
        }
    } catch (error) {
        console.error("AI Analysis failed:", error);
        analysisError.value = error.response?.data?.error || t("campaign_stats.ai_analysis.error");
    } finally {
        isLoadingAnalysis.value = false;
    }
};

// Status styling
const getStatusBadge = (status) => {
    const styles = {
        past: "bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300",
        ongoing: "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400",
        future: "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400",
        draft: "bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400",
        sent: "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400",
        scheduled: "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400",
    };
    return styles[status] || "bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400";
};

// Trend indicator
const getTrendIcon = (value) => {
    if (value === null || value === undefined) return "";
    if (value > 0) return "↑";
    if (value < 0) return "↓";
    return "→";
};

const getTrendColor = (value) => {
    if (value === null || value === undefined) return "";
    if (value > 2) return "text-emerald-500";
    if (value < -2) return "text-red-500";
    return "text-gray-500";
};

// Rate color
const getRateColor = (rate, type = 'open') => {
    const thresholds = type === 'open'
        ? { good: 25, ok: 15 }
        : { good: 3, ok: 1 };

    if (rate >= thresholds.good) return "text-emerald-600 dark:text-emerald-400";
    if (rate >= thresholds.ok) return "text-amber-600 dark:text-amber-400";
    return "text-red-500 dark:text-red-400";
};

// Export CSV
const exportCsv = () => {
    window.open(route("campaign-stats.export", props.tag.id), "_blank");
};
</script>

<template>
    <Head :title="tag.name + ' - ' + $t('campaign_stats.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <Link :href="route('campaign-stats.index')" class="text-sm text-gray-500 dark:text-gray-400 hover:underline">
                        ← {{ $t("campaign_stats.back_to_list") }}
                    </Link>
                    <h2 class="mt-1 text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200 flex items-center gap-3">
                        <span
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg"
                            :style="{ backgroundColor: tag.color + '20', color: tag.color }"
                        >
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                        </span>
                        {{ tag.name }}
                    </h2>
                </div>
                <button
                    @click="exportCsv"
                    class="inline-flex items-center gap-2 rounded-lg bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 transition hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-600 dark:hover:bg-gray-700"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    {{ $t("campaign_stats.export") }}
                </button>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- KPI Cards -->
                <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <div class="rounded-xl bg-white p-5 shadow-md dark:bg-gray-800">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t("campaign_stats.kpi.sent") }}</p>
                        <p class="mt-1 text-3xl font-bold text-gray-900 dark:text-white">
                            {{ stats.total_sent.toLocaleString() }}
                        </p>
                        <p class="mt-1 text-xs text-gray-400">
                            {{ stats.messages_count }} {{ $t("campaign_stats.messages") }}
                        </p>
                    </div>

                    <div class="rounded-xl bg-white p-5 shadow-md dark:bg-gray-800">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t("campaign_stats.kpi.open_rate") }}</p>
                        <p :class="['mt-1 text-3xl font-bold', getRateColor(stats.open_rate, 'open')]">
                            {{ stats.open_rate }}%
                        </p>
                        <p v-if="trends.has_comparison" :class="['mt-1 text-xs', getTrendColor(trends.open_rate_trend)]">
                            {{ getTrendIcon(trends.open_rate_trend) }} {{ trends.open_rate_trend > 0 ? '+' : '' }}{{ trends.open_rate_trend }}% vs avg
                        </p>
                        <p class="mt-1 text-xs text-gray-400">
                            {{ stats.total_opens.toLocaleString() }} {{ $t("campaign_stats.kpi.opens") }}
                        </p>
                    </div>

                    <div class="rounded-xl bg-white p-5 shadow-md dark:bg-gray-800">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t("campaign_stats.kpi.click_rate") }}</p>
                        <p :class="['mt-1 text-3xl font-bold', getRateColor(stats.click_rate, 'click')]">
                            {{ stats.click_rate }}%
                        </p>
                        <p v-if="trends.has_comparison" :class="['mt-1 text-xs', getTrendColor(trends.click_rate_trend)]">
                            {{ getTrendIcon(trends.click_rate_trend) }} {{ trends.click_rate_trend > 0 ? '+' : '' }}{{ trends.click_rate_trend }}% vs avg
                        </p>
                        <p class="mt-1 text-xs text-gray-400">
                            {{ stats.total_clicks.toLocaleString() }} {{ $t("campaign_stats.kpi.clicks") }}
                        </p>
                    </div>

                    <div class="rounded-xl bg-white p-5 shadow-md dark:bg-gray-800">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t("campaign_stats.kpi.bounce_rate") }}</p>
                        <p class="mt-1 text-3xl font-bold text-gray-900 dark:text-white">
                            {{ stats.bounce_rate }}%
                        </p>
                        <p class="mt-1 text-xs text-gray-400">
                            {{ stats.total_failed }} {{ $t("campaign_stats.kpi.failed") }}
                        </p>
                    </div>
                </div>

                <!-- AI Analysis Section -->
                <div class="rounded-2xl bg-gradient-to-br from-indigo-50 via-white to-purple-50 p-6 shadow-xl ring-1 ring-indigo-100 dark:from-indigo-900/20 dark:via-gray-800 dark:to-purple-900/20 dark:ring-indigo-800">
                    <div class="flex items-start gap-4">
                        <div class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-lg">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h3 class="mb-3 text-lg font-semibold text-gray-900 dark:text-white">
                                <span class="bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">
                                    {{ $t("campaign_stats.ai_analysis.title") }}
                                </span>
                            </h3>

                            <!-- Not loaded yet -->
                            <div v-if="!aiAnalysis && !isLoadingAnalysis">
                                <p class="mb-4 text-gray-600 dark:text-gray-400">
                                    {{ $t("campaign_stats.ai_analysis.description") }}
                                </p>
                                <button
                                    @click="generateAiAnalysis"
                                    class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-purple-600 px-4 py-2 text-sm font-medium text-white shadow-md transition hover:shadow-lg"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    {{ $t("campaign_stats.ai_analysis.generate") }}
                                </button>
                            </div>

                            <!-- Loading -->
                            <div v-if="isLoadingAnalysis" class="flex items-center gap-3">
                                <svg class="h-5 w-5 animate-spin text-indigo-600" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="text-gray-600 dark:text-gray-400">{{ $t("campaign_stats.ai_analysis.generating") }}</span>
                            </div>

                            <!-- Error -->
                            <div v-if="analysisError" class="rounded-lg bg-red-50 p-4 text-red-700 dark:bg-red-900/20 dark:text-red-400">
                                {{ analysisError }}
                            </div>

                            <!-- Analysis Results -->
                            <div v-if="aiAnalysis" class="space-y-4">
                                <!-- Summary -->
                                <div>
                                    <h4 class="font-medium text-gray-900 dark:text-white">{{ $t("campaign_stats.ai_analysis.summary") }}</h4>
                                    <p class="mt-1 text-gray-600 dark:text-gray-400">{{ aiAnalysis.summary }}</p>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <!-- Strengths -->
                                    <div v-if="aiAnalysis.strengths?.length" class="rounded-lg bg-green-50 p-4 dark:bg-green-900/20">
                                        <h4 class="flex items-center gap-2 font-medium text-green-800 dark:text-green-300">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            {{ $t("campaign_stats.ai_analysis.strengths") }}
                                        </h4>
                                        <ul class="mt-2 space-y-1">
                                            <li v-for="(strength, i) in aiAnalysis.strengths" :key="i" class="text-sm text-green-700 dark:text-green-400">
                                                • {{ strength }}
                                            </li>
                                        </ul>
                                    </div>

                                    <!-- Improvements -->
                                    <div v-if="aiAnalysis.improvements?.length" class="rounded-lg bg-amber-50 p-4 dark:bg-amber-900/20">
                                        <h4 class="flex items-center gap-2 font-medium text-amber-800 dark:text-amber-300">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                            {{ $t("campaign_stats.ai_analysis.improvements") }}
                                        </h4>
                                        <ul class="mt-2 space-y-1">
                                            <li v-for="(improvement, i) in aiAnalysis.improvements" :key="i" class="text-sm text-amber-700 dark:text-amber-400">
                                                • {{ improvement }}
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Recommendations -->
                                <div v-if="aiAnalysis.recommendations?.length" class="rounded-lg bg-indigo-50 p-4 dark:bg-indigo-900/20">
                                    <h4 class="flex items-center gap-2 font-medium text-indigo-800 dark:text-indigo-300">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                                        </svg>
                                        {{ $t("campaign_stats.ai_analysis.recommendations") }}
                                    </h4>
                                    <ul class="mt-2 space-y-1">
                                        <li v-for="(rec, i) in aiAnalysis.recommendations" :key="i" class="text-sm text-indigo-700 dark:text-indigo-400">
                                            {{ i + 1 }}. {{ rec }}
                                        </li>
                                    </ul>
                                </div>

                                <!-- Regenerate button -->
                                <button
                                    @click="generateAiAnalysis"
                                    class="inline-flex items-center gap-2 text-sm text-indigo-600 hover:underline dark:text-indigo-400"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                    {{ $t("campaign_stats.ai_analysis.regenerate") }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Messages Table -->
                <div class="rounded-xl bg-white shadow-md dark:bg-gray-800">
                    <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            {{ $t("campaign_stats.messages_in_campaign") }}
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        {{ $t("campaign_stats.table.subject") }}
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        {{ $t("campaign_stats.table.status") }}
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        {{ $t("campaign_stats.table.sent") }}
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        {{ $t("campaign_stats.table.opens") }}
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        {{ $t("campaign_stats.table.clicks") }}
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                        {{ $t("campaign_stats.table.date") }}
                                    </th>
                                    <th class="px-6 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="message in messages" :key="message.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-6 py-4">
                                        <div class="max-w-xs truncate text-sm font-medium text-gray-900 dark:text-white">
                                            {{ message.subject }}
                                        </div>
                                        <div v-if="message.mailbox" class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ message.mailbox }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span :class="['inline-flex rounded-full px-2 py-1 text-xs font-medium', getStatusBadge(message.status)]">
                                            {{ message.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                        {{ message.sent.toLocaleString() }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900 dark:text-white">{{ message.opens }}</div>
                                        <div class="text-xs text-gray-500">{{ message.open_rate }}%</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900 dark:text-white">{{ message.clicks }}</div>
                                        <div class="text-xs text-gray-500">{{ message.click_rate }}%</div>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                        {{ message.send_at || '—' }}
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <Link :href="route('messages.stats', message.id)" class="text-indigo-600 hover:underline dark:text-indigo-400">
                                            {{ $t("campaign_stats.view_details") }}
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
