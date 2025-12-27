<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { Head, useForm, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const props = defineProps({
    settings: Object,
    webhookStats: Object,
});

const activeTab = ref("logs");
const isLoading = ref(false);
const logData = ref({
    lines: [],
    total_lines: 0,
    size: "0 B",
    last_modified: null,
    exists: false,
});
const searchQuery = ref("");
const selectedLevel = ref("");
const showClearModal = ref(false);
const autoRefresh = ref(false);
let refreshInterval = null;

// Webhook logs state
const webhookLogs = ref([]);
const webhookEvents = ref([]);
const webhookLoading = ref(false);
const webhookSearch = ref("");
const webhookStatus = ref("");
const webhookEvent = ref("");
const webhookStats = ref(
    props.webhookStats || {
        total: 0,
        successful: 0,
        failed: 0,
        avg_duration_ms: 0,
    }
);
const expandedWebhook = ref(null);

const retentionOptions = [
    { value: 6, label: "6 " + t("logs.settings.hours", "godzin") },
    { value: 12, label: "12 " + t("logs.settings.hours", "godzin") },
    { value: 24, label: "24 " + t("logs.settings.hours", "godzin") },
    {
        value: 48,
        label:
            "48 " +
            t("logs.settings.hours", "godzin") +
            " (2 " +
            t("logs.settings.days", "dni") +
            ")",
    },
    {
        value: 72,
        label:
            "72 " +
            t("logs.settings.hours", "godzin") +
            " (3 " +
            t("logs.settings.days", "dni") +
            ")",
    },
    {
        value: 168,
        label:
            "168 " +
            t("logs.settings.hours", "godzin") +
            " (7 " +
            t("logs.settings.days", "dni") +
            ")",
    },
];

const form = useForm({
    retention_hours: props.settings?.retention_hours || 24,
});

const levels = [
    { value: "", label: t("logs.levels.all", "Wszystkie") },
    { value: "ERROR", label: "🔴 ERROR" },
    { value: "WARNING", label: "🟡 WARNING" },
    { value: "INFO", label: "🔵 INFO" },
    { value: "DEBUG", label: "⚪ DEBUG" },
];

async function fetchLogs() {
    isLoading.value = true;
    try {
        const params = new URLSearchParams({
            lines: 500,
        });
        if (searchQuery.value) {
            params.append("search", searchQuery.value);
        }
        if (selectedLevel.value) {
            params.append("level", selectedLevel.value);
        }

        const response = await fetch(
            route("settings.logs.content") + "?" + params.toString()
        );
        if (response.ok) {
            logData.value = await response.json();
        }
    } catch (e) {
        console.error("Failed to fetch logs:", e);
    } finally {
        isLoading.value = false;
    }
}

async function fetchWebhookLogs() {
    webhookLoading.value = true;
    try {
        const params = new URLSearchParams({ limit: 100 });
        if (webhookSearch.value) params.append("search", webhookSearch.value);
        if (webhookStatus.value) params.append("status", webhookStatus.value);
        if (webhookEvent.value) params.append("event", webhookEvent.value);

        const response = await fetch(
            route("settings.logs.webhooks") + "?" + params.toString()
        );
        if (response.ok) {
            const data = await response.json();
            webhookLogs.value = data.logs;
            webhookEvents.value = data.events;
            webhookStats.value = data.stats;
        }
    } catch (e) {
        console.error("Failed to fetch webhook logs:", e);
    } finally {
        webhookLoading.value = false;
    }
}

async function clearLogs() {
    try {
        const response = await fetch(route("settings.logs.clear"), {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute("content"),
                "Content-Type": "application/json",
                Accept: "application/json",
            },
        });
        if (response.ok) {
            showClearModal.value = false;
            fetchLogs();
        }
    } catch (e) {
        console.error("Failed to clear logs:", e);
    }
}

function submitSettings() {
    form.post(route("settings.logs.settings.save"), {
        preserveScroll: true,
    });
}

function getLogLineClass(line) {
    if (line.includes(".ERROR:") || line.includes("[ERROR]")) {
        return "text-red-400";
    }
    if (line.includes(".WARNING:") || line.includes("[WARNING]")) {
        return "text-yellow-400";
    }
    if (line.includes(".INFO:") || line.includes("[INFO]")) {
        return "text-blue-400";
    }
    if (line.includes(".DEBUG:") || line.includes("[DEBUG]")) {
        return "text-gray-500";
    }
    return "text-gray-300";
}

function toggleAutoRefresh() {
    autoRefresh.value = !autoRefresh.value;
    if (autoRefresh.value) {
        refreshInterval = setInterval(fetchLogs, 5000);
    } else if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
    }
}

function formatDate(dateStr) {
    if (!dateStr) return "-";
    const date = new Date(dateStr);
    return date.toLocaleString();
}

function toggleWebhookDetails(logId) {
    expandedWebhook.value = expandedWebhook.value === logId ? null : logId;
}

onMounted(() => {
    fetchLogs();
});

onUnmounted(() => {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
});

// Watch for tab changes to load webhook logs
function onTabChange(tab) {
    activeTab.value = tab;
    if (tab === "webhooks") {
        fetchWebhookLogs();
    }
}
</script>

<template>
    <Head :title="t('logs.title', 'Logi systemowe')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2
                    class="text-xl font-semibold text-gray-800 dark:text-gray-100"
                >
                    📋 {{ t("logs.title", "Logi systemowe") }}
                </h2>
                <div class="flex items-center gap-3">
                    <span
                        v-if="logData.exists"
                        class="text-sm text-gray-500 dark:text-gray-400"
                    >
                        {{ logData.size }} • {{ logData.total_lines }}
                        {{ t("logs.lines", "linii") }}
                    </span>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Tabs -->
                <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
                    <nav class="-mb-px flex space-x-8">
                        <button
                            @click="onTabChange('logs')"
                            :class="[
                                'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors',
                                activeTab === 'logs'
                                    ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400',
                            ]"
                        >
                            📋 {{ t("logs.tabs.logs", "Logi Laravel") }}
                        </button>
                        <button
                            @click="onTabChange('webhooks')"
                            :class="[
                                'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors',
                                activeTab === 'webhooks'
                                    ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400',
                            ]"
                        >
                            🔗 {{ t("logs.tabs.webhooks", "Logi Webhooków") }}
                        </button>
                        <button
                            @click="onTabChange('settings')"
                            :class="[
                                'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors',
                                activeTab === 'settings'
                                    ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400',
                            ]"
                        >
                            ⚙️ {{ t("logs.tabs.settings", "Ustawienia") }}
                        </button>
                    </nav>
                </div>

                <!-- Laravel Logs Tab -->
                <div v-if="activeTab === 'logs'" class="space-y-4">
                    <!-- Controls -->
                    <div
                        class="flex flex-wrap items-center gap-4 bg-white dark:bg-gray-800 rounded-lg shadow p-4"
                    >
                        <div class="flex-1 min-w-[200px]">
                            <input
                                v-model="searchQuery"
                                @keyup.enter="fetchLogs"
                                type="text"
                                :placeholder="
                                    t('logs.actions.search', 'Szukaj...')
                                "
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                            />
                        </div>
                        <div>
                            <select
                                v-model="selectedLevel"
                                @change="fetchLogs"
                                class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                            >
                                <option
                                    v-for="level in levels"
                                    :key="level.value"
                                    :value="level.value"
                                >
                                    {{ level.label }}
                                </option>
                            </select>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                @click="fetchLogs"
                                :disabled="isLoading"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 transition-colors"
                            >
                                🔄 {{ t("logs.actions.refresh", "Odśwież") }}
                            </button>
                            <button
                                @click="toggleAutoRefresh"
                                :class="[
                                    'px-4 py-2 rounded-md transition-colors',
                                    autoRefresh
                                        ? 'bg-green-600 text-white hover:bg-green-700'
                                        : 'bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-500',
                                ]"
                            >
                                {{ autoRefresh ? "⏸️" : "▶️" }} Auto
                            </button>
                            <button
                                @click="showClearModal = true"
                                class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors"
                            >
                                🗑️ {{ t("logs.actions.clear", "Wyczyść") }}
                            </button>
                        </div>
                    </div>

                    <!-- Log Content -->
                    <div class="bg-gray-900 rounded-lg shadow overflow-hidden">
                        <div
                            v-if="isLoading"
                            class="p-4 text-center text-gray-400"
                        >
                            <svg
                                class="animate-spin h-8 w-8 mx-auto"
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
                        <div
                            v-else-if="
                                !logData.exists || logData.lines.length === 0
                            "
                            class="p-8 text-center text-gray-400"
                        >
                            {{ t("logs.empty", "Brak logów do wyświetlenia") }}
                        </div>
                        <div
                            v-else
                            class="p-4 font-mono text-sm overflow-x-auto max-h-[600px] overflow-y-auto"
                        >
                            <div
                                v-for="(line, index) in logData.lines"
                                :key="index"
                                :class="[
                                    'py-0.5 border-b border-gray-800 hover:bg-gray-800/50',
                                    getLogLineClass(line),
                                ]"
                            >
                                <span class="text-gray-600 select-none mr-3">{{
                                    String(index + 1).padStart(4, " ")
                                }}</span>
                                {{ line }}
                            </div>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div
                        v-if="logData.exists"
                        class="text-sm text-gray-500 dark:text-gray-400 text-center"
                    >
                        {{ t("logs.last_modified", "Ostatnia modyfikacja") }}:
                        {{ logData.last_modified }}
                    </div>
                </div>

                <!-- Webhook Logs Tab -->
                <div v-if="activeTab === 'webhooks'" class="space-y-4">
                    <!-- Stats Cards -->
                    <div class="grid grid-cols-4 gap-4">
                        <div
                            class="bg-white dark:bg-gray-800 rounded-lg shadow p-4"
                        >
                            <div
                                class="text-2xl font-bold text-gray-900 dark:text-white"
                            >
                                {{ webhookStats.total }}
                            </div>
                            <div
                                class="text-sm text-gray-500 dark:text-gray-400"
                            >
                                {{ t("logs.webhook.total", "Łącznie (24h)") }}
                            </div>
                        </div>
                        <div
                            class="bg-white dark:bg-gray-800 rounded-lg shadow p-4"
                        >
                            <div class="text-2xl font-bold text-green-600">
                                {{ webhookStats.successful }}
                            </div>
                            <div
                                class="text-sm text-gray-500 dark:text-gray-400"
                            >
                                {{ t("logs.webhook.success", "Sukces") }}
                            </div>
                        </div>
                        <div
                            class="bg-white dark:bg-gray-800 rounded-lg shadow p-4"
                        >
                            <div class="text-2xl font-bold text-red-600">
                                {{ webhookStats.failed }}
                            </div>
                            <div
                                class="text-sm text-gray-500 dark:text-gray-400"
                            >
                                {{ t("logs.webhook.failed", "Błędy") }}
                            </div>
                        </div>
                        <div
                            class="bg-white dark:bg-gray-800 rounded-lg shadow p-4"
                        >
                            <div class="text-2xl font-bold text-blue-600">
                                {{ webhookStats.avg_duration_ms }} ms
                            </div>
                            <div
                                class="text-sm text-gray-500 dark:text-gray-400"
                            >
                                {{ t("logs.webhook.avg_duration", "Śr. czas") }}
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div
                        class="flex flex-wrap items-center gap-4 bg-white dark:bg-gray-800 rounded-lg shadow p-4"
                    >
                        <div class="flex-1 min-w-[200px]">
                            <input
                                v-model="webhookSearch"
                                @keyup.enter="fetchWebhookLogs"
                                type="text"
                                :placeholder="
                                    t('logs.actions.search', 'Szukaj...')
                                "
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                            />
                        </div>
                        <div>
                            <select
                                v-model="webhookStatus"
                                @change="fetchWebhookLogs"
                                class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                            >
                                <option value="">
                                    {{
                                        t(
                                            "logs.webhook.all_status",
                                            "Wszystkie statusy"
                                        )
                                    }}
                                </option>
                                <option value="success">
                                    ✅ {{ t("logs.webhook.success", "Sukces") }}
                                </option>
                                <option value="failed">
                                    ❌ {{ t("logs.webhook.failed", "Błąd") }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <select
                                v-model="webhookEvent"
                                @change="fetchWebhookLogs"
                                class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                            >
                                <option value="">
                                    {{
                                        t(
                                            "logs.webhook.all_events",
                                            "Wszystkie eventy"
                                        )
                                    }}
                                </option>
                                <option
                                    v-for="evt in webhookEvents"
                                    :key="evt"
                                    :value="evt"
                                >
                                    {{ evt }}
                                </option>
                            </select>
                        </div>
                        <button
                            @click="fetchWebhookLogs"
                            :disabled="webhookLoading"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 transition-colors"
                        >
                            🔄 {{ t("logs.actions.refresh", "Odśwież") }}
                        </button>
                    </div>

                    <!-- Webhook Logs Table -->
                    <div
                        class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden"
                    >
                        <div
                            v-if="webhookLoading"
                            class="p-8 text-center text-gray-400"
                        >
                            <svg
                                class="animate-spin h-8 w-8 mx-auto"
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
                        <div
                            v-else-if="webhookLogs.length === 0"
                            class="p-8 text-center text-gray-500 dark:text-gray-400"
                        >
                            {{
                                t("logs.webhook.empty", "Brak logów webhooków")
                            }}
                        </div>
                        <table
                            v-else
                            class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"
                        >
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                                    >
                                        {{ t("logs.webhook.date", "Data") }}
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                                    >
                                        {{ t("logs.webhook.event", "Event") }}
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                                    >
                                        URL
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                                    >
                                        {{ t("logs.webhook.status", "Status") }}
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                                    >
                                        {{
                                            t(
                                                "logs.webhook.response_code",
                                                "Kod"
                                            )
                                        }}
                                    </th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                                    >
                                        {{ t("logs.webhook.duration", "Czas") }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700"
                            >
                                <template
                                    v-for="log in webhookLogs"
                                    :key="log.id"
                                >
                                    <tr
                                        @click="toggleWebhookDetails(log.id)"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer"
                                    >
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            {{ formatDate(log.created_at) }}
                                        </td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-sm"
                                        >
                                            <span
                                                class="px-2 py-1 rounded-md text-xs font-medium bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200"
                                                >{{ log.event }}</span
                                            >
                                        </td>
                                        <td
                                            class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100 max-w-[200px] truncate"
                                            :title="log.url"
                                        >
                                            {{ log.url }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span
                                                :class="[
                                                    'px-2 py-1 rounded-full text-xs font-medium',
                                                    log.status === 'success'
                                                        ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200'
                                                        : 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200',
                                                ]"
                                            >
                                                {{
                                                    log.status === "success"
                                                        ? "✅"
                                                        : "❌"
                                                }}
                                                {{ log.status }}
                                            </span>
                                        </td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            {{ log.response_code || "-" }}
                                        </td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            {{ log.duration_ms }} ms
                                        </td>
                                    </tr>
                                    <!-- Expanded details -->
                                    <tr v-if="expandedWebhook === log.id">
                                        <td
                                            colspan="6"
                                            class="px-6 py-4 bg-gray-50 dark:bg-gray-900"
                                        >
                                            <div class="space-y-3">
                                                <div v-if="log.error_message">
                                                    <span
                                                        class="text-xs font-medium text-red-600 dark:text-red-400"
                                                        >{{
                                                            t(
                                                                "logs.webhook.error",
                                                                "Błąd"
                                                            )
                                                        }}:</span
                                                    >
                                                    <pre
                                                        class="mt-1 text-xs text-red-500 bg-red-50 dark:bg-red-900/20 p-2 rounded overflow-x-auto"
                                                        >{{
                                                            log.error_message
                                                        }}</pre
                                                    >
                                                </div>
                                                <div v-if="log.response_body">
                                                    <span
                                                        class="text-xs font-medium text-gray-600 dark:text-gray-400"
                                                        >{{
                                                            t(
                                                                "logs.webhook.response",
                                                                "Odpowiedź"
                                                            )
                                                        }}:</span
                                                    >
                                                    <pre
                                                        class="mt-1 text-xs text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 p-2 rounded overflow-x-auto max-h-32"
                                                        >{{
                                                            log.response_body
                                                        }}</pre
                                                    >
                                                </div>
                                                <div v-if="log.payload">
                                                    <span
                                                        class="text-xs font-medium text-gray-600 dark:text-gray-400"
                                                        >{{
                                                            t(
                                                                "logs.webhook.payload",
                                                                "Payload"
                                                            )
                                                        }}:</span
                                                    >
                                                    <pre
                                                        class="mt-1 text-xs text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 p-2 rounded overflow-x-auto max-h-32"
                                                        >{{
                                                            JSON.stringify(
                                                                log.payload,
                                                                null,
                                                                2
                                                            )
                                                        }}</pre
                                                    >
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Settings Tab -->
                <div v-if="activeTab === 'settings'" class="space-y-6">
                    <form @submit.prevent="submitSettings">
                        <div
                            class="bg-white dark:bg-gray-800 rounded-lg shadow p-6"
                        >
                            <h3
                                class="text-lg font-medium text-gray-900 dark:text-white mb-4"
                            >
                                ⏰
                                {{
                                    t(
                                        "logs.settings.retention_title",
                                        "Czas przechowywania logów"
                                    )
                                }}
                            </h3>
                            <p
                                class="text-sm text-gray-500 dark:text-gray-400 mb-4"
                            >
                                {{
                                    t(
                                        "logs.settings.retention_desc",
                                        "Logi starsze niż ustawiony czas zostaną automatycznie usunięte przez CRON"
                                    )
                                }}
                            </p>

                            <div class="max-w-xs">
                                <select
                                    v-model="form.retention_hours"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                >
                                    <option
                                        v-for="option in retentionOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                            </div>

                            <div class="mt-6 flex justify-end">
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 transition-colors"
                                >
                                    {{
                                        form.processing
                                            ? t(
                                                  "common.saving",
                                                  "Zapisywanie..."
                                              )
                                            : "💾 " + t("common.save", "Zapisz")
                                    }}
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Info Box -->
                    <div
                        class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4"
                    >
                        <div class="flex items-start gap-3">
                            <svg
                                class="h-5 w-5 text-blue-500 mt-0.5"
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
                            <div>
                                <p
                                    class="text-sm font-medium text-blue-800 dark:text-blue-200"
                                >
                                    {{
                                        t(
                                            "logs.settings.cron_info_title",
                                            "Automatyczne czyszczenie"
                                        )
                                    }}
                                </p>
                                <p
                                    class="text-sm text-blue-600 dark:text-blue-300 mt-1"
                                >
                                    {{
                                        t(
                                            "logs.settings.cron_info_desc",
                                            "Komenda logs:clean jest uruchamiana co godzinę przez system CRON. Sprawdza wiek pliku logów i czyści go, jeśli przekracza ustawiony czas retencji."
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Clear Confirmation Modal -->
        <Teleport to="body">
            <div
                v-if="showClearModal"
                class="fixed inset-0 z-50 overflow-y-auto"
            >
                <div class="flex min-h-screen items-center justify-center p-4">
                    <div
                        class="fixed inset-0 bg-black/50"
                        @click="showClearModal = false"
                    ></div>
                    <div
                        class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full p-6"
                    >
                        <h3
                            class="text-lg font-medium text-gray-900 dark:text-white mb-4"
                        >
                            🗑️
                            {{ t("logs.confirm.clear_title", "Wyczyść logi") }}
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-6">
                            {{
                                t(
                                    "logs.confirm.clear_message",
                                    "Czy na pewno chcesz wyczyścić wszystkie logi? Ta operacja jest nieodwracalna."
                                )
                            }}
                        </p>
                        <div class="flex justify-end gap-3">
                            <button
                                @click="showClearModal = false"
                                class="px-4 py-2 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-500"
                            >
                                {{ t("common.cancel", "Anuluj") }}
                            </button>
                            <button
                                @click="clearLogs"
                                class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700"
                            >
                                {{ t("logs.actions.clear", "Wyczyść") }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
