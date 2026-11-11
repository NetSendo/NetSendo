<script setup>
import { ref, watch, computed } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import AddToListDropdown from "@/Components/AddToListDropdown.vue";
import BulkAddToListModal from "@/Components/BulkAddToListModal.vue";
import Panel from "@/Components/Ui/Panel.vue";
import PageHeader from "@/Components/Ui/PageHeader.vue";
import StatTiles from "@/Components/Ui/StatTiles.vue";
import StatusPill from "@/Components/Ui/StatusPill.vue";
import Pagination from "@/Components/Ui/Pagination.vue";
import SortMark from "@/Components/Ui/SortMark.vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import {
    Chart as ChartJS,
    Tooltip,
    CategoryScale,
    LinearScale,
    BarController,
    BarElement,
} from "chart.js";
import { Bar } from "vue-chartjs";
import { useI18n } from "vue-i18n";
import { useTheme } from "@/Composables/useTheme";

ChartJS.register(Tooltip, CategoryScale, LinearScale, BarController, BarElement);

const { t, locale } = useI18n();
const { isDark } = useTheme();
const page = usePage();

const number = (value) => new Intl.NumberFormat(locale.value).format(value ?? 0);
const percent = (value) =>
    `${new Intl.NumberFormat(locale.value, { maximumFractionDigits: 1 }).format(value ?? 0)}%`;

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

// What happened to one copy of the message, as a tone
const STATUS_TONES = {
    planned: "info",
    queued: "warning",
    sent: "success",
    failed: "danger",
    skipped: "neutral",
};

// The sort of the two activity tables lives in the URL (sort_opens_by, sort_clicks_dir, ...)
const sortParams = computed(() => new URLSearchParams(page.url.split("?")[1] || ""));
const sortState = (type, field) =>
    sortParams.value.get(`sort_${type}_by`) === field
        ? sortParams.value.get(`sort_${type}_dir`) || "desc"
        : null;
const ariaSort = (type, field) => {
    const state = sortState(type, field);
    return state ? (state === "asc" ? "ascending" : "descending") : "none";
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

const shareOfSent = (value) =>
    props.stats.sent > 0 ? Math.min(100, (value / props.stats.sent) * 100) : 0;

// Sent, then the people who opened, then the people who clicked: each a share of what was sent
const funnel = computed(() =>
    [
        { key: "sent", label: t("messages.stats.charts.labels.sent"), value: props.stats.sent, bar: "bg-indigo-600 dark:bg-indigo-400" },
        { key: "opens", label: t("messages.stats.charts.labels.unique_opens"), value: props.stats.unique_opens, bar: "bg-amber-500 dark:bg-amber-400" },
        { key: "clicks", label: t("messages.stats.charts.labels.unique_clicks"), value: props.stats.unique_clicks, bar: "bg-emerald-600 dark:bg-emerald-400" },
    ].map((step) => {
        const share = step.key === "sent" ? (props.stats.sent > 0 ? 100 : 0) : shareOfSent(step.value);
        return { ...step, share: percent(share), width: `${share}%` };
    }),
);

const kpiTiles = computed(() => [
    { key: "sent", label: t("messages.stats.kpi.sent"), value: number(props.stats.sent) },
    {
        key: "open_rate",
        label: t("messages.stats.kpi.open_rate"),
        value: percent(props.stats.open_rate),
        caption: t("messages.stats.kpi.unique", { count: number(props.stats.unique_opens) }),
    },
    {
        key: "click_rate",
        label: t("messages.stats.kpi.click_rate"),
        value: percent(props.stats.click_rate),
        caption: t("messages.stats.kpi.unique", { count: number(props.stats.unique_clicks) }),
    },
    {
        key: "ctor",
        label: t("messages.stats.kpi.ctor"),
        value: percent(props.stats.click_to_open_rate),
        caption: t("messages.stats.kpi.clicks_opens"),
    },
]);

const readTimeTiles = computed(() => [
    { key: "average", label: t("messages.stats.read_time.average"), value: formatReadTime(props.read_time_stats?.average_seconds) },
    { key: "median", label: t("messages.stats.read_time.median"), value: formatReadTime(props.read_time_stats?.median_seconds) },
    { key: "sessions", label: t("messages.stats.read_time.sessions"), value: number(props.read_time_stats?.total_sessions) },
    { key: "max", label: t("messages.stats.read_time.max"), value: formatReadTime(props.read_time_stats?.max_seconds) },
]);

// Queue messages always have a queue; a broadcast has one once it went through it
const showQueue = computed(
    () =>
        Boolean(props.queue_stats) &&
        (props.queue_stats.total > 0 || props.message.type === "autoresponder"),
);

// The queue in five parts that add up to its total
const queueParts = computed(() => {
    const queue = props.queue_stats || {};
    const width = (value) => (queue.total > 0 ? `${((value || 0) / queue.total) * 100}%` : "0%");

    return [
        { key: "sent", bar: "bg-emerald-600 dark:bg-emerald-400" },
        { key: "queued", bar: "bg-amber-500 dark:bg-amber-400" },
        { key: "planned", bar: "bg-indigo-600 dark:bg-indigo-400" },
        { key: "failed", bar: "bg-rose-600 dark:bg-rose-400" },
        { key: "skipped", bar: "bg-slate-400 dark:bg-slate-500" },
    ].map((part) => ({
        ...part,
        label: t(`messages.stats.queue.${part.key}`),
        value: queue[part.key] || 0,
        width: width(queue[part.key]),
    }));
});

const headerSubtitle = computed(() =>
    props.message.scheduled_at
        ? `${t("messages.stats.title")} · ${t("messages.scheduled_for")}: ${props.message.scheduled_at}`
        : t("messages.stats.title"),
);

// How long people stayed with the message
const readTimeChartData = computed(() => ({
    labels: props.read_time_histogram?.labels || [],
    datasets: [
        {
            label: t("messages.stats.read_time.sessions"),
            backgroundColor: isDark.value ? "#818cf8" : "#4f46e5",
            borderRadius: 3,
            maxBarThickness: 36,
            data: props.read_time_histogram?.data || [],
        },
    ],
}));

const chartOptions = computed(() => {
    const grid = isDark.value ? "rgba(148, 163, 184, 0.14)" : "#e2e8f0";
    const ticks = isDark.value ? "#94a3b8" : "#475569";
    const font = { family: "Figtree, ui-sans-serif, system-ui, sans-serif", size: 12 };

    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            datalabels: { display: false },
            tooltip: {
                backgroundColor: isDark.value ? "#f8fafc" : "#0f172a",
                titleColor: isDark.value ? "#0f172a" : "#ffffff",
                bodyColor: isDark.value ? "#0f172a" : "#ffffff",
                padding: 12,
                cornerRadius: 10,
                displayColors: false,
                titleFont: font,
                bodyFont: font,
            },
        },
        scales: {
            x: { grid: { display: false }, border: { color: grid }, ticks: { color: ticks, font } },
            y: {
                beginAtZero: true,
                border: { display: false },
                grid: { color: grid },
                ticks: { color: ticks, font, precision: 0, maxTicksLimit: 5 },
            },
        },
    };
});

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
const notice = ref(null);

const resendToFailed = () => {
    if (resendingToFailed.value) return;

    resendingToFailed.value = true;
    notice.value = null;

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
                notice.value = t("messages.stats.queue.resend_error");
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

// Select all opens (fetches ALL matching IDs from server, not just current page)
const loadingSelectAllOpens = ref(false);
const selectAllOpens = async () => {
    loadingSelectAllOpens.value = true;
    try {
        const params = new URLSearchParams();
        if (searchOpens.value) {
            params.set("search_opens", searchOpens.value);
        }
        const url =
            route("messages.all-opens-ids", { message: props.message.id }) +
            (params.toString() ? `?${params.toString()}` : "");
        const response = await fetch(url);
        const data = await response.json();

        // Merge with existing selection (add new IDs, keep existing ones)
        const newIds = data.subscriber_ids.filter(
            (id) => !selectedOpens.value.includes(id),
        );
        selectedOpens.value = [...selectedOpens.value, ...newIds];
    } catch (error) {
        console.error("Failed to fetch all opens IDs:", error);
        // Fallback to current page selection
        const pageData = props.recent_activity?.opens?.data || [];
        const subscriberIds = pageData
            .filter((log) => log.subscriber_id)
            .map((log) => log.subscriber_id);
        const newIds = subscriberIds.filter(
            (id) => !selectedOpens.value.includes(id),
        );
        selectedOpens.value = [...selectedOpens.value, ...newIds];
    } finally {
        loadingSelectAllOpens.value = false;
    }
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

// Select all clicks (fetches ALL matching IDs from server, not just current page)
const loadingSelectAllClicks = ref(false);
const selectAllClicks = async () => {
    loadingSelectAllClicks.value = true;
    try {
        const params = new URLSearchParams();
        if (searchClicks.value) {
            params.set("search_clicks", searchClicks.value);
        }
        const url =
            route("messages.all-clicks-ids", { message: props.message.id }) +
            (params.toString() ? `?${params.toString()}` : "");
        const response = await fetch(url);
        const data = await response.json();

        // Merge with existing selection (add new IDs, keep existing ones)
        const newIds = data.subscriber_ids.filter(
            (id) => !selectedClicks.value.includes(id),
        );
        selectedClicks.value = [...selectedClicks.value, ...newIds];
    } catch (error) {
        console.error("Failed to fetch all clicks IDs:", error);
        // Fallback to current page selection
        const pageData = props.recent_activity?.clicks?.data || [];
        const subscriberIds = pageData
            .filter((log) => log.subscriber_id)
            .map((log) => log.subscriber_id);
        const newIds = subscriberIds.filter(
            (id) => !selectedClicks.value.includes(id),
        );
        selectedClicks.value = [...selectedClicks.value, ...newIds];
    } finally {
        loadingSelectAllClicks.value = false;
    }
};

// Clear selections
const clearOpensSelection = () => {
    selectedOpens.value = [];
};

const clearClicksSelection = () => {
    selectedClicks.value = [];
};

// The two activity tables are the same table over different data
const activityLogs = computed(() => [
    {
        type: "opens",
        title: t("messages.stats.activity.recent_opens"),
        total: props.recent_activity?.opens_total,
        placeholder: t("common.search_placeholder"),
        search: searchOpens,
        selected: selectedOpens,
        allSelected: allOpensSelected,
        loadingAll: loadingSelectAllOpens,
        toggle: toggleSelectOpen,
        toggleAll: toggleAllOpens,
        selectAll: selectAllOpens,
        clear: clearOpensSelection,
        paginator: props.recent_activity?.opens,
        rows: props.recent_activity?.opens?.data || [],
        columns: [
            { field: "email", label: t("messages.stats.activity.email") },
            { field: "time", label: t("messages.stats.activity.time") },
        ],
    },
    {
        type: "clicks",
        title: t("messages.stats.activity.recent_clicks"),
        total: props.recent_activity?.clicks_total,
        placeholder: t("messages.stats.search_email_or_url"),
        search: searchClicks,
        selected: selectedClicks,
        allSelected: allClicksSelected,
        loadingAll: loadingSelectAllClicks,
        toggle: toggleSelectClick,
        toggleAll: toggleAllClicks,
        selectAll: selectAllClicks,
        clear: clearClicksSelection,
        paginator: props.recent_activity?.clicks,
        rows: props.recent_activity?.clicks?.data || [],
        columns: [
            { field: "email", label: t("messages.stats.activity.email") },
            { field: "url", label: t("messages.stats.activity.url") },
            { field: "time", label: t("messages.stats.activity.time") },
        ],
    },
]);

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
            <PageHeader :title="message.subject || $t('messages.stats.title')" :subtitle="headerSubtitle">
                <Link
                    :href="route('messages.edit', message.id)"
                    class="inline-flex min-h-10 items-center rounded-xl border border-slate-300 px-3.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700/60"
                >
                    {{ $t("common.edit") }}
                </Link>
                <Link
                    :href="route('messages.index')"
                    class="inline-flex min-h-10 items-center gap-1.5 rounded-xl border border-slate-300 px-3.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700/60"
                >
                    <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M15 19l-7-7 7-7" />
                    </svg>
                    {{ $t("messages.stats.back_to_list") }}
                </Link>
            </PageHeader>
        </template>

        <div class="mx-auto flex max-w-screen-2xl flex-col gap-5">
            <p
                v-if="notice"
                role="status"
                class="rounded-2xl border border-rose-200 bg-rose-50 px-5 py-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300"
            >
                {{ notice }}
            </p>

            <!-- The four numbers the page is opened for -->
            <StatTiles :items="kpiTiles" :label="$t('messages.stats.title')" />

            <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
                <!-- From sent to opened to clicked, each as a share of what was sent -->
                <Panel
                    :title="$t('messages.stats.charts.conversion_funnel')"
                    :class="{ 'xl:col-span-2': !showQueue }"
                >
                    <ul class="flex flex-col gap-5">
                        <li v-for="step in funnel" :key="step.key" class="flex flex-col gap-1.5">
                            <div class="flex items-baseline justify-between gap-3 text-sm">
                                <span class="font-medium text-slate-900 dark:text-white">{{ step.label }}</span>
                                <span class="tabular-nums text-slate-600 dark:text-slate-400">
                                    <span class="font-semibold text-slate-900 dark:text-white">{{ number(step.value) }}</span>
                                    · {{ step.share }}
                                </span>
                            </div>
                            <div class="h-2.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700/60">
                                <div
                                    class="h-full rounded-full"
                                    :class="step.bar"
                                    :style="{ width: step.width }"
                                ></div>
                            </div>
                        </li>
                    </ul>
                </Panel>

                <!-- Queue progress (queue messages, and broadcasts that went through the queue) -->
                <Panel v-if="showQueue" :title="$t('messages.stats.queue.title')">
                    <template v-if="message.recipients_calculated_at" #actions>
                        <span class="text-xs tabular-nums text-slate-600 dark:text-slate-400">
                            {{ $t("messages.stats.queue.last_sync") }}: {{ message.recipients_calculated_at }}
                        </span>
                    </template>

                    <div
                        role="img"
                        :aria-label="$t('messages.stats.queue.title')"
                        class="flex h-2.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-700/60"
                    >
                        <div
                            v-for="part in queueParts"
                            :key="part.key"
                            class="h-full"
                            :class="part.bar"
                            :style="{ width: part.width }"
                        ></div>
                    </div>

                    <dl class="mt-5 grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-3">
                        <div v-for="part in queueParts" :key="part.key" class="flex flex-col gap-0.5">
                            <dt class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                                <span aria-hidden="true" class="h-2.5 w-2.5 rounded-sm" :class="part.bar"></span>
                                {{ part.label }}
                            </dt>
                            <dd class="flex items-center gap-3 text-xl font-semibold tabular-nums text-slate-900 dark:text-white">
                                {{ number(part.value) }}
                                <button
                                    v-if="part.key === 'failed' && part.value > 0"
                                    type="button"
                                    :disabled="resendingToFailed"
                                    class="min-h-8 rounded-lg border border-slate-300 px-2.5 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50 disabled:opacity-60 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700/60"
                                    @click="resendToFailed"
                                >
                                    {{
                                        resendingToFailed
                                            ? $t("messages.stats.queue.resending")
                                            : $t("messages.stats.queue.resend_failed")
                                    }}
                                </button>
                            </dd>
                        </div>
                    </dl>
                </Panel>
            </div>

            <!-- Reading time: only when the message was measured -->
            <template v-if="read_time_stats && read_time_stats.total_sessions > 0">
                <StatTiles :items="readTimeTiles" :label="$t('messages.stats.read_time.distribution')" />

                <div
                    v-if="read_time_histogram && read_time_histogram.total > 0"
                    class="grid grid-cols-1 gap-5 xl:grid-cols-2"
                >
                    <Panel :title="$t('messages.stats.read_time.distribution')">
                        <div class="relative h-64">
                            <Bar
                                :data="readTimeChartData"
                                :options="chartOptions"
                                role="img"
                                :aria-label="$t('messages.stats.read_time.distribution')"
                            />
                        </div>
                    </Panel>

                    <Panel :title="$t('messages.stats.read_time.top_readers')" flush>
                        <p
                            v-if="!top_readers || top_readers.length === 0"
                            class="px-5 pb-5 text-sm text-slate-600 dark:text-slate-400"
                        >
                            {{ $t("messages.stats.activity.no_data") }}
                        </p>
                        <table v-else class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 text-left text-xs text-slate-600 dark:border-slate-700/60 dark:text-slate-400">
                                    <th scope="col" class="px-5 py-2.5 font-medium">{{ $t("messages.stats.activity.email") }}</th>
                                    <th scope="col" class="px-5 py-2.5 text-right font-medium">{{ $t("messages.stats.read_time.time") }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(reader, i) in top_readers"
                                    :key="i"
                                    class="border-b border-slate-100 last:border-0 dark:border-slate-700/40"
                                >
                                    <td class="max-w-0 px-5 py-2.5">
                                        <p class="truncate text-slate-900 dark:text-white">{{ reader.email }}</p>
                                        <p v-if="reader.name" class="truncate text-xs text-slate-600 dark:text-slate-400">{{ reader.name }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-2.5 text-right tabular-nums text-slate-900 dark:text-slate-100">
                                        {{ reader.read_time }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </Panel>
                </div>
            </template>

            <!-- Who the message went to, and what happened to each copy -->
            <Panel
                v-if="recipients && (recipients.data?.length > 0 || searchRecipients)"
                :title="$t('messages.stats.recipients.title')"
                flush
            >
                <template #actions>
                    <span class="tabular-nums text-slate-600 dark:text-slate-400">
                        {{ $t("messages.stats.recipients.total", { count: number(recipients.total || 0) }) }}
                    </span>
                </template>

                <div class="px-5 pb-4">
                    <input
                        v-model="searchRecipients"
                        type="search"
                        :placeholder="$t('common.search_placeholder')"
                        :aria-label="$t('common.search_placeholder')"
                        class="min-h-10 w-full max-w-sm rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-white dark:placeholder:text-slate-400"
                    />
                </div>

                <div class="overflow-x-auto border-t border-slate-200 dark:border-slate-700/60">
                    <table class="w-full min-w-[720px] text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs text-slate-600 dark:border-slate-700/60 dark:text-slate-400">
                                <th scope="col" class="px-5 py-2.5 font-medium">{{ $t("messages.stats.recipients.email") }}</th>
                                <th scope="col" class="px-3 py-2.5 font-medium">{{ $t("messages.stats.recipients.name") }}</th>
                                <th scope="col" class="px-3 py-2.5 font-medium">{{ $t("messages.stats.recipients.queue_status") }}</th>
                                <th scope="col" class="px-3 py-2.5 font-medium">{{ $t("messages.stats.recipients.sent_at") }}</th>
                                <th scope="col" class="py-2.5 pl-3 pr-5 font-medium">{{ $t("messages.stats.recipients.error") }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="recipient in recipients.data"
                                :key="recipient.id"
                                class="border-b border-slate-100 last:border-0 dark:border-slate-700/40"
                            >
                                <td class="px-5 py-2.5 font-medium text-slate-900 dark:text-white">{{ recipient.email }}</td>
                                <td class="px-3 py-2.5 text-slate-900 dark:text-slate-100">{{ recipient.name || "—" }}</td>
                                <td class="px-3 py-2.5">
                                    <StatusPill :tone="STATUS_TONES[recipient.queue_status] || 'neutral'">
                                        {{ $t(`messages.stats.recipients.status.${recipient.queue_status}`) }}
                                    </StatusPill>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2.5 tabular-nums text-slate-600 dark:text-slate-400">
                                    {{ recipient.sent_at || recipient.planned_at || "—" }}
                                </td>
                                <td class="max-w-0 py-2.5 pl-3 pr-5" style="width: 28%">
                                    <button
                                        v-if="recipient.error"
                                        type="button"
                                        :title="$t('messages.stats.recipients.click_to_view_error')"
                                        class="block w-full truncate text-left text-rose-700 underline decoration-rose-300 underline-offset-2 hover:no-underline dark:text-rose-300 dark:decoration-rose-500/50"
                                        @click="openErrorModal(recipient.email, recipient.error)"
                                    >
                                        {{ recipient.error }}
                                    </button>
                                    <span v-else class="text-slate-600 dark:text-slate-400">—</span>
                                </td>
                            </tr>
                            <tr v-if="recipients.data.length === 0">
                                <td colspan="5" class="px-5 py-8 text-center text-slate-600 dark:text-slate-400">
                                    {{ $t("common.no_results") }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination v-if="recipients.total > 0" :paginator="recipients" />
            </Panel>

            <!-- Opens and clicks, one by one -->
            <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
                <Panel
                    v-for="log in activityLogs"
                    :key="log.type"
                    :title="log.title"
                    flush
                >
                    <template #actions>
                        <span v-if="log.total !== undefined" class="tabular-nums text-slate-600 dark:text-slate-400">
                            {{ number(log.total) }}
                        </span>
                    </template>

                    <div class="px-5 pb-4">
                        <input
                            v-model="log.search.value"
                            type="search"
                            :placeholder="log.placeholder"
                            :aria-label="log.placeholder"
                            class="min-h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-white dark:placeholder:text-slate-400"
                        />
                    </div>

                    <!-- What can be done with the ticked people -->
                    <div
                        v-if="log.selected.value.length > 0"
                        class="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-indigo-200 bg-indigo-50 px-5 py-3 text-sm dark:border-indigo-500/30 dark:bg-indigo-500/10"
                    >
                        <span class="font-semibold tabular-nums text-indigo-900 dark:text-indigo-200">
                            {{ log.selected.value.length }} {{ $t("messages.stats.bulk_selected") }}
                        </span>
                        <button
                            type="button"
                            class="min-h-9 rounded-lg bg-indigo-600 px-3 text-sm font-semibold text-white transition-colors hover:bg-indigo-500"
                            @click="openBulkModal(log.type)"
                        >
                            {{ $t("messages.stats.bulk_add_to_list") }}
                        </button>
                        <button
                            type="button"
                            :disabled="log.loadingAll.value"
                            class="font-medium text-indigo-700 underline underline-offset-2 hover:no-underline disabled:opacity-60 dark:text-indigo-300"
                            @click="log.selectAll"
                        >
                            {{ log.loadingAll.value ? $t("common.loading") : $t("common.select_all") }}
                        </button>
                        <button
                            type="button"
                            class="font-medium text-indigo-700 underline underline-offset-2 hover:no-underline dark:text-indigo-300"
                            @click="log.clear"
                        >
                            {{ $t("common.deselect_all") }}
                        </button>
                    </div>

                    <div class="overflow-x-auto border-t border-slate-200 dark:border-slate-700/60">
                        <table class="w-full text-sm" :class="log.type === 'clicks' ? 'min-w-[640px]' : 'min-w-[480px]'">
                            <thead>
                                <tr class="border-b border-slate-200 text-left text-xs text-slate-600 dark:border-slate-700/60 dark:text-slate-400">
                                    <th scope="col" class="w-10 py-2.5 pl-5 pr-2">
                                        <input
                                            type="checkbox"
                                            :aria-label="$t('common.select_all')"
                                            :checked="log.allSelected.value"
                                            class="h-4 w-4 accent-indigo-600"
                                            @change="log.toggleAll"
                                        />
                                    </th>
                                    <th
                                        v-for="column in log.columns"
                                        :key="column.field"
                                        scope="col"
                                        class="px-3 py-2.5 font-medium"
                                        :aria-sort="ariaSort(log.type, column.field)"
                                    >
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 hover:text-slate-900 dark:hover:text-white"
                                            @click="handleSort(log.type, column.field)"
                                        >
                                            {{ column.label }}
                                            <SortMark :state="sortState(log.type, column.field)" />
                                        </button>
                                    </th>
                                    <th scope="col" class="py-2.5 pl-3 pr-5 text-right font-medium">
                                        <span class="sr-only">{{ $t("messages.stats.actions") }}</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(entry, i) in log.rows"
                                    :key="i"
                                    class="border-b border-slate-100 last:border-0 dark:border-slate-700/40"
                                    :class="
                                        entry.subscriber_id && log.selected.value.includes(entry.subscriber_id)
                                            ? 'bg-indigo-50 dark:bg-indigo-500/10'
                                            : ''
                                    "
                                >
                                    <td class="w-10 py-2.5 pl-5 pr-2">
                                        <input
                                            v-if="entry.subscriber_id"
                                            type="checkbox"
                                            :aria-label="entry.email"
                                            :checked="log.selected.value.includes(entry.subscriber_id)"
                                            class="h-4 w-4 accent-indigo-600"
                                            @change="log.toggle(entry.subscriber_id)"
                                        />
                                    </td>
                                    <td class="px-3 py-2.5 font-medium text-slate-900 dark:text-white">{{ entry.email }}</td>
                                    <td v-if="log.type === 'clicks'" class="max-w-0 px-3 py-2.5" style="width: 36%">
                                        <a
                                            :href="entry.url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            :title="entry.url"
                                            class="block truncate text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                                        >
                                            {{ entry.url }}
                                        </a>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2.5 tabular-nums text-slate-600 dark:text-slate-400">
                                        {{ entry.occurred_at }}
                                    </td>
                                    <td class="py-2.5 pl-3 pr-5 text-right">
                                        <AddToListDropdown
                                            v-if="entry.subscriber_id"
                                            :subscriber-id="entry.subscriber_id"
                                            :subscriber-email="entry.email"
                                        />
                                    </td>
                                </tr>
                                <tr v-if="log.rows.length === 0">
                                    <td :colspan="log.columns.length + 2" class="px-5 py-8 text-center text-slate-600 dark:text-slate-400">
                                        {{ $t("messages.stats.activity.no_data") }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <Pagination v-if="log.paginator?.total > 0" :paginator="log.paginator" />
                </Panel>
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- The full text of a delivery error -->
    <Modal :show="showErrorModal" max-width="lg" @close="closeErrorModal">
        <div class="flex flex-col gap-4 p-6">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                {{ $t("messages.stats.recipients.error_details") }}
            </h2>
            <p class="text-sm text-slate-600 dark:text-slate-400">
                {{ $t("messages.stats.recipients.email") }}:
                <span class="font-medium text-slate-900 dark:text-white">{{ selectedError.email }}</span>
            </p>
            <pre class="max-h-72 overflow-auto whitespace-pre-wrap break-words rounded-xl bg-slate-100 p-4 text-xs text-slate-900 dark:bg-slate-900/60 dark:text-slate-100">{{ selectedError.error }}</pre>
            <div class="flex justify-end gap-3">
                <SecondaryButton @click="copyError">
                    {{
                        errorCopied
                            ? $t("messages.stats.recipients.copied")
                            : $t("messages.stats.recipients.copy_error")
                    }}
                </SecondaryButton>
                <PrimaryButton @click="closeErrorModal">{{ $t("common.close") }}</PrimaryButton>
            </div>
        </div>
    </Modal>

    <BulkAddToListModal
        :show="showBulkModal"
        :subscriber-ids="bulkModalType === 'opens' ? selectedOpens : selectedClicks"
        :type="bulkModalType"
        @close="showBulkModal = false"
        @success="handleBulkSuccess"
    />
</template>
