<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import axios from "axios";

const { t } = useI18n();

const props = defineProps({
    settings: Object,
});

// --- State ---
const loading = ref(true);
const monitorData = ref(null);
const logsData = ref(null);
const logsLoading = ref(false);
const lastUpdated = ref(null);
const autoRefresh = ref(true);
let refreshInterval = null;

// Cron form
const cronForm = reactive({
    enabled: props.settings?.cron_enabled || false,
    interval: props.settings?.cron_interval_minutes || 60,
});
const isSavingCron = ref(false);
const cronSaved = ref(false);

// Log filters
const logFilters = reactive({
    agent: "",
    status: "",
});
const currentPage = ref(1);

// Active tab
const activeTab = ref("overview"); // 'overview', 'tasks', 'logs'

// --- Intervals ---
const CRON_INTERVALS = [
    { value: 5, label: "5 min" },
    { value: 15, label: "15 min" },
    { value: 30, label: "30 min" },
    { value: 60, label: "1h" },
    { value: 120, label: "2h" },
    { value: 240, label: "4h" },
    { value: 360, label: "6h" },
    { value: 720, label: "12h" },
    { value: 1440, label: "24h" },
];

// --- Fetch Data ---
async function fetchMonitorData() {
    try {
        const { data } = await axios.get("/brain/api/monitor");
        monitorData.value = data;
        lastUpdated.value = new Date();
    } catch (e) {
        console.error("Monitor fetch failed:", e);
    } finally {
        loading.value = false;
    }
}

async function fetchLogs() {
    logsLoading.value = true;
    try {
        const params = { page: currentPage.value };
        if (logFilters.agent) params.agent = logFilters.agent;
        if (logFilters.status) params.status = logFilters.status;
        const { data } = await axios.get("/brain/api/monitor/logs", {
            params,
        });
        logsData.value = data;
    } catch (e) {
        console.error("Logs fetch failed:", e);
    } finally {
        logsLoading.value = false;
    }
}

async function saveCronSettings() {
    isSavingCron.value = true;
    try {
        await axios.put("/brain/api/monitor/cron", {
            cron_enabled: cronForm.enabled,
            cron_interval_minutes: cronForm.interval,
        });
        cronSaved.value = true;
        setTimeout(() => (cronSaved.value = false), 2000);
        fetchMonitorData();
    } catch (e) {
        console.error("Cron save failed:", e);
    } finally {
        isSavingCron.value = false;
    }
}

// --- Computed ---
const brainStatus = computed(() => monitorData.value?.brain || {});
const agents = computed(() => monitorData.value?.agents || []);
const planStats = computed(() => monitorData.value?.plan_stats || {});
const tokensToday = computed(() => monitorData.value?.tokens_today || {});
const cronStatus = computed(() => monitorData.value?.cron || {});

const nextCronRun = computed(() => {
    if (!cronStatus.value.last_run_at || !cronStatus.value.interval_minutes)
        return "—";
    const lastRun = new Date(cronStatus.value.last_run_at);
    const nextRun = new Date(
        lastRun.getTime() + cronStatus.value.interval_minutes * 60000,
    );
    const now = new Date();
    if (nextRun <= now) return t("brain.monitor.soon", "wkrótce");
    const diffMs = nextRun - now;
    const diffMin = Math.floor(diffMs / 60000);
    if (diffMin < 60) return `${diffMin}min`;
    const hours = Math.floor(diffMin / 60);
    const mins = diffMin % 60;
    return mins > 0 ? `${hours}h ${mins}min` : `${hours}h`;
});

const showModelBreakdown = ref(false);

function formatCost(cost) {
    if (!cost || cost === 0) return "$0.00";
    if (cost < 0.01) return "<$0.01";
    return "$" + cost.toFixed(2);
}

// --- Helpers ---
function isRecentActivity(dateStr) {
    if (!dateStr) return false;
    const seconds = Math.floor((new Date() - new Date(dateStr)) / 1000);
    return seconds < 1800; // 30 minutes
}

function timeAgo(dateStr) {
    if (!dateStr) return t("brain.monitor.never", "Nigdy");
    const date = new Date(dateStr);
    const seconds = Math.floor((new Date() - date) / 1000);
    if (seconds < 60) return `${seconds}s`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m`;
    if (seconds < 86400)
        return `${Math.floor(seconds / 3600)}h ${Math.floor((seconds % 3600) / 60)}m`;
    return `${Math.floor(seconds / 86400)}d`;
}

function statusColor(status) {
    const map = {
        success: "bg-green-500",
        completed: "bg-green-500",
        error: "bg-red-500",
        failed: "bg-red-500",
        executing: "bg-blue-500",
        started: "bg-blue-500",
        pending: "bg-amber-500",
        draft: "bg-slate-400",
        idle: "bg-slate-400",
        approved: "bg-cyan-500",
        pending_approval: "bg-amber-500",
    };
    return map[status] || "bg-slate-400";
}

function statusBadgeClass(status) {
    const map = {
        success:
            "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400",
        completed:
            "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400",
        error: "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400",
        failed: "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400",
        executing:
            "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400",
        pending:
            "bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400",
        draft: "bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300",
        approved:
            "bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400",
        pending_approval:
            "bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400",
    };
    return (
        map[status] ||
        "bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300"
    );
}

function formatDate(dateStr) {
    if (!dateStr) return "—";
    return new Date(dateStr).toLocaleString();
}

// --- Lifecycle ---
onMounted(() => {
    fetchMonitorData();
    fetchLogs();
    refreshInterval = setInterval(() => {
        if (autoRefresh.value) {
            fetchMonitorData();
            if (activeTab.value === "logs") fetchLogs();
        }
    }, 10000);
});

onUnmounted(() => {
    if (refreshInterval) clearInterval(refreshInterval);
});

function onTabChange(tab) {
    activeTab.value = tab;
    if (tab === "logs") fetchLogs();
}

function onFilterChange() {
    currentPage.value = 1;
    fetchLogs();
}

function goToPage(page) {
    currentPage.value = page;
    fetchLogs();
}
</script>

<template>
    <Head
        :title="t('brain.monitor.page_title', 'Brain — Monitor Orkiestracji')"
    />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-indigo-600 shadow-lg shadow-violet-500/25"
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
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                            />
                        </svg>
                    </div>
                    <div>
                        <h2
                            class="text-xl font-bold text-gray-800 dark:text-gray-100"
                        >
                            {{
                                t(
                                    "brain.monitor.page_title",
                                    "Brain — Monitor Orkiestracji",
                                )
                            }}
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{
                                t(
                                    "brain.monitor.subtitle",
                                    "Nadzór agentów, zadań i aktywności Mózgu w czasie rzeczywistym",
                                )
                            }}
                        </p>
                    </div>
                </div>
                <!-- Live indicator + Auto-refresh toggle -->
                <div class="flex items-center gap-4">
                    <div
                        class="flex items-center gap-2 rounded-lg bg-slate-100 px-3 py-1.5 dark:bg-slate-700"
                    >
                        <span
                            class="relative flex h-2.5 w-2.5"
                            v-if="autoRefresh"
                        >
                            <span
                                class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"
                            ></span>
                            <span
                                class="relative inline-flex h-2.5 w-2.5 rounded-full bg-green-500"
                            ></span>
                        </span>
                        <span
                            class="inline-flex h-2.5 w-2.5 rounded-full bg-slate-400"
                            v-else
                        ></span>
                        <span
                            class="text-xs font-medium"
                            :class="
                                autoRefresh
                                    ? 'text-green-600 dark:text-green-400'
                                    : 'text-slate-500'
                            "
                        >
                            {{ t("brain.monitor.live", "NA ŻYWO") }}
                        </span>
                    </div>
                    <label class="flex cursor-pointer items-center gap-2">
                        <input
                            type="checkbox"
                            v-model="autoRefresh"
                            class="h-4 w-4 rounded border-gray-300 text-cyan-600 focus:ring-cyan-500"
                        />
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{
                                t(
                                    "brain.monitor.auto_refresh",
                                    "Auto-odświeżanie",
                                )
                            }}
                        </span>
                    </label>
                    <Link
                        :href="route('brain.index')"
                        class="text-sm font-medium text-cyan-600 hover:text-cyan-700 dark:text-cyan-400"
                    >
                        ← {{ t("brain.chat", "Chat AI") }}
                    </Link>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <!-- Loading -->
            <div v-if="loading" class="flex items-center justify-center py-20">
                <svg
                    class="h-10 w-10 animate-spin text-violet-500"
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

            <template v-else>
                <!-- ============ TOP STATS ROW ============ -->
                <div
                    class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <!-- Brain Status -->
                    <div
                        class="overflow-hidden rounded-2xl border bg-white p-5 shadow-sm transition-all dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="flex items-center justify-between">
                            <p
                                class="text-sm font-medium text-gray-500 dark:text-gray-400"
                            >
                                {{
                                    t(
                                        "brain.monitor.brain_status",
                                        "Status Mózgu",
                                    )
                                }}
                            </p>
                            <span class="relative flex h-3 w-3">
                                <span
                                    v-if="brainStatus.is_active"
                                    class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"
                                ></span>
                                <span
                                    class="relative inline-flex h-3 w-3 rounded-full"
                                    :class="
                                        brainStatus.is_active
                                            ? 'bg-green-500'
                                            : 'bg-slate-400'
                                    "
                                ></span>
                            </span>
                        </div>
                        <p
                            class="mt-2 text-2xl font-bold text-gray-900 dark:text-white"
                        >
                            {{
                                brainStatus.is_active
                                    ? t("brain.monitor.active", "Aktywny")
                                    : t("brain.monitor.idle", "Bezczynny")
                            }}
                        </p>
                        <p
                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                        >
                            {{ t("brain.monitor.mode", "Tryb") }}:
                            <span class="font-medium">{{
                                t(
                                    brainStatus.mode_label ||
                                        "brain.mode.semi_auto",
                                )
                            }}</span>
                        </p>
                    </div>

                    <!-- Plans Today -->
                    <div
                        class="overflow-hidden rounded-2xl border bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <p
                            class="text-sm font-medium text-gray-500 dark:text-gray-400"
                        >
                            {{
                                t("brain.monitor.plans_today", "Plany dzisiaj")
                            }}
                        </p>
                        <p
                            class="mt-2 text-2xl font-bold text-gray-900 dark:text-white"
                        >
                            {{ planStats.today || 0 }}
                        </p>
                        <div
                            class="mt-1 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400"
                        >
                            <span class="text-green-500"
                                >✓ {{ planStats.completed || 0 }}</span
                            >
                            <span class="text-amber-500"
                                >⏳ {{ planStats.active || 0 }}</span
                            >
                            <span class="text-red-500"
                                >✕ {{ planStats.failed || 0 }}</span
                            >
                        </div>
                    </div>

                    <!-- Tokens Today -->
                    <div
                        class="overflow-hidden rounded-2xl border bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="flex items-center justify-between">
                            <p
                                class="text-sm font-medium text-gray-500 dark:text-gray-400"
                            >
                                {{
                                    t(
                                        "brain.monitor.tokens_today",
                                        "Tokeny dzisiaj",
                                    )
                                }}
                            </p>
                            <span
                                class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400"
                            >
                                {{ formatCost(tokensToday.cost_usd) }}
                            </span>
                        </div>
                        <p
                            class="mt-2 text-2xl font-bold text-gray-900 dark:text-white"
                        >
                            {{ (tokensToday.total || 0).toLocaleString() }}
                        </p>
                        <div
                            class="mt-1.5 flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400"
                        >
                            <span
                                >⬆
                                {{ t("brain.monitor.tokens_input", "Input") }}:
                                {{
                                    (tokensToday.input || 0).toLocaleString()
                                }}</span
                            >
                            <span
                                >⬇
                                {{
                                    t("brain.monitor.tokens_output", "Output")
                                }}:
                                {{
                                    (tokensToday.output || 0).toLocaleString()
                                }}</span
                            >
                        </div>
                        <button
                            v-if="tokensToday.by_model?.length"
                            @click="showModelBreakdown = !showModelBreakdown"
                            class="mt-2 text-[10px] font-medium text-cyan-600 hover:text-cyan-700 dark:text-cyan-400"
                        >
                            {{ showModelBreakdown ? "▲" : "▼" }}
                            {{
                                t(
                                    "brain.monitor.cost_by_model",
                                    "Koszty wg modelu",
                                )
                            }}
                        </button>
                        <div
                            v-if="
                                showModelBreakdown &&
                                tokensToday.by_model?.length
                            "
                            class="mt-2 space-y-1"
                        >
                            <div
                                v-for="m in tokensToday.by_model"
                                :key="m.model"
                                class="flex items-center justify-between rounded bg-gray-50 px-2 py-1 text-[10px] dark:bg-gray-700/50"
                            >
                                <span
                                    class="font-medium text-gray-700 dark:text-gray-300"
                                    >{{ m.model }}</span
                                >
                                <span class="text-gray-500 dark:text-gray-400">
                                    {{ (m.total || 0).toLocaleString() }} tk ·
                                    {{ formatCost(m.cost_usd) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Last Activity -->
                    <div
                        class="overflow-hidden rounded-2xl border bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <p
                            class="text-sm font-medium text-gray-500 dark:text-gray-400"
                        >
                            {{
                                t(
                                    "brain.monitor.last_activity",
                                    "Ostatnia aktywność",
                                )
                            }}
                        </p>
                        <p
                            class="mt-2 text-2xl font-bold text-gray-900 dark:text-white"
                        >
                            {{ timeAgo(brainStatus.last_activity_at) }}
                        </p>
                        <p
                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                        >
                            {{
                                lastUpdated
                                    ? lastUpdated.toLocaleTimeString()
                                    : ""
                            }}
                        </p>
                    </div>
                </div>

                <!-- ============ TAB NAVIGATION ============ -->
                <div
                    class="mb-6 flex gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-800"
                >
                    <button
                        v-for="tab in [
                            {
                                key: 'overview',
                                label: t('brain.monitor.agents', 'Podagenci'),
                            },
                            {
                                key: 'tasks',
                                label: t(
                                    'brain.monitor.task_list',
                                    'Lista Zadań',
                                ),
                            },
                            {
                                key: 'logs',
                                label: t(
                                    'brain.monitor.execution_logs',
                                    'Logi Wykonania',
                                ),
                            },
                        ]"
                        :key="tab.key"
                        @click="onTabChange(tab.key)"
                        class="flex-1 rounded-lg px-4 py-2 text-sm font-medium transition-all"
                        :class="
                            activeTab === tab.key
                                ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
                                : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                        "
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <!-- ============ OVERVIEW TAB — Agent Grid + Cron ============ -->
                <div v-if="activeTab === 'overview'" class="space-y-6">
                    <!-- Agent Grid -->
                    <div>
                        <h3
                            class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-100"
                        >
                            🤖 {{ t("brain.monitor.agents", "Podagenci") }}
                            <span class="ml-2 text-sm font-normal text-gray-400"
                                >({{ agents.length }})</span
                            >
                        </h3>
                        <div
                            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
                        >
                            <div
                                v-for="agent in agents"
                                :key="agent.name"
                                class="group overflow-hidden rounded-2xl border bg-white p-5 shadow-sm transition-all hover:shadow-md dark:border-gray-700 dark:bg-gray-800"
                            >
                                <div class="flex items-start justify-between">
                                    <div>
                                        <h4
                                            class="text-sm font-semibold text-gray-900 dark:text-white"
                                        >
                                            {{ agent.label }}
                                        </h4>
                                        <p
                                            class="mt-0.5 text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            {{ agent.name }}
                                        </p>
                                    </div>
                                    <span
                                        class="inline-flex h-2.5 w-2.5 rounded-full"
                                        :class="
                                            agent.last_status === 'success' &&
                                            isRecentActivity(
                                                agent.last_activity_at,
                                            )
                                                ? 'bg-green-500'
                                                : agent.last_status ===
                                                        'error' &&
                                                    isRecentActivity(
                                                        agent.last_activity_at,
                                                    )
                                                  ? 'bg-red-500'
                                                  : 'bg-slate-400'
                                        "
                                    ></span>
                                </div>

                                <!-- Stats -->
                                <div class="mt-4 grid grid-cols-2 gap-3">
                                    <div>
                                        <p
                                            class="text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            {{
                                                t(
                                                    "brain.monitor.tasks_today",
                                                    "Dzisiaj",
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="text-lg font-bold text-gray-900 dark:text-white"
                                        >
                                            {{ agent.tasks_today }}
                                        </p>
                                    </div>
                                    <div>
                                        <p
                                            class="text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            {{
                                                t(
                                                    "brain.monitor.success_rate",
                                                    "Skuteczność",
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="text-lg font-bold"
                                            :class="
                                                agent.success_rate === null
                                                    ? 'text-gray-300 dark:text-gray-600'
                                                    : agent.success_rate >= 80
                                                      ? 'text-green-500'
                                                      : agent.success_rate >= 50
                                                        ? 'text-amber-500'
                                                        : 'text-red-500'
                                            "
                                        >
                                            {{
                                                agent.success_rate !== null
                                                    ? agent.success_rate + "%"
                                                    : "—"
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Last action -->
                                <div
                                    class="mt-3 border-t border-gray-100 pt-3 dark:border-gray-700"
                                >
                                    <p
                                        class="text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{
                                            t(
                                                "brain.monitor.last_action",
                                                "Ostatnia akcja",
                                            )
                                        }}:
                                        <span
                                            class="font-medium text-gray-700 dark:text-gray-300"
                                            >{{
                                                agent.last_action || "—"
                                            }}</span
                                        >
                                    </p>
                                    <p
                                        class="mt-0.5 text-xs text-gray-400 dark:text-gray-500"
                                    >
                                        {{ timeAgo(agent.last_activity_at) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cron Settings -->
                    <div
                        class="overflow-hidden rounded-2xl border bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div
                            class="border-b border-gray-100 px-6 py-4 dark:border-gray-700"
                        >
                            <h3
                                class="text-lg font-semibold text-gray-800 dark:text-gray-100"
                            >
                                ⏰
                                {{
                                    t(
                                        "brain.monitor.cron_settings",
                                        "Ustawienia CRON",
                                    )
                                }}
                            </h3>
                            <p
                                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                            >
                                {{
                                    t(
                                        "brain.monitor.cron_desc",
                                        "Skonfiguruj automatyczne uruchamianie Mózgu wg harmonogramu",
                                    )
                                }}
                            </p>
                        </div>
                        <div class="p-6">
                            <div class="flex flex-wrap items-center gap-6">
                                <!-- Toggle -->
                                <label
                                    class="flex cursor-pointer items-center gap-3"
                                >
                                    <div class="relative">
                                        <input
                                            type="checkbox"
                                            v-model="cronForm.enabled"
                                            class="sr-only"
                                        />
                                        <div
                                            class="h-6 w-11 rounded-full transition-colors"
                                            :class="
                                                cronForm.enabled
                                                    ? 'bg-violet-500'
                                                    : 'bg-gray-300 dark:bg-gray-600'
                                            "
                                        ></div>
                                        <div
                                            class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white transition-transform shadow"
                                            :class="
                                                cronForm.enabled
                                                    ? 'translate-x-5'
                                                    : ''
                                            "
                                        ></div>
                                    </div>
                                    <span
                                        class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        {{
                                            t(
                                                "brain.monitor.cron_enabled",
                                                "Auto-uruchamianie",
                                            )
                                        }}
                                    </span>
                                </label>

                                <!-- Interval -->
                                <div
                                    class="flex items-center gap-2"
                                    v-if="cronForm.enabled"
                                >
                                    <label
                                        class="text-sm text-gray-600 dark:text-gray-400"
                                    >
                                        {{
                                            t(
                                                "brain.monitor.cron_interval",
                                                "Interwał",
                                            )
                                        }}:
                                    </label>
                                    <select
                                        v-model="cronForm.interval"
                                        class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 focus:border-violet-500 focus:ring-violet-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                                    >
                                        <option
                                            v-for="opt in CRON_INTERVALS"
                                            :key="opt.value"
                                            :value="opt.value"
                                        >
                                            {{ opt.label }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Save button -->
                                <button
                                    @click="saveCronSettings"
                                    :disabled="isSavingCron"
                                    class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-violet-700 disabled:opacity-50"
                                >
                                    <svg
                                        v-if="isSavingCron"
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
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                        ></path>
                                    </svg>
                                    <svg
                                        v-else-if="cronSaved"
                                        class="h-4 w-4 text-green-300"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M5 13l4 4L19 7"
                                        />
                                    </svg>
                                    {{
                                        cronSaved
                                            ? t("common.saved", "Zapisano")
                                            : t("common.save", "Zapisz")
                                    }}
                                </button>
                            </div>

                            <!-- Cron status info -->
                            <div v-if="cronForm.enabled" class="mt-4 space-y-2">
                                <div
                                    class="flex flex-wrap items-center gap-4 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    <span
                                        class="inline-flex items-center gap-1.5"
                                    >
                                        <span
                                            class="inline-flex h-2 w-2 rounded-full"
                                            :class="
                                                cronStatus.last_run_at
                                                    ? 'bg-green-500'
                                                    : 'bg-amber-500'
                                            "
                                        ></span>
                                        {{
                                            cronStatus.last_run_at
                                                ? t(
                                                      "brain.monitor.cron_connected",
                                                      "Podłączony do schedulera",
                                                  )
                                                : t(
                                                      "brain.monitor.cron_waiting",
                                                      "Oczekuje na pierwszy cykl",
                                                  )
                                        }}
                                    </span>
                                </div>
                                <div
                                    v-if="cronStatus.last_run_at"
                                    class="flex flex-wrap items-center gap-4 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    <span>
                                        {{
                                            t(
                                                "brain.monitor.last_run",
                                                "Ostatnie uruchomienie",
                                            )
                                        }}:
                                        <span class="font-medium">{{
                                            formatDate(cronStatus.last_run_at)
                                        }}</span>
                                        <span class="text-gray-400"
                                            >({{
                                                timeAgo(cronStatus.last_run_at)
                                            }}
                                            {{
                                                t("brain.monitor.ago", "temu")
                                            }})</span
                                        >
                                    </span>
                                    <span>
                                        {{
                                            t(
                                                "brain.monitor.next_run",
                                                "Następne uruchomienie",
                                            )
                                        }}:
                                        <span class="font-medium"
                                            >~{{ nextCronRun }}</span
                                        >
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============ TASKS TAB ============ -->
                <div v-if="activeTab === 'tasks'" class="space-y-6">
                    <!-- Suggested Tasks from Marketing/Sales Skill -->
                    <div
                        class="overflow-hidden rounded-2xl border bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div
                            class="border-b border-gray-100 px-6 py-4 dark:border-gray-700"
                        >
                            <div class="flex items-center justify-between">
                                <h3
                                    class="text-lg font-semibold text-gray-800 dark:text-gray-100"
                                >
                                    🎯
                                    {{
                                        t(
                                            "brain.monitor.suggested_tasks",
                                            "Sugerowane zadania",
                                        )
                                    }}
                                </h3>
                                <span
                                    class="text-xs text-gray-400 dark:text-gray-500"
                                >
                                    {{
                                        t(
                                            "brain.monitor.ai_generated",
                                            "Wygenerowane przez AI",
                                        )
                                    }}
                                </span>
                            </div>
                            <p
                                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                            >
                                {{
                                    t(
                                        "brain.monitor.suggested_tasks_desc",
                                        "Zadania zaproponowane na podstawie analizy Twojego CRM, list kontaktów i historii kampanii",
                                    )
                                }}
                            </p>
                        </div>

                        <div
                            class="divide-y divide-gray-100 dark:divide-gray-700"
                        >
                            <div
                                v-if="!monitorData?.suggested_tasks?.length"
                                class="px-6 py-8 text-center text-gray-400"
                            >
                                <p class="text-lg mb-1">🏁</p>
                                {{
                                    t(
                                        "brain.monitor.no_suggestions",
                                        "Brak sugestii — dodaj kontakty i listy aby otrzymać rekomendacje",
                                    )
                                }}
                            </div>
                            <div
                                v-for="task in monitorData?.suggested_tasks ||
                                []"
                                :key="task.id"
                                class="group flex items-start gap-4 px-6 py-4 transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/50"
                            >
                                <span class="mt-0.5 text-xl flex-shrink-0">{{
                                    task.icon
                                }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <p
                                            class="text-sm font-semibold text-gray-900 dark:text-white"
                                        >
                                            {{ task.title }}
                                        </p>
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium"
                                            :class="
                                                task.priority === 'high'
                                                    ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
                                                    : task.priority === 'medium'
                                                      ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                                                      : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300'
                                            "
                                        >
                                            {{
                                                task.priority === "high"
                                                    ? t(
                                                          "brain.monitor.priority_high",
                                                          "Wysoki",
                                                      )
                                                    : task.priority === "medium"
                                                      ? t(
                                                            "brain.monitor.priority_medium",
                                                            "Średni",
                                                        )
                                                      : t(
                                                            "brain.monitor.priority_low",
                                                            "Niski",
                                                        )
                                            }}
                                        </span>
                                    </div>
                                    <p
                                        class="mt-1 text-xs text-gray-500 dark:text-gray-400 line-clamp-2"
                                    >
                                        {{ task.description }}
                                    </p>
                                    <span
                                        class="mt-1 inline-flex rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500 dark:bg-slate-700 dark:text-slate-400"
                                    >
                                        {{ task.category }}
                                    </span>
                                </div>
                                <Link
                                    :href="
                                        route('brain.index') +
                                        '?action=' +
                                        encodeURIComponent(task.action)
                                    "
                                    class="flex-shrink-0 rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-medium text-white opacity-0 transition-all hover:bg-violet-700 group-hover:opacity-100"
                                >
                                    {{ t("brain.monitor.execute", "Wykonaj") }}
                                    →
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Existing Plans -->
                    <div
                        class="overflow-hidden rounded-2xl border bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div
                            class="border-b border-gray-100 px-6 py-4 dark:border-gray-700"
                        >
                            <h3
                                class="text-lg font-semibold text-gray-800 dark:text-gray-100"
                            >
                                📋
                                {{
                                    t(
                                        "brain.monitor.executed_plans",
                                        "Wykonane plany",
                                    )
                                }}
                            </h3>
                            <div class="mt-2 flex gap-3 text-sm">
                                <span
                                    class="rounded-full bg-slate-100 px-3 py-1 text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                                >
                                    {{ t("common.all", "Wszystko") }}:
                                    {{ planStats.total || 0 }}
                                </span>
                                <span
                                    class="rounded-full bg-green-100 px-3 py-1 text-green-700 dark:bg-green-900/30 dark:text-green-400"
                                >
                                    ✓ {{ planStats.completed || 0 }}
                                </span>
                                <span
                                    class="rounded-full bg-amber-100 px-3 py-1 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400"
                                >
                                    ⏳ {{ planStats.pending || 0 }}
                                </span>
                                <span
                                    class="rounded-full bg-red-100 px-3 py-1 text-red-700 dark:bg-red-900/30 dark:text-red-400"
                                >
                                    ✕ {{ planStats.failed || 0 }}
                                </span>
                            </div>
                        </div>

                        <div
                            class="divide-y divide-gray-100 dark:divide-gray-700"
                        >
                            <div
                                v-if="!monitorData?.recent_activity?.length"
                                class="px-6 py-8 text-center text-gray-400"
                            >
                                {{ t("brain.monitor.no_tasks", "Brak zadań") }}
                            </div>
                            <div
                                v-for="activity in monitorData?.recent_activity ||
                                []"
                                :key="activity.id"
                                class="flex items-center gap-4 px-6 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50"
                            >
                                <span
                                    class="inline-flex h-2 w-2 rounded-full"
                                    :class="statusColor(activity.status)"
                                ></span>
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="truncate text-sm font-medium text-gray-900 dark:text-white"
                                    >
                                        {{ activity.event_type }}
                                        <span
                                            v-if="activity.agent_name"
                                            class="ml-1 text-xs text-gray-500"
                                            >({{ activity.agent_name }})</span
                                        >
                                    </p>
                                </div>
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="statusBadgeClass(activity.status)"
                                >
                                    {{ activity.status }}
                                </span>
                                <span
                                    class="whitespace-nowrap text-xs text-gray-400 dark:text-gray-500"
                                >
                                    {{ timeAgo(activity.created_at) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============ LOGS TAB ============ -->
                <div v-if="activeTab === 'logs'">
                    <div
                        class="overflow-hidden rounded-2xl border bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <!-- Filters -->
                        <div
                            class="flex flex-wrap items-center gap-3 border-b border-gray-100 px-6 py-4 dark:border-gray-700"
                        >
                            <h3
                                class="text-lg font-semibold text-gray-800 dark:text-gray-100"
                            >
                                📜
                                {{
                                    t(
                                        "brain.monitor.execution_logs",
                                        "Logi Wykonania",
                                    )
                                }}
                            </h3>

                            <div class="ml-auto flex gap-2">
                                <select
                                    v-model="logFilters.agent"
                                    @change="onFilterChange"
                                    class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs text-gray-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                                >
                                    <option value="">
                                        {{
                                            t(
                                                "brain.monitor.all_agents",
                                                "Wszyscy agenci",
                                            )
                                        }}
                                    </option>
                                    <option
                                        v-for="agent in agents"
                                        :key="agent.name"
                                        :value="agent.name"
                                    >
                                        {{ agent.label }}
                                    </option>
                                </select>

                                <select
                                    v-model="logFilters.status"
                                    @change="onFilterChange"
                                    class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs text-gray-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                                >
                                    <option value="">
                                        {{
                                            t(
                                                "brain.monitor.all_statuses",
                                                "Wszystkie",
                                            )
                                        }}
                                    </option>
                                    <option value="success">✓ Success</option>
                                    <option value="error">✕ Error</option>
                                </select>
                            </div>
                        </div>

                        <!-- Log entries -->
                        <div
                            class="divide-y divide-gray-100 dark:divide-gray-700"
                        >
                            <div
                                v-if="logsLoading"
                                class="flex items-center justify-center py-8"
                            >
                                <svg
                                    class="h-6 w-6 animate-spin text-violet-500"
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
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                    ></path>
                                </svg>
                            </div>
                            <template v-else-if="logsData?.data?.length">
                                <div
                                    v-for="log in logsData.data"
                                    :key="log.id"
                                    class="flex items-center gap-4 px-6 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50"
                                >
                                    <span
                                        class="inline-flex h-2 w-2 flex-shrink-0 rounded-full"
                                        :class="statusColor(log.status)"
                                    ></span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="inline-flex rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                                            >
                                                {{ log.agent_type }}
                                            </span>
                                            <span
                                                class="truncate text-sm font-medium text-gray-900 dark:text-white"
                                            >
                                                {{ log.action }}
                                            </span>
                                        </div>
                                        <p
                                            v-if="log.error_message"
                                            class="mt-0.5 truncate text-xs text-red-500"
                                        >
                                            {{ log.error_message }}
                                        </p>
                                        <p
                                            v-if="log.plan"
                                            class="mt-0.5 text-xs text-gray-400"
                                        >
                                            {{ log.plan.title }}
                                        </p>
                                    </div>
                                    <div
                                        class="flex flex-shrink-0 items-center gap-3"
                                    >
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="
                                                statusBadgeClass(log.status)
                                            "
                                        >
                                            {{ log.status }}
                                        </span>
                                        <span
                                            v-if="log.duration_ms"
                                            class="text-xs text-gray-400"
                                        >
                                            {{ log.duration_ms }}ms
                                        </span>
                                        <span
                                            v-if="
                                                log.tokens_input ||
                                                log.tokens_output
                                            "
                                            class="text-xs text-gray-400"
                                        >
                                            🪙
                                            {{
                                                (log.tokens_input || 0) +
                                                (log.tokens_output || 0)
                                            }}
                                        </span>
                                        <span
                                            class="whitespace-nowrap text-xs text-gray-400"
                                        >
                                            {{ timeAgo(log.created_at) }}
                                        </span>
                                    </div>
                                </div>
                            </template>
                            <div
                                v-else
                                class="px-6 py-8 text-center text-gray-400"
                            >
                                {{ t("brain.monitor.no_logs", "Brak logów") }}
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div
                            v-if="logsData?.last_page > 1"
                            class="flex items-center justify-between border-t border-gray-100 px-6 py-3 dark:border-gray-700"
                        >
                            <p class="text-xs text-gray-500">
                                {{ t("common.showing", "Pokazuje") }}
                                {{ logsData.from }}–{{ logsData.to }}
                                {{ t("common.of", "z") }}
                                {{ logsData.total }}
                            </p>
                            <div class="flex gap-1">
                                <button
                                    v-for="page in logsData.last_page"
                                    :key="page"
                                    @click="goToPage(page)"
                                    class="h-7 w-7 rounded text-xs font-medium transition-colors"
                                    :class="
                                        page === logsData.current_page
                                            ? 'bg-violet-600 text-white'
                                            : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700'
                                    "
                                >
                                    {{ page }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </AuthenticatedLayout>
</template>
