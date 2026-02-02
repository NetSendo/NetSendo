<script setup>
import { ref, watch, computed } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";
import AddToListDropdown from "@/Components/AddToListDropdown.vue";
import BulkAddToListModal from "@/Components/BulkAddToListModal.vue";
import { Head, Link } from "@inertiajs/vue3";
import {
    Chart as ChartJS,
    ArcElement,
    Tooltip,
    Legend,
    CategoryScale,
    LinearScale,
    BarElement,
    Title,
} from "chart.js";
import { Doughnut, Bar } from "vue-chartjs";
import { useI18n } from "vue-i18n";
import { router } from "@inertiajs/vue3";
import ChartDataLabels from "chartjs-plugin-datalabels";

ChartJS.register(
    ArcElement,
    Tooltip,
    Legend,
    CategoryScale,
    LinearScale,
    BarElement,
    BarElement,
    Title,
    ChartDataLabels,
);

const { t } = useI18n();

// Search state
const searchRecipients = ref(
    new URLSearchParams(window.location.search).get("search_recipients") || "",
);
const searchOpens = ref(
    new URLSearchParams(window.location.search).get("search_opens") || "",
);
const searchClicks = ref(
    new URLSearchParams(window.location.search).get("search_clicks") || "",
);

// Debounce timers
let recipientsTimer = null;
let opensTimer = null;
let clicksTimer = null;

// Search handlers with debounce
const handleSearch = (type, value) => {
    const params = new URLSearchParams(window.location.search);

    if (value) {
        params.set(`search_${type}`, value);
    } else {
        params.delete(`search_${type}`);
    }

    // Reset to first page when searching
    params.delete(`${type}_page`);

    router.visit(`${window.location.pathname}?${params.toString()}`, {
        preserveScroll: true,
        preserveState: true,
    });
};

// Debounced search watchers
watch(searchRecipients, (value) => {
    clearTimeout(recipientsTimer);
    recipientsTimer = setTimeout(() => handleSearch("recipients", value), 400);
});

watch(searchOpens, (value) => {
    clearTimeout(opensTimer);
    opensTimer = setTimeout(() => handleSearch("opens", value), 400);
});

watch(searchClicks, (value) => {
    clearTimeout(clicksTimer);
    clicksTimer = setTimeout(() => handleSearch("clicks", value), 400);
});

const props = defineProps({
    message: Object,
    stats: Object,
    queue_stats: Object,
    recent_activity: Object,
    read_time_stats: Object,
    read_time_histogram: Object,
    top_readers: Array,
    recipients: Object,
});

// Status badge configuration
const statusConfig = {
    planned: {
        color: "blue",
        label: "messages.stats.recipients.status.planned",
    },
    queued: {
        color: "yellow",
        label: "messages.stats.recipients.status.queued",
    },
    sent: { color: "green", label: "messages.stats.recipients.status.sent" },
    failed: { color: "red", label: "messages.stats.recipients.status.failed" },
    skipped: {
        color: "gray",
        label: "messages.stats.recipients.status.skipped",
    },
};

const getStatusClass = (status) => {
    const colors = {
        planned:
            "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400",
        queued: "bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400",
        sent: "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400",
        failed: "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400",
        skipped:
            "bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400",
    };
    return colors[status] || "bg-gray-100 text-gray-800";
    return colors[status] || "bg-gray-100 text-gray-800";
};

const handleSort = (type, field) => {
    const params = new URLSearchParams(window.location.search);
    const currentSortBy = params.get(`sort_${type}_by`);
    const currentDir = params.get(`sort_${type}_dir`) || "desc";

    let newDir = "desc";
    if (currentSortBy === field && currentDir === "desc") {
        newDir = "asc";
    }

    params.set(`sort_${type}_by`, field);
    params.set(`sort_${type}_dir`, newDir);

    router.visit(`${window.location.pathname}?${params.toString()}`, {
        preserveScroll: true,
        preserveState: true,
    });
};

const doughnutData = {
    labels: [
        t("messages.stats.charts.labels.opened"),
        t("messages.stats.charts.labels.sent_no_open"),
    ],
    datasets: [
        {
            backgroundColor: ["#10B981", "#E5E7EB"],
            data: [
                props.stats.unique_opens,
                props.stats.sent - props.stats.unique_opens,
            ],
        },
    ],
};

const barData = {
    labels: [
        t("messages.stats.charts.labels.sent"),
        t("messages.stats.charts.labels.unique_opens"),
        t("messages.stats.charts.labels.unique_clicks"),
    ],
    datasets: [
        {
            label: t("messages.stats.charts.labels.counters"),
            backgroundColor: "#4F46E5",
            data: [
                props.stats.sent,
                props.stats.unique_opens,
                props.stats.unique_clicks,
            ],
        },
    ],
};

// Read time histogram chart
const readTimeChartData = {
    labels: props.read_time_histogram?.labels || [],
    datasets: [
        {
            label: t("messages.stats.read_time.sessions"),
            backgroundColor: "#8B5CF6",
            data: props.read_time_histogram?.data || [],
        },
    ],
};

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: "bottom",
        },
        datalabels: {
            color: "#fff",
            font: {
                weight: "bold",
                size: 12,
            },
            formatter: (value, ctx) => {
                if (value === 0) return "";
                return value;
            },
            textShadowBlur: 4,
            textShadowColor: "rgba(0, 0, 0, 0.5)",
        },
    },
};

// Format seconds to human readable
const formatReadTime = (seconds) => {
    if (!seconds) return "0s";
    if (seconds < 60) return `${seconds}s`;
    const min = Math.floor(seconds / 60);
    const sec = seconds % 60;
    return sec > 0 ? `${min}m ${sec}s` : `${min}m`;
};

// Error modal state
const showErrorModal = ref(false);
const selectedError = ref({ email: "", error: "" });
const errorCopied = ref(false);

const openErrorModal = (email, error) => {
    selectedError.value = { email, error };
    showErrorModal.value = true;
    errorCopied.value = false;
};

const closeErrorModal = () => {
    showErrorModal.value = false;
};

const copyError = async () => {
    try {
        await navigator.clipboard.writeText(selectedError.value.error);
        errorCopied.value = true;
        setTimeout(() => {
            errorCopied.value = false;
        }, 2000);
    } catch (err) {
        console.error("Failed to copy:", err);
    }
};

// Resend to failed functionality
const resendingToFailed = ref(false);

const resendToFailed = () => {
    if (resendingToFailed.value) return;

    resendingToFailed.value = true;

    router.post(
        route("messages.resend-to-failed", props.message.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                // Automatically reloaded by Inertia
            },
            onError: (errors) => {
                console.error("Resend to failed error:", errors);
                alert(t("messages.stats.queue.resend_error"));
            },
            onFinish: () => {
                resendingToFailed.value = false;
            },
        },
    );
};

// Bulk selection state
const selectedOpens = ref([]);
const selectedClicks = ref([]);
const showBulkModal = ref(false);
const bulkModalType = ref("opens");

// Check if all visible opens are selected
const allOpensSelected = computed(() => {
    const data = props.recent_activity?.opens?.data || [];
    if (data.length === 0) return false;
    const subscriberIds = data
        .filter((log) => log.subscriber_id)
        .map((log) => log.subscriber_id);
    return (
        subscriberIds.length > 0 &&
        subscriberIds.every((id) => selectedOpens.value.includes(id))
    );
});

// Check if all visible clicks are selected
const allClicksSelected = computed(() => {
    const data = props.recent_activity?.clicks?.data || [];
    if (data.length === 0) return false;
    const subscriberIds = data
        .filter((log) => log.subscriber_id)
        .map((log) => log.subscriber_id);
    return (
        subscriberIds.length > 0 &&
        subscriberIds.every((id) => selectedClicks.value.includes(id))
    );
});

// Toggle select open
const toggleSelectOpen = (subscriberId) => {
    const idx = selectedOpens.value.indexOf(subscriberId);
    if (idx === -1) {
        selectedOpens.value.push(subscriberId);
    } else {
        selectedOpens.value.splice(idx, 1);
    }
};

// Toggle select click
const toggleSelectClick = (subscriberId) => {
    const idx = selectedClicks.value.indexOf(subscriberId);
    if (idx === -1) {
        selectedClicks.value.push(subscriberId);
    } else {
        selectedClicks.value.splice(idx, 1);
    }
};

// Select/deselect all opens (toggle for checkbox)
const toggleAllOpens = () => {
    const data = props.recent_activity?.opens?.data || [];
    const subscriberIds = data
        .filter((log) => log.subscriber_id)
        .map((log) => log.subscriber_id);
    if (allOpensSelected.value) {
        selectedOpens.value = selectedOpens.value.filter(
            (id) => !subscriberIds.includes(id),
        );
    } else {
        const newIds = subscriberIds.filter(
            (id) => !selectedOpens.value.includes(id),
        );
        selectedOpens.value = [...selectedOpens.value, ...newIds];
    }
};

// Select all opens (always adds, never removes)
const selectAllOpens = () => {
    const data = props.recent_activity?.opens?.data || [];
    const subscriberIds = data
        .filter((log) => log.subscriber_id)
        .map((log) => log.subscriber_id);
    const newIds = subscriberIds.filter(
        (id) => !selectedOpens.value.includes(id),
    );
    selectedOpens.value = [...selectedOpens.value, ...newIds];
};

// Select/deselect all clicks (toggle for checkbox)
const toggleAllClicks = () => {
    const data = props.recent_activity?.clicks?.data || [];
    const subscriberIds = data
        .filter((log) => log.subscriber_id)
        .map((log) => log.subscriber_id);
    if (allClicksSelected.value) {
        selectedClicks.value = selectedClicks.value.filter(
            (id) => !subscriberIds.includes(id),
        );
    } else {
        const newIds = subscriberIds.filter(
            (id) => !selectedClicks.value.includes(id),
        );
        selectedClicks.value = [...selectedClicks.value, ...newIds];
    }
};

// Select all clicks (always adds, never removes)
const selectAllClicks = () => {
    const data = props.recent_activity?.clicks?.data || [];
    const subscriberIds = data
        .filter((log) => log.subscriber_id)
        .map((log) => log.subscriber_id);
    const newIds = subscriberIds.filter(
        (id) => !selectedClicks.value.includes(id),
    );
    selectedClicks.value = [...selectedClicks.value, ...newIds];
};

// Clear selections
const clearOpensSelection = () => {
    selectedOpens.value = [];
};

const clearClicksSelection = () => {
    selectedClicks.value = [];
};

// Open bulk modal
const openBulkModal = (type) => {
    bulkModalType.value = type;
    showBulkModal.value = true;
};

// Handle bulk add success
const handleBulkSuccess = ({ listId, count }) => {
    if (bulkModalType.value === "opens") {
        selectedOpens.value = [];
    } else {
        selectedClicks.value = [];
    }
};
</script>

<template>
    <Head :title="$t('messages.stats.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2
                        class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight"
                    >
                        {{
                            $t("messages.stats.header_title", {
                                subject: message.subject,
                            })
                        }}
                    </h2>
                    <div
                        v-if="message.scheduled_at"
                        class="text-sm text-gray-500 dark:text-gray-400 mt-1"
                    >
                        📅 {{ $t("messages.scheduled_for") }}:
                        {{ message.scheduled_at }}
                    </div>
                </div>
                <Link
                    :href="route('messages.index')"
                    class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                >
                    &larr; {{ $t("messages.stats.back_to_list") }}
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- KPI Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div
                        class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow text-center"
                    >
                        <div
                            class="text-3xl font-bold text-gray-900 dark:text-gray-100"
                        >
                            {{ stats.sent }}
                        </div>
                        <div
                            class="text-sm text-gray-500 uppercase tracking-wide"
                        >
                            {{ $t("messages.stats.kpi.sent") }}
                        </div>
                    </div>
                    <div
                        class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow text-center"
                    >
                        <div class="text-3xl font-bold text-green-600">
                            {{ stats.open_rate }}%
                        </div>
                        <div
                            class="text-sm text-gray-500 uppercase tracking-wide"
                        >
                            {{ $t("messages.stats.kpi.open_rate") }}
                        </div>
                        <div class="text-xs text-gray-400 mt-1">
                            {{
                                $t("messages.stats.kpi.unique", {
                                    count: stats.unique_opens,
                                })
                            }}
                        </div>
                    </div>
                    <div
                        class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow text-center"
                    >
                        <div class="text-3xl font-bold text-blue-600">
                            {{ stats.click_rate }}%
                        </div>
                        <div
                            class="text-sm text-gray-500 uppercase tracking-wide"
                        >
                            {{ $t("messages.stats.kpi.click_rate") }}
                        </div>
                        <div class="text-xs text-gray-400 mt-1">
                            {{
                                $t("messages.stats.kpi.unique", {
                                    count: stats.unique_clicks,
                                })
                            }}
                        </div>
                    </div>
                    <div
                        class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow text-center"
                    >
                        <div class="text-3xl font-bold text-purple-600">
                            {{ stats.click_to_open_rate }}%
                        </div>
                        <div
                            class="text-sm text-gray-500 uppercase tracking-wide"
                        >
                            {{ $t("messages.stats.kpi.ctor") }}
                        </div>
                        <div class="text-xs text-gray-400 mt-1">
                            {{ $t("messages.stats.kpi.clicks_opens") }}
                        </div>
                    </div>
                </div>

                <!-- Read Time KPIs -->
                <div
                    v-if="read_time_stats && read_time_stats.total_sessions > 0"
                    class="grid grid-cols-1 md:grid-cols-4 gap-4"
                >
                    <div
                        class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow text-center border-l-4 border-violet-500"
                    >
                        <div class="text-3xl font-bold text-violet-600">
                            {{
                                formatReadTime(read_time_stats.average_seconds)
                            }}
                        </div>
                        <div
                            class="text-sm text-gray-500 uppercase tracking-wide"
                        >
                            {{ $t("messages.stats.read_time.average") }}
                        </div>
                    </div>
                    <div
                        class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow text-center border-l-4 border-violet-500"
                    >
                        <div class="text-3xl font-bold text-violet-600">
                            {{ formatReadTime(read_time_stats.median_seconds) }}
                        </div>
                        <div
                            class="text-sm text-gray-500 uppercase tracking-wide"
                        >
                            {{ $t("messages.stats.read_time.median") }}
                        </div>
                    </div>
                    <div
                        class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow text-center border-l-4 border-violet-500"
                    >
                        <div class="text-3xl font-bold text-violet-600">
                            {{ read_time_stats.total_sessions }}
                        </div>
                        <div
                            class="text-sm text-gray-500 uppercase tracking-wide"
                        >
                            {{ $t("messages.stats.read_time.sessions") }}
                        </div>
                    </div>
                    <div
                        class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow text-center border-l-4 border-violet-500"
                    >
                        <div class="text-3xl font-bold text-violet-600">
                            {{ formatReadTime(read_time_stats.max_seconds) }}
                        </div>
                        <div
                            class="text-sm text-gray-500 uppercase tracking-wide"
                        >
                            {{ $t("messages.stats.read_time.max") }}
                        </div>
                    </div>
                </div>

                <!-- Queue Progress (for autoresponder/queue messages) -->
                <div
                    v-if="
                        queue_stats &&
                        (queue_stats.total > 0 ||
                            message.type === 'autoresponder')
                    "
                    class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden"
                >
                    <div
                        class="px-6 py-4 border-b border-gray-200 dark:border-gray-700"
                    >
                        <div class="flex justify-between items-center">
                            <h3
                                class="text-lg font-semibold text-gray-800 dark:text-gray-200"
                            >
                                {{ $t("messages.stats.queue.title") }}
                            </h3>
                            <span
                                v-if="message.recipients_calculated_at"
                                class="text-xs text-gray-400"
                            >
                                {{ $t("messages.stats.queue.last_sync") }}:
                                {{ message.recipients_calculated_at }}
                            </span>
                        </div>
                    </div>
                    <div class="p-6">
                        <!-- Progress Bar -->
                        <div class="mb-4">
                            <div
                                class="flex h-4 rounded-full overflow-hidden bg-gray-200 dark:bg-gray-700"
                            >
                                <div
                                    class="bg-green-500 transition-all duration-300"
                                    :style="{
                                        width:
                                            queue_stats.total > 0
                                                ? (queue_stats.sent /
                                                      queue_stats.total) *
                                                      100 +
                                                  '%'
                                                : '0%',
                                    }"
                                    :title="
                                        $t('messages.stats.queue.sent') +
                                        ': ' +
                                        queue_stats.sent
                                    "
                                ></div>
                                <div
                                    class="bg-yellow-500 transition-all duration-300"
                                    :style="{
                                        width:
                                            queue_stats.total > 0
                                                ? (queue_stats.queued /
                                                      queue_stats.total) *
                                                      100 +
                                                  '%'
                                                : '0%',
                                    }"
                                    :title="
                                        $t('messages.stats.queue.queued') +
                                        ': ' +
                                        queue_stats.queued
                                    "
                                ></div>
                                <div
                                    class="bg-blue-500 transition-all duration-300"
                                    :style="{
                                        width:
                                            queue_stats.total > 0
                                                ? (queue_stats.planned /
                                                      queue_stats.total) *
                                                      100 +
                                                  '%'
                                                : '0%',
                                    }"
                                    :title="
                                        $t('messages.stats.queue.planned') +
                                        ': ' +
                                        queue_stats.planned
                                    "
                                ></div>
                                <div
                                    class="bg-red-500 transition-all duration-300"
                                    :style="{
                                        width:
                                            queue_stats.total > 0
                                                ? (queue_stats.failed /
                                                      queue_stats.total) *
                                                      100 +
                                                  '%'
                                                : '0%',
                                    }"
                                    :title="
                                        $t('messages.stats.queue.failed') +
                                        ': ' +
                                        queue_stats.failed
                                    "
                                ></div>
                                <div
                                    class="bg-gray-400 transition-all duration-300"
                                    :style="{
                                        width:
                                            queue_stats.total > 0
                                                ? (queue_stats.skipped /
                                                      queue_stats.total) *
                                                      100 +
                                                  '%'
                                                : '0%',
                                    }"
                                    :title="
                                        $t('messages.stats.queue.skipped') +
                                        ': ' +
                                        queue_stats.skipped
                                    "
                                ></div>
                            </div>
                        </div>

                        <!-- Stats Grid -->
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                            <div
                                class="text-center p-3 rounded-lg bg-blue-50 dark:bg-blue-900/20"
                            >
                                <div
                                    class="text-2xl font-bold text-blue-600 dark:text-blue-400"
                                >
                                    {{ queue_stats.planned }}
                                </div>
                                <div
                                    class="text-xs text-blue-500 dark:text-blue-300 uppercase"
                                >
                                    {{ $t("messages.stats.queue.planned") }}
                                </div>
                            </div>
                            <div
                                class="text-center p-3 rounded-lg bg-yellow-50 dark:bg-yellow-900/20"
                            >
                                <div
                                    class="text-2xl font-bold text-yellow-600 dark:text-yellow-400"
                                >
                                    {{ queue_stats.queued }}
                                </div>
                                <div
                                    class="text-xs text-yellow-500 dark:text-yellow-300 uppercase"
                                >
                                    {{ $t("messages.stats.queue.queued") }}
                                </div>
                            </div>
                            <div
                                class="text-center p-3 rounded-lg bg-green-50 dark:bg-green-900/20"
                            >
                                <div
                                    class="text-2xl font-bold text-green-600 dark:text-green-400"
                                >
                                    {{ queue_stats.sent }}
                                </div>
                                <div
                                    class="text-xs text-green-500 dark:text-green-300 uppercase"
                                >
                                    {{ $t("messages.stats.queue.sent") }}
                                </div>
                            </div>
                            <div
                                class="text-center p-3 rounded-lg bg-red-50 dark:bg-red-900/20"
                            >
                                <div
                                    class="text-2xl font-bold text-red-600 dark:text-red-400"
                                >
                                    {{ queue_stats.failed }}
                                </div>
                                <div
                                    class="text-xs text-red-500 dark:text-red-300 uppercase"
                                >
                                    {{ $t("messages.stats.queue.failed") }}
                                </div>
                                <button
                                    v-if="queue_stats.failed > 0"
                                    @click="resendToFailed"
                                    :disabled="resendingToFailed"
                                    class="mt-2 text-xs px-2 py-1 bg-red-600 hover:bg-red-700 text-white rounded transition-colors disabled:opacity-50"
                                >
                                    {{
                                        resendingToFailed
                                            ? $t(
                                                  "messages.stats.queue.resending",
                                              )
                                            : $t(
                                                  "messages.stats.queue.resend_failed",
                                              )
                                    }}
                                </button>
                            </div>
                            <div
                                class="text-center p-3 rounded-lg bg-gray-50 dark:bg-gray-700"
                            >
                                <div
                                    class="text-2xl font-bold text-gray-600 dark:text-gray-400"
                                >
                                    {{ queue_stats.skipped }}
                                </div>
                                <div
                                    class="text-xs text-gray-500 dark:text-gray-300 uppercase"
                                >
                                    {{ $t("messages.stats.queue.skipped") }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recipients List -->
                <div
                    v-if="
                        recipients &&
                        (recipients.data?.length > 0 || searchRecipients)
                    "
                    class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden"
                >
                    <div
                        class="px-6 py-4 border-b border-gray-200 dark:border-gray-700"
                    >
                        <div
                            class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3"
                        >
                            <h3
                                class="text-lg font-semibold text-gray-800 dark:text-gray-200"
                            >
                                📧 {{ $t("messages.stats.recipients.title") }}
                            </h3>
                            <div class="flex items-center gap-3">
                                <div class="relative">
                                    <input
                                        v-model="searchRecipients"
                                        type="text"
                                        :placeholder="
                                            $t('common.search_placeholder')
                                        "
                                        class="w-48 px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:placeholder-gray-400"
                                    />
                                    <svg
                                        v-if="!searchRecipients"
                                        class="absolute right-2.5 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                        />
                                    </svg>
                                    <button
                                        v-else
                                        @click="searchRecipients = ''"
                                        class="absolute right-2.5 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                    >
                                        <svg
                                            class="w-4 h-4"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M6 18L18 6M6 6l12 12"
                                            />
                                        </svg>
                                    </button>
                                </div>
                                <span
                                    class="text-sm text-gray-500 dark:text-gray-400 whitespace-nowrap"
                                >
                                    {{
                                        $t("messages.stats.recipients.total", {
                                            count: recipients.total || 0,
                                        })
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table
                            class="min-w-full text-sm text-left text-gray-500 dark:text-gray-400"
                        >
                            <thead
                                class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400"
                            >
                                <tr>
                                    <th class="px-4 py-3">
                                        {{
                                            $t(
                                                "messages.stats.recipients.email",
                                            )
                                        }}
                                    </th>
                                    <th class="px-4 py-3">
                                        {{
                                            $t("messages.stats.recipients.name")
                                        }}
                                    </th>
                                    <th class="px-4 py-3">
                                        {{
                                            $t(
                                                "messages.stats.recipients.queue_status",
                                            )
                                        }}
                                    </th>
                                    <th class="px-4 py-3">
                                        {{
                                            $t(
                                                "messages.stats.recipients.sent_at",
                                            )
                                        }}
                                    </th>
                                    <th class="px-4 py-3">
                                        {{
                                            $t(
                                                "messages.stats.recipients.error",
                                            )
                                        }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="recipient in recipients.data"
                                    :key="recipient.id"
                                    class="border-b dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50"
                                >
                                    <td
                                        class="px-4 py-3 font-medium text-gray-900 dark:text-white"
                                    >
                                        {{ recipient.email }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ recipient.name || "-" }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex px-2 py-1 rounded-full text-xs font-medium"
                                            :class="
                                                getStatusClass(
                                                    recipient.queue_status,
                                                )
                                            "
                                        >
                                            {{
                                                $t(
                                                    `messages.stats.recipients.status.${recipient.queue_status}`,
                                                )
                                            }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        {{
                                            recipient.sent_at ||
                                            recipient.planned_at ||
                                            "-"
                                        }}
                                    </td>
                                    <td class="px-4 py-3 max-w-xs">
                                        <button
                                            v-if="recipient.error"
                                            @click="
                                                openErrorModal(
                                                    recipient.email,
                                                    recipient.error,
                                                )
                                            "
                                            class="text-left text-red-600 dark:text-red-400 hover:underline cursor-pointer truncate block max-w-full"
                                            :title="
                                                $t(
                                                    'messages.stats.recipients.click_to_view_error',
                                                )
                                            "
                                        >
                                            {{ recipient.error }}
                                        </button>
                                        <span v-else class="text-gray-400"
                                            >-</span
                                        >
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <!-- Pagination -->
                    <div
                        v-if="recipients.links && recipients.links.length > 3"
                        class="flex items-center justify-center border-t border-gray-100 px-6 py-4 dark:border-gray-700"
                    >
                        <div class="flex gap-1">
                            <Link
                                v-for="(link, i) in recipients.links"
                                :key="i"
                                :href="link.url || '#'"
                                class="rounded-lg px-3 py-1 text-sm"
                                :class="{
                                    'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/50 dark:text-indigo-400':
                                        link.active,
                                    'text-gray-500 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800':
                                        !link.active && link.url,
                                    'text-gray-500 dark:text-gray-400 opacity-50 cursor-not-allowed':
                                        !link.url,
                                }"
                                v-html="link.label"
                                preserve-scroll
                            />
                        </div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div
                        class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow h-96"
                    >
                        <h3
                            class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200"
                        >
                            {{ $t("messages.stats.charts.effectiveness") }}
                        </h3>
                        <Doughnut
                            :data="doughnutData"
                            :options="chartOptions"
                        />
                    </div>
                    <div
                        class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow h-80"
                    >
                        <h3
                            class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200"
                        >
                            {{ $t("messages.stats.charts.conversion_funnel") }}
                        </h3>
                        <Bar :data="barData" :options="chartOptions" />
                    </div>
                </div>

                <!-- Read Time Charts & Top Readers -->
                <div
                    v-if="read_time_histogram && read_time_histogram.total > 0"
                    class="grid grid-cols-1 md:grid-cols-2 gap-6"
                >
                    <!-- Read Time Distribution -->
                    <div
                        class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow h-80"
                    >
                        <h3
                            class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200"
                        >
                            <span class="mr-2">⏱️</span
                            >{{ $t("messages.stats.read_time.distribution") }}
                        </h3>
                        <Bar
                            :data="readTimeChartData"
                            :options="chartOptions"
                        />
                    </div>

                    <!-- Top Readers -->
                    <div
                        class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow"
                    >
                        <h3
                            class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200"
                        >
                            <span class="mr-2">🏆</span
                            >{{ $t("messages.stats.read_time.top_readers") }}
                        </h3>
                        <div class="overflow-x-auto max-h-64 overflow-y-auto">
                            <table
                                class="min-w-full text-sm text-left text-gray-500 dark:text-gray-400"
                            >
                                <thead
                                    class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400 sticky top-0"
                                >
                                    <tr>
                                        <th class="px-3 py-2">
                                            {{
                                                $t(
                                                    "messages.stats.activity.email",
                                                )
                                            }}
                                        </th>
                                        <th class="px-3 py-2 text-right">
                                            {{
                                                $t(
                                                    "messages.stats.read_time.time",
                                                )
                                            }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(reader, i) in top_readers"
                                        :key="i"
                                        class="border-b dark:border-gray-700"
                                    >
                                        <td class="px-3 py-2">
                                            <div>{{ reader.email }}</div>
                                            <div
                                                v-if="reader.name"
                                                class="text-xs text-gray-400"
                                            >
                                                {{ reader.name }}
                                            </div>
                                        </td>
                                        <td
                                            class="px-3 py-2 text-right font-medium text-violet-600"
                                        >
                                            {{ reader.read_time }}
                                        </td>
                                    </tr>
                                    <tr
                                        v-if="
                                            !top_readers ||
                                            top_readers.length === 0
                                        "
                                    >
                                        <td
                                            colspan="2"
                                            class="px-3 py-2 text-center"
                                        >
                                            {{
                                                $t(
                                                    "messages.stats.activity.no_data",
                                                )
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity Logs -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
                    <!-- Opens Log -->
                    <div
                        class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow"
                    >
                        <div
                            class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-4"
                        >
                            <h3
                                class="text-lg font-semibold text-gray-800 dark:text-gray-200"
                            >
                                {{ $t("messages.stats.activity.recent_opens") }}
                            </h3>
                            <div class="relative">
                                <input
                                    v-model="searchOpens"
                                    type="text"
                                    :placeholder="
                                        $t('common.search_placeholder')
                                    "
                                    class="w-48 px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:placeholder-gray-400"
                                />
                                <svg
                                    v-if="!searchOpens"
                                    class="absolute right-2.5 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                    />
                                </svg>
                                <button
                                    v-else
                                    @click="searchOpens = ''"
                                    class="absolute right-2.5 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                >
                                    <svg
                                        class="w-4 h-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12"
                                        />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <!-- Bulk Actions Bar for Opens -->
                        <div
                            v-if="selectedOpens.length > 0"
                            class="mb-3 flex items-center gap-3 rounded-lg bg-indigo-50 px-4 py-2 dark:bg-indigo-900/30"
                        >
                            <span
                                class="text-sm font-medium text-indigo-700 dark:text-indigo-300"
                            >
                                {{ selectedOpens.length }}
                                {{ $t("messages.stats.bulk_selected") }}
                            </span>
                            <button
                                @click="openBulkModal('opens')"
                                class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700"
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
                                        d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                                    />
                                </svg>
                                {{ $t("messages.stats.bulk_add_to_list") }}
                            </button>
                            <button
                                @click="selectAllOpens"
                                class="text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-200"
                            >
                                {{ $t("common.select_all") }}
                            </button>
                            <button
                                @click="clearOpensSelection"
                                class="text-xs text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
                            >
                                {{ $t("common.deselect_all") }}
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table
                                class="min-w-full text-sm text-left text-gray-500 dark:text-gray-400"
                            >
                                <thead
                                    class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400"
                                >
                                    <tr>
                                        <th class="px-2 py-2 w-10">
                                            <input
                                                type="checkbox"
                                                :checked="allOpensSelected"
                                                @change="toggleAllOpens"
                                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700"
                                            />
                                        </th>
                                        <th
                                            class="px-4 py-2 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600"
                                            @click="
                                                handleSort('opens', 'email')
                                            "
                                        >
                                            {{
                                                $t(
                                                    "messages.stats.activity.email",
                                                )
                                            }}
                                            ⇅
                                        </th>
                                        <th
                                            class="px-4 py-2 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600"
                                            @click="handleSort('opens', 'time')"
                                        >
                                            {{
                                                $t(
                                                    "messages.stats.activity.time",
                                                )
                                            }}
                                            ⇅
                                        </th>
                                        <th class="px-4 py-2">
                                            {{ $t("messages.stats.actions") }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(log, i) in recent_activity.opens
                                            .data"
                                        :key="i"
                                        class="border-b dark:border-gray-700"
                                        :class="
                                            selectedOpens.includes(
                                                log.subscriber_id,
                                            )
                                                ? 'bg-indigo-50 dark:bg-indigo-900/20'
                                                : ''
                                        "
                                    >
                                        <td class="px-2 py-2">
                                            <input
                                                v-if="log.subscriber_id"
                                                type="checkbox"
                                                :checked="
                                                    selectedOpens.includes(
                                                        log.subscriber_id,
                                                    )
                                                "
                                                @change="
                                                    toggleSelectOpen(
                                                        log.subscriber_id,
                                                    )
                                                "
                                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700"
                                            />
                                        </td>
                                        <td class="px-4 py-2">
                                            {{ log.email }}
                                        </td>
                                        <td class="px-4 py-2">
                                            {{ log.occurred_at }}
                                        </td>
                                        <td class="px-4 py-2">
                                            <AddToListDropdown
                                                v-if="log.subscriber_id"
                                                :subscriber-id="
                                                    log.subscriber_id
                                                "
                                                :subscriber-email="log.email"
                                            />
                                        </td>
                                    </tr>
                                    <tr
                                        v-if="
                                            !recent_activity.opens.data ||
                                            recent_activity.opens.data
                                                .length === 0
                                        "
                                    >
                                        <td
                                            colspan="4"
                                            class="px-4 py-2 text-center"
                                        >
                                            {{
                                                $t(
                                                    "messages.stats.activity.no_data",
                                                )
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <!-- Pagination for Opens -->
                        <div
                            v-if="
                                recent_activity.opens.links &&
                                recent_activity.opens.links.length > 3
                            "
                            class="flex items-center justify-center border-t border-gray-100 px-6 py-4 dark:border-gray-700"
                        >
                            <div class="flex gap-1 flex-wrap justify-center">
                                <Link
                                    v-for="(link, i) in recent_activity.opens
                                        .links"
                                    :key="i"
                                    :href="link.url || '#'"
                                    class="rounded-lg px-3 py-1 text-sm whitespace-nowrap"
                                    :class="{
                                        'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/50 dark:text-indigo-400':
                                            link.active,
                                        'text-gray-500 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700':
                                            !link.active && link.url,
                                        'text-gray-500 dark:text-gray-400 opacity-50 cursor-not-allowed':
                                            !link.url,
                                    }"
                                    v-html="link.label"
                                    preserve-scroll
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Clicks Log -->
                    <div
                        class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow"
                    >
                        <div
                            class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-4"
                        >
                            <h3
                                class="text-lg font-semibold text-gray-800 dark:text-gray-200"
                            >
                                {{
                                    $t("messages.stats.activity.recent_clicks")
                                }}
                            </h3>
                            <div class="relative">
                                <input
                                    v-model="searchClicks"
                                    type="text"
                                    :placeholder="
                                        $t('messages.stats.search_email_or_url')
                                    "
                                    class="w-48 px-3 py-1.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:placeholder-gray-400"
                                />
                                <svg
                                    v-if="!searchClicks"
                                    class="absolute right-2.5 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                    />
                                </svg>
                                <button
                                    v-else
                                    @click="searchClicks = ''"
                                    class="absolute right-2.5 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                >
                                    <svg
                                        class="w-4 h-4"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12"
                                        />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <!-- Bulk Actions Bar for Clicks -->
                        <div
                            v-if="selectedClicks.length > 0"
                            class="mb-3 flex items-center gap-3 rounded-lg bg-indigo-50 px-4 py-2 dark:bg-indigo-900/30"
                        >
                            <span
                                class="text-sm font-medium text-indigo-700 dark:text-indigo-300"
                            >
                                {{ selectedClicks.length }}
                                {{ $t("messages.stats.bulk_selected") }}
                            </span>
                            <button
                                @click="openBulkModal('clicks')"
                                class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700"
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
                                        d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                                    />
                                </svg>
                                {{ $t("messages.stats.bulk_add_to_list") }}
                            </button>
                            <button
                                @click="selectAllClicks"
                                class="text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-200"
                            >
                                {{ $t("common.select_all") }}
                            </button>
                            <button
                                @click="clearClicksSelection"
                                class="text-xs text-gray-600 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200"
                            >
                                {{ $t("common.deselect_all") }}
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table
                                class="w-full text-sm text-left text-gray-500 dark:text-gray-400 table-fixed"
                            >
                                <thead
                                    class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400"
                                >
                                    <tr>
                                        <th class="px-2 py-2 w-10">
                                            <input
                                                type="checkbox"
                                                :checked="allClicksSelected"
                                                @change="toggleAllClicks"
                                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700"
                                            />
                                        </th>
                                        <th
                                            class="px-4 py-2 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 w-1/4"
                                            @click="
                                                handleSort('clicks', 'email')
                                            "
                                        >
                                            {{
                                                $t(
                                                    "messages.stats.activity.email",
                                                )
                                            }}
                                            ⇅
                                        </th>
                                        <th
                                            class="px-4 py-2 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 w-2/5"
                                            @click="handleSort('clicks', 'url')"
                                        >
                                            {{
                                                $t(
                                                    "messages.stats.activity.url",
                                                )
                                            }}
                                            ⇅
                                        </th>
                                        <th
                                            class="px-4 py-2 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600 w-1/5"
                                            @click="
                                                handleSort('clicks', 'time')
                                            "
                                        >
                                            {{
                                                $t(
                                                    "messages.stats.activity.time",
                                                )
                                            }}
                                            ⇅
                                        </th>
                                        <th
                                            class="px-4 py-2 w-24 bg-gray-50 dark:bg-gray-700 text-center"
                                        >
                                            {{ $t("messages.stats.actions") }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(log, i) in recent_activity
                                            .clicks.data"
                                        :key="i"
                                        class="border-b dark:border-gray-700"
                                        :class="
                                            selectedClicks.includes(
                                                log.subscriber_id,
                                            )
                                                ? 'bg-indigo-50 dark:bg-indigo-900/20'
                                                : ''
                                        "
                                    >
                                        <td class="px-2 py-2">
                                            <input
                                                v-if="log.subscriber_id"
                                                type="checkbox"
                                                :checked="
                                                    selectedClicks.includes(
                                                        log.subscriber_id,
                                                    )
                                                "
                                                @change="
                                                    toggleSelectClick(
                                                        log.subscriber_id,
                                                    )
                                                "
                                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700"
                                            />
                                        </td>
                                        <td class="px-4 py-2">
                                            {{ log.email }}
                                        </td>
                                        <td
                                            class="px-4 py-2 truncate max-w-[200px]"
                                        >
                                            <a
                                                :href="log.url"
                                                target="_blank"
                                                class="text-blue-600 hover:underline dark:text-blue-400"
                                            >
                                                {{ log.url }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-2">
                                            {{ log.occurred_at }}
                                        </td>
                                        <td
                                            class="px-4 py-2 bg-white dark:bg-gray-800 text-center"
                                        >
                                            <AddToListDropdown
                                                v-if="log.subscriber_id"
                                                :subscriber-id="
                                                    log.subscriber_id
                                                "
                                                :subscriber-email="log.email"
                                            />
                                        </td>
                                    </tr>
                                    <tr
                                        v-if="
                                            !recent_activity.clicks.data ||
                                            recent_activity.clicks.data
                                                .length === 0
                                        "
                                    >
                                        <td
                                            colspan="5"
                                            class="px-4 py-2 text-center"
                                        >
                                            {{
                                                $t(
                                                    "messages.stats.activity.no_data",
                                                )
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <!-- Pagination for Clicks -->
                        <div
                            v-if="
                                recent_activity.clicks.links &&
                                recent_activity.clicks.links.length > 3
                            "
                            class="flex items-center justify-center border-t border-gray-100 px-6 py-4 dark:border-gray-700"
                        >
                            <div class="flex gap-1 flex-wrap justify-center">
                                <Link
                                    v-for="(link, i) in recent_activity.clicks
                                        .links"
                                    :key="i"
                                    :href="link.url || '#'"
                                    class="rounded-lg px-3 py-1 text-sm whitespace-nowrap"
                                    :class="{
                                        'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/50 dark:text-indigo-400':
                                            link.active,
                                        'text-gray-500 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700':
                                            !link.active && link.url,
                                        'text-gray-500 dark:text-gray-400 opacity-50 cursor-not-allowed':
                                            !link.url,
                                    }"
                                    v-html="link.label"
                                    preserve-scroll
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Error Details Modal -->
    <Modal :show="showErrorModal" @close="closeErrorModal" max-width="lg">
        <div class="p-6">
            <h3
                class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4"
            >
                {{ $t("messages.stats.recipients.error_details") }}
            </h3>
            <div class="mb-2 text-sm text-gray-500 dark:text-gray-400">
                {{ $t("messages.stats.recipients.email") }}:
                <span class="font-medium text-gray-900 dark:text-gray-100">{{
                    selectedError.email
                }}</span>
            </div>
            <div
                class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-4"
            >
                <pre
                    class="text-sm text-red-700 dark:text-red-300 whitespace-pre-wrap break-words font-mono"
                    >{{ selectedError.error }}</pre
                >
            </div>
            <div class="flex justify-end gap-3">
                <button
                    @click="copyError"
                    class="px-4 py-2 text-sm font-medium rounded-lg transition-colors"
                    :class="
                        errorCopied
                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                    "
                >
                    <span v-if="errorCopied"
                        >✓ {{ $t("messages.stats.recipients.copied") }}</span
                    >
                    <span v-else
                        >📋
                        {{ $t("messages.stats.recipients.copy_error") }}</span
                    >
                </button>
                <button
                    @click="closeErrorModal"
                    class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors"
                >
                    {{ $t("common.close") }}
                </button>
            </div>
        </div>
    </Modal>

    <!-- Bulk Add to List Modal -->
    <BulkAddToListModal
        :show="showBulkModal"
        :subscriber-ids="
            bulkModalType === 'opens' ? selectedOpens : selectedClicks
        "
        :type="bulkModalType"
        @close="showBulkModal = false"
        @success="handleBulkSuccess"
    />
</template>
