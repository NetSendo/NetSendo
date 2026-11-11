<script setup>
import { computed, ref } from "vue";
import { useI18n } from "vue-i18n";
import {
    Chart as ChartJS,
    BarController,
    BarElement,
    CategoryScale,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
} from "chart.js";
import { Bar } from "vue-chartjs";
import Panel from "@/Components/Ui/Panel.vue";
import { useTheme } from "@/Composables/useTheme";

ChartJS.register(
    BarController,
    BarElement,
    CategoryScale,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
);

const { t, locale } = useI18n();
const { isDark } = useTheme();

const props = defineProps({
    // [{ date: 'YYYY-MM-DD', emails, opens, clicks, subscribers }]
    data: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
});

// Sent mail is the volume (bars); what recipients did with it is drawn as lines over it
const seriesConfig = computed(() => [
    {
        key: "emails",
        label: t("dashboard.activity.sent"),
        type: "bar",
        color: isDark.value ? "#818cf8" : "#4f46e5",
    },
    {
        key: "opens",
        label: t("dashboard.activity.opens"),
        type: "line",
        color: isDark.value ? "#fbbf24" : "#b45309",
    },
    {
        key: "clicks",
        label: t("dashboard.activity.clicks"),
        type: "line",
        color: isDark.value ? "#34d399" : "#047857",
    },
    {
        key: "subscribers",
        label: t("dashboard.activity.new_subscribers"),
        type: "line",
        dashed: true,
        color: isDark.value ? "#cbd5e1" : "#475569",
    },
]);

const visible = ref(["emails", "opens"]);

const toggle = (key) => {
    if (!visible.value.includes(key)) {
        visible.value = [...visible.value, key];
    } else if (visible.value.length > 1) {
        visible.value = visible.value.filter((k) => k !== key);
    }
};

const hasData = computed(() =>
    props.data.some((day) => day.emails || day.opens || day.clicks || day.subscribers),
);

// Dates arrive as calendar days of the user; noon keeps them on that day in any browser zone
const asDate = (day) => new Date(`${day}T12:00:00`);
const shortDate = (day) =>
    asDate(day).toLocaleDateString(locale.value, { day: "numeric", month: "short" });
const longDate = (day) =>
    asDate(day).toLocaleDateString(locale.value, {
        weekday: "long",
        day: "numeric",
        month: "long",
    });

const chartData = computed(() => ({
    labels: props.data.map((day) => shortDate(day.date)),
    datasets: seriesConfig.value
        .filter((series) => visible.value.includes(series.key))
        .map((series) => ({
            type: series.type,
            label: series.label,
            data: props.data.map((day) => day[series.key] || 0),
            backgroundColor: series.color,
            borderColor: series.color,
            borderWidth: series.type === "line" ? 2 : 0,
            borderDash: series.dashed ? [5, 4] : [],
            borderRadius: 3,
            maxBarThickness: 28,
            pointRadius: 0,
            pointHoverRadius: 4,
            // Monotone curves never dip below zero between two days
            cubicInterpolationMode: "monotone",
            // Lines are drawn over the bars
            order: series.type === "line" ? 0 : 1,
        })),
}));

const chartOptions = computed(() => {
    const grid = isDark.value ? "rgba(148, 163, 184, 0.14)" : "#e2e8f0";
    const ticks = isDark.value ? "#94a3b8" : "#475569";
    const font = { family: "Figtree, ui-sans-serif, system-ui, sans-serif", size: 12 };

    return {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 250 },
        interaction: { mode: "index", intersect: false },
        plugins: {
            legend: { display: false },
            datalabels: { display: false },
            tooltip: {
                backgroundColor: isDark.value ? "#f8fafc" : "#0f172a",
                titleColor: isDark.value ? "#0f172a" : "#ffffff",
                bodyColor: isDark.value ? "#0f172a" : "#ffffff",
                padding: 12,
                cornerRadius: 10,
                boxPadding: 4,
                titleFont: font,
                bodyFont: font,
                callbacks: {
                    title: (items) => longDate(props.data[items[0].dataIndex].date),
                    label: (item) =>
                        ` ${item.dataset.label}: ${new Intl.NumberFormat(locale.value).format(item.raw)}`,
                },
            },
        },
        scales: {
            x: {
                grid: { display: false },
                border: { color: grid },
                ticks: { color: ticks, font, maxRotation: 0, autoSkip: true, maxTicksLimit: 8 },
            },
            y: {
                beginAtZero: true,
                border: { display: false },
                grid: { color: grid },
                ticks: {
                    color: ticks,
                    font,
                    precision: 0,
                    maxTicksLimit: 5,
                    callback: (value) =>
                        new Intl.NumberFormat(locale.value, { notation: "compact" }).format(value),
                },
            },
        },
    };
});
</script>

<template>
    <Panel :title="$t('dashboard.activity.heading')">
        <template #actions>
            <div
                role="group"
                :aria-label="$t('dashboard.activity.series')"
                class="flex flex-wrap gap-1.5"
            >
                <button
                    v-for="series in seriesConfig"
                    :key="series.key"
                    type="button"
                    :aria-pressed="visible.includes(series.key)"
                    class="inline-flex min-h-8 items-center gap-1.5 rounded-lg border px-2.5 text-xs font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
                    :class="
                        visible.includes(series.key)
                            ? 'border-slate-300 bg-slate-50 text-slate-900 dark:border-slate-600 dark:bg-slate-700/60 dark:text-white'
                            : 'border-transparent text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'
                    "
                    @click="toggle(series.key)"
                >
                    <span
                        aria-hidden="true"
                        class="h-2.5 w-2.5 rounded-sm"
                        :class="{ 'opacity-40': !visible.includes(series.key) }"
                        :style="{ backgroundColor: series.color }"
                    ></span>
                    {{ series.label }}
                </button>
            </div>
        </template>

        <!-- The panel stretches to its neighbour's height; the chart fills whatever it gets -->
        <div
            class="relative min-h-72 flex-1"
            :class="{ 'opacity-60 transition-opacity': loading && data.length }"
        >
            <div
                v-if="!data.length"
                class="absolute inset-0 animate-pulse rounded-xl bg-slate-100 dark:bg-slate-700/40"
            ></div>
            <div
                v-else-if="!hasData"
                class="absolute inset-0 flex flex-col items-center justify-center gap-1 text-center"
            >
                <p class="text-sm font-medium text-slate-900 dark:text-white">
                    {{ $t("dashboard.activity.empty_title") }}
                </p>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    {{ $t("dashboard.activity.empty_period") }}
                </p>
            </div>
            <div v-else class="absolute inset-0">
                <Bar
                    :data="chartData"
                    :options="chartOptions"
                    role="img"
                    :aria-label="$t('dashboard.activity.chart_label')"
                />
            </div>
        </div>
    </Panel>
</template>
