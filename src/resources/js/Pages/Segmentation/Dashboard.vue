<script setup>
import { ref, computed } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const props = defineProps({
    tagSegments: Array,
    scoreSegments: Array,
    automationStats: Object,
    recentActivity: Array,
    engagementTrends: Array,
});

const maxTagCount = computed(() =>
    Math.max(...(props.tagSegments?.map((t) => t.count) || [1])),
);
const maxTrendCount = computed(() =>
    Math.max(...(props.engagementTrends?.map((t) => t.executions) || [1])),
);
</script>

<template>
    <Head :title="t('segmentation.title')" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1
                        class="text-2xl font-bold text-slate-900 dark:text-white"
                    >
                        🎯 {{ t("segmentation.title") }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ t("segmentation.subtitle") }}
                    </p>
                </div>
                <Link
                    :href="route('automations.create')"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
                >
                    ➕ {{ t("segmentation.quick_actions.create_rule") }}
                </Link>
            </div>
        </template>

        <!-- Stats Grid -->
        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Total Rules -->
            <div
                class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400"
                    >
                        ⚡
                    </div>
                    <div>
                        <p
                            class="text-2xl font-bold text-slate-900 dark:text-white"
                        >
                            {{ automationStats?.total_rules || 0 }}
                        </p>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{ t("segmentation.stats.total_rules") }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Active Rules -->
            <div
                class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-lg bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400"
                    >
                        ✅
                    </div>
                    <div>
                        <p
                            class="text-2xl font-bold text-slate-900 dark:text-white"
                        >
                            {{ automationStats?.active_rules || 0 }}
                        </p>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{ t("segmentation.stats.active_rules") }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Executions 24h -->
            <div
                class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-lg bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400"
                    >
                        📊
                    </div>
                    <div>
                        <p
                            class="text-2xl font-bold text-slate-900 dark:text-white"
                        >
                            {{ automationStats?.executions_24h || 0 }}
                        </p>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{ t("segmentation.stats.executions_24h") }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Success Rate -->
            <div
                class="rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-700 dark:bg-slate-800"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400"
                    >
                        🎯
                    </div>
                    <div>
                        <p
                            class="text-2xl font-bold text-slate-900 dark:text-white"
                        >
                            {{ automationStats?.success_rate || 100 }}%
                        </p>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{ t("segmentation.stats.success_rate") }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Score Segments -->
            <div
                class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800"
            >
                <h3
                    class="mb-4 text-lg font-semibold text-slate-900 dark:text-white flex items-center gap-2"
                >
                    📈 {{ t("segmentation.score_segments.title") }}
                </h3>
                <div class="space-y-3">
                    <div
                        v-for="segment in scoreSegments"
                        :key="segment.label"
                        class="flex items-center justify-between"
                    >
                        <div class="flex items-center gap-2">
                            <div
                                class="h-3 w-3 rounded-full"
                                :style="{ backgroundColor: segment.color }"
                            ></div>
                            <span
                                class="text-sm text-slate-700 dark:text-slate-300"
                                >{{ segment.label }}</span
                            >
                        </div>
                        <span
                            class="text-sm font-medium text-slate-900 dark:text-white"
                            >{{ segment.count }}</span
                        >
                    </div>
                </div>
                <div
                    class="mt-4 flex items-center justify-between text-xs text-slate-500"
                >
                    <span>0 pkt</span>
                    <span>75+ pkt</span>
                </div>
            </div>

            <!-- Tag Distribution -->
            <div
                class="lg:col-span-2 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800"
            >
                <h3
                    class="mb-4 text-lg font-semibold text-slate-900 dark:text-white flex items-center gap-2"
                >
                    🏷️ {{ t("segmentation.tag_distribution.title") }}
                </h3>
                <div class="space-y-2">
                    <div v-for="tag in tagSegments" :key="tag.id" class="group">
                        <div class="flex items-center justify-between mb-1">
                            <span
                                class="text-sm text-slate-700 dark:text-slate-300 flex items-center gap-2"
                            >
                                <span
                                    class="h-2 w-2 rounded-full"
                                    :style="{ backgroundColor: tag.color }"
                                ></span>
                                {{ tag.name }}
                            </span>
                            <span
                                class="text-sm font-medium text-slate-900 dark:text-white"
                                >{{ tag.count }}</span
                            >
                        </div>
                        <div
                            class="h-1.5 w-full bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden"
                        >
                            <div
                                class="h-full rounded-full transition-all"
                                :style="{
                                    width:
                                        (tag.count / maxTagCount) * 100 + '%',
                                    backgroundColor: tag.color,
                                }"
                            ></div>
                        </div>
                    </div>
                    <p
                        v-if="!tagSegments?.length"
                        class="text-center text-sm text-slate-400 py-4"
                    >
                        {{ t("segmentation.tag_distribution.empty") }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Activity & Trends Row -->
        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <!-- Engagement Trends Chart -->
            <div
                class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800"
            >
                <h3
                    class="mb-4 text-lg font-semibold text-slate-900 dark:text-white flex items-center gap-2"
                >
                    📊 {{ t("segmentation.engagement_trends.subtitle") }}
                </h3>
                <div class="flex items-end gap-2 h-32">
                    <div
                        v-for="day in engagementTrends"
                        :key="day.date"
                        class="flex-1 flex flex-col items-center gap-1"
                    >
                        <div
                            class="w-full bg-indigo-500 rounded-t transition-all"
                            :style="{
                                height:
                                    (day.executions / maxTrendCount) * 100 +
                                    '%',
                                minHeight: day.executions > 0 ? '4px' : '0',
                            }"
                        ></div>
                        <span class="text-xs text-slate-500">{{
                            day.date
                        }}</span>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div
                class="rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800"
            >
                <div class="flex items-center justify-between mb-4">
                    <h3
                        class="text-lg font-semibold text-slate-900 dark:text-white flex items-center gap-2"
                    >
                        🕐 {{ t("segmentation.recent_activity.title") }}
                    </h3>
                    <Link
                        :href="route('automations.index')"
                        class="text-sm text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                    >
                        {{ t("segmentation.recent_activity.view_all") }}
                    </Link>
                </div>
                <div class="space-y-2 max-h-48 overflow-y-auto">
                    <div
                        v-for="activity in recentActivity"
                        :key="activity.id"
                        class="flex items-center justify-between py-2 border-b border-slate-100 dark:border-slate-700 last:border-0"
                    >
                        <div class="flex-1 min-w-0">
                            <p
                                class="text-sm font-medium text-slate-900 dark:text-white truncate"
                            >
                                {{ activity.rule_name }}
                            </p>
                            <p class="text-xs text-slate-500 truncate">
                                {{ activity.subscriber_email }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="text-xs px-2 py-0.5 rounded-full"
                                :class="{
                                    'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400':
                                        activity.status === 'success',
                                    'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400':
                                        activity.status === 'failed',
                                    'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400':
                                        activity.status === 'partial',
                                }"
                                >{{ activity.status }}</span
                            >
                            <span
                                class="text-xs text-slate-400 whitespace-nowrap"
                                >{{ activity.executed_at }}</span
                            >
                        </div>
                    </div>
                    <p
                        v-if="!recentActivity?.length"
                        class="text-center text-sm text-slate-400 py-4"
                    >
                        {{ t("segmentation.recent_activity.empty") }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Top Triggers -->
        <div
            class="mt-6 rounded-xl border border-slate-200 bg-white p-6 dark:border-slate-700 dark:bg-slate-800"
        >
            <h3
                class="mb-4 text-lg font-semibold text-slate-900 dark:text-white flex items-center gap-2"
            >
                🎯 {{ t("segmentation.top_triggers.title") }}
            </h3>
            <div class="flex flex-wrap gap-2">
                <span
                    v-for="trigger in automationStats?.top_triggers"
                    :key="trigger.event"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300"
                >
                    {{ trigger.label }}
                    <span
                        class="bg-indigo-600 text-white text-xs px-1.5 py-0.5 rounded-full"
                        >{{ trigger.count }}</span
                    >
                </span>
                <p
                    v-if="!automationStats?.top_triggers?.length"
                    class="text-slate-400 text-sm"
                >
                    {{ t("segmentation.top_triggers.empty") }}
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
