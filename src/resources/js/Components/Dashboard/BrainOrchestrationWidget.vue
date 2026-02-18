<script setup>
import { ref, onMounted, onUnmounted, computed } from "vue";
import { Link } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const loading = ref(true);
const monitorData = ref(null);
let pollInterval = null;

const agentEmojis = {
    campaign: "📧",
    list: "📋",
    message: "✉️",
    crm: "👥",
    analytics: "📊",
    segmentation: "🎯",
};

const statusColors = {
    success:
        "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400",
    failed: "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400",
    running: "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400",
    pending:
        "bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400",
};

const isVisible = computed(() => {
    if (!monitorData.value) return false;
    return (
        monitorData.value.brain?.is_running ||
        monitorData.value.plan_stats?.today > 0 ||
        (monitorData.value.recent_logs &&
            monitorData.value.recent_logs.length > 0)
    );
});

const activeAgentCount = computed(() => {
    if (!monitorData.value?.agents) return 0;
    return monitorData.value.agents.filter((a) => a.tasks_today > 0).length;
});

const fetchData = async () => {
    try {
        const response = await fetch(route("brain.api.monitor"));
        if (response.ok) {
            monitorData.value = await response.json();
        }
    } catch {
        // Non-critical
    } finally {
        loading.value = false;
    }
};

const timeAgo = (dateStr) => {
    if (!dateStr) return "";
    const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
    if (diff < 60) return t("dashboard.brain_activity.just_now", "just now");
    if (diff < 3600)
        return (
            Math.floor(diff / 60) +
            " min " +
            t("dashboard.brain_activity.ago", "ago")
        );
    if (diff < 86400)
        return (
            Math.floor(diff / 3600) +
            "h " +
            t("dashboard.brain_activity.ago", "ago")
        );
    return (
        Math.floor(diff / 86400) +
        "d " +
        t("dashboard.brain_activity.ago", "ago")
    );
};

onMounted(() => {
    fetchData();
    pollInterval = setInterval(fetchData, 10000);
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});
</script>

<template>
    <div v-if="isVisible" class="mb-6">
        <div
            class="overflow-hidden rounded-2xl border border-violet-200/50 bg-white shadow-sm dark:border-violet-800/30 dark:bg-slate-800"
        >
            <!-- Header -->
            <div
                class="flex items-center justify-between border-b border-slate-100 bg-gradient-to-r from-violet-50 to-indigo-50 px-5 py-3 dark:border-slate-700 dark:from-violet-900/20 dark:to-indigo-900/20"
            >
                <div class="flex items-center gap-2.5">
                    <span class="text-lg">🧠</span>
                    <h3
                        class="text-sm font-bold text-slate-900 dark:text-white"
                    >
                        {{
                            t(
                                "dashboard.brain_activity.orchestration_title",
                                "Brain Orchestration",
                            )
                        }}
                    </h3>
                    <!-- LIVE badge -->
                    <span
                        v-if="monitorData?.brain?.is_running"
                        class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-bold text-green-700 dark:bg-green-900/30 dark:text-green-400"
                    >
                        <span class="relative flex h-1.5 w-1.5">
                            <span
                                class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-500 opacity-75"
                            ></span>
                            <span
                                class="relative inline-flex h-1.5 w-1.5 rounded-full bg-green-500"
                            ></span>
                        </span>
                        {{
                            t(
                                "dashboard.brain_activity.orchestration_live",
                                "LIVE",
                            )
                        }}
                    </span>
                </div>
                <Link
                    :href="route('brain.monitor')"
                    class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                >
                    {{
                        t(
                            "dashboard.brain_activity.orchestration_open_monitor",
                            "Open Monitor →",
                        )
                    }}
                </Link>
            </div>

            <!-- Content -->
            <div class="p-5">
                <!-- Current Task Banner -->
                <div
                    v-if="monitorData?.current_task"
                    class="mb-4 rounded-xl bg-gradient-to-r from-violet-500/10 to-indigo-500/10 p-3 dark:from-violet-500/20 dark:to-indigo-500/20"
                >
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="text-sm">
                                {{
                                    agentEmojis[
                                        monitorData.current_task.agent
                                    ] || "⚙️"
                                }}
                            </span>
                            <span
                                class="text-sm font-medium text-slate-800 dark:text-slate-200 truncate"
                            >
                                {{ monitorData.current_task.description }}
                            </span>
                        </div>
                        <span
                            class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 flex-shrink-0"
                        >
                            {{ monitorData.current_task.steps_done }}/{{
                                monitorData.current_task.steps_total
                            }}
                        </span>
                    </div>
                    <div
                        class="h-1.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-600"
                    >
                        <div
                            class="h-full rounded-full bg-gradient-to-r from-violet-500 to-indigo-500 transition-all duration-700"
                            :style="{
                                width: monitorData.current_task.progress + '%',
                            }"
                        ></div>
                    </div>
                </div>

                <!-- Stats row -->
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 mb-4">
                    <div
                        class="rounded-xl bg-slate-50 px-3 py-2.5 text-center dark:bg-slate-700/50"
                    >
                        <div
                            class="text-xl font-bold text-indigo-600 dark:text-indigo-400"
                        >
                            {{ monitorData?.plan_stats?.active || 0 }}
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">
                            {{
                                t(
                                    "dashboard.brain_activity.active_plans",
                                    "Active Plans",
                                )
                            }}
                        </div>
                    </div>
                    <div
                        class="rounded-xl bg-slate-50 px-3 py-2.5 text-center dark:bg-slate-700/50"
                    >
                        <div
                            class="text-xl font-bold text-emerald-600 dark:text-emerald-400"
                        >
                            {{ monitorData?.plan_stats?.today || 0 }}
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">
                            {{
                                t(
                                    "dashboard.brain_activity.completed_today",
                                    "Today",
                                )
                            }}
                        </div>
                    </div>
                    <div
                        class="rounded-xl bg-slate-50 px-3 py-2.5 text-center dark:bg-slate-700/50"
                    >
                        <div
                            class="text-xl font-bold text-cyan-600 dark:text-cyan-400"
                        >
                            {{
                                (
                                    monitorData?.tokens_today?.total || 0
                                ).toLocaleString()
                            }}
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">
                            {{
                                t(
                                    "dashboard.brain_activity.tokens_used",
                                    "Tokens",
                                )
                            }}
                        </div>
                    </div>
                    <div
                        class="rounded-xl bg-slate-50 px-3 py-2.5 text-center dark:bg-slate-700/50"
                    >
                        <div
                            class="text-xl font-bold text-violet-600 dark:text-violet-400"
                        >
                            {{ activeAgentCount }}
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400">
                            {{
                                t(
                                    "dashboard.brain_activity.active_agents",
                                    "Agents",
                                )
                            }}
                        </div>
                    </div>
                </div>

                <!-- Agent Activity Feed -->
                <div
                    v-if="
                        monitorData?.recent_logs &&
                        monitorData.recent_logs.length > 0
                    "
                >
                    <div class="space-y-2">
                        <div
                            v-for="log in monitorData.recent_logs"
                            :key="log.id"
                            class="flex items-center gap-3 rounded-lg px-3 py-2 transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/30"
                        >
                            <span class="text-sm flex-shrink-0">
                                {{ agentEmojis[log.agent] || "⚙️" }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p
                                    class="text-sm text-slate-700 dark:text-slate-300 truncate"
                                >
                                    {{ log.action }}
                                    <span
                                        v-if="log.plan_desc"
                                        class="text-slate-400 dark:text-slate-500"
                                    >
                                        — {{ log.plan_desc }}
                                    </span>
                                </p>
                            </div>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium flex-shrink-0"
                                :class="
                                    statusColors[log.status] ||
                                    statusColors.pending
                                "
                            >
                                {{ log.status }}
                            </span>
                            <span
                                class="text-xs text-slate-400 dark:text-slate-500 whitespace-nowrap flex-shrink-0"
                            >
                                {{ timeAgo(log.created_at) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- No recent logs -->
                <div v-else-if="!loading" class="text-center py-2">
                    <p class="text-sm text-slate-400 dark:text-slate-500">
                        {{
                            t(
                                "dashboard.brain_activity.no_recent_activity",
                                "No recent activity",
                            )
                        }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
