<script setup>
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import Sparkline from "@/Components/Ui/Sparkline.vue";

const { t, locale } = useI18n();

const props = defineProps({
    stats: { type: Object, default: null },
    loading: { type: Boolean, default: false },
});

const number = (value) => new Intl.NumberFormat(locale.value).format(value ?? 0);
const decimal = (value) =>
    new Intl.NumberFormat(locale.value, { maximumFractionDigits: 1 }).format(
        Math.abs(value ?? 0),
    );
const percent = (value) => `${decimal(value)}%`;

const series = (key) => (props.stats?.activity_chart || []).map((day) => day[key]);

/**
 * A change is shown only when there is something to compare with: "+100%" next to a previous
 * value of zero says nothing.
 */
const countChange = (trend, previous) =>
    previous > 0 && trend !== 0
        ? { up: trend > 0, text: `${decimal(trend)}%` }
        : null;
const pointChange = (trend) =>
    trend !== 0
        ? { up: trend > 0, text: `${decimal(trend)} ${t("dashboard.kpi.points")}` }
        : null;

const tiles = computed(() => {
    const s = props.stats;
    if (!s) return [];

    return [
        {
            key: "subscribers",
            label: t("dashboard.kpi.subscribers"),
            value: number(s.subscribers.total),
            series: series("subscribers"),
            change: countChange(s.subscribers.trend, s.subscribers.new_previous),
            caption: t("dashboard.kpi.new_subscribers", {
                count: number(s.subscribers.new),
                previous: number(s.subscribers.new_previous),
            }),
        },
        {
            key: "emails",
            label: t("dashboard.kpi.sent"),
            value: number(s.emails_sent.total),
            series: series("emails"),
            change: countChange(s.emails_sent.trend, s.emails_sent.previous),
            caption: t("dashboard.kpi.previously", {
                value: number(s.emails_sent.previous),
            }),
        },
        {
            key: "opens",
            label: t("dashboard.kpi.open_rate"),
            value: percent(s.open_rate.value),
            series: series("opens"),
            change: pointChange(s.open_rate.trend),
            caption: t("dashboard.kpi.previously", {
                value: percent(s.open_rate.previous),
            }),
        },
        {
            key: "clicks",
            label: t("dashboard.kpi.click_rate"),
            value: percent(s.click_rate.value),
            series: series("clicks"),
            change: pointChange(s.click_rate.trend),
            caption: t("dashboard.kpi.previously", {
                value: percent(s.click_rate.previous),
            }),
        },
    ];
});
</script>

<template>
    <section
        :aria-label="$t('dashboard.kpi.title')"
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700/60 dark:bg-slate-800/50"
    >
        <!-- The -m-px grid with a top and left border per tile draws hairlines between tiles
             however they wrap, and hides the outer ones under the rounded frame -->
        <div
            class="-m-px grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4"
            :class="{ 'opacity-60 transition-opacity': loading && stats }"
        >
            <template v-if="stats">
                <div
                    v-for="tile in tiles"
                    :key="tile.key"
                    class="flex flex-col gap-2 border-l border-t border-slate-200 p-5 dark:border-slate-700/60"
                >
                    <div
                        class="text-sm font-medium text-slate-600 dark:text-slate-400"
                    >
                        {{ tile.label }}
                    </div>
                    <div class="flex items-end justify-between gap-4">
                        <div
                            class="text-3xl font-semibold leading-none tracking-tight tabular-nums text-slate-900 dark:text-white"
                        >
                            {{ tile.value }}
                        </div>
                        <Sparkline
                            :values="tile.series"
                            class="h-8 w-24 shrink-0 text-indigo-600 dark:text-indigo-400"
                        />
                    </div>
                    <div
                        class="flex min-h-5 flex-wrap items-center gap-x-2 text-xs text-slate-600 dark:text-slate-400"
                    >
                        <span
                            v-if="tile.change"
                            class="inline-flex items-center gap-0.5 font-semibold tabular-nums"
                            :class="
                                tile.change.up
                                    ? 'text-emerald-700 dark:text-emerald-400'
                                    : 'text-rose-700 dark:text-rose-400'
                            "
                        >
                            <svg
                                aria-hidden="true"
                                class="h-3.5 w-3.5"
                                :class="{ 'rotate-180': !tile.change.up }"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M12 19V5M5 12l7-7 7 7" />
                            </svg>
                            <span class="sr-only">{{
                                tile.change.up
                                    ? $t("dashboard.kpi.up")
                                    : $t("dashboard.kpi.down")
                            }}</span>
                            {{ tile.change.text }}
                        </span>
                        <span class="tabular-nums">{{ tile.caption }}</span>
                    </div>
                </div>
            </template>

            <template v-else>
                <div
                    v-for="n in 4"
                    :key="n"
                    class="flex flex-col gap-3 border-l border-t border-slate-200 p-5 dark:border-slate-700/60"
                >
                    <div
                        class="h-4 w-28 animate-pulse rounded bg-slate-200 dark:bg-slate-700"
                    ></div>
                    <div
                        class="h-8 w-24 animate-pulse rounded bg-slate-200 dark:bg-slate-700"
                    ></div>
                    <div
                        class="h-3 w-32 animate-pulse rounded bg-slate-200 dark:bg-slate-700"
                    ></div>
                </div>
            </template>
        </div>
    </section>
</template>
