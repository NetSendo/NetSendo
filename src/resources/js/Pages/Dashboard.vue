<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import CreateMenu from "@/Components/CreateMenu.vue";
import SegmentedControl from "@/Components/Ui/SegmentedControl.vue";
import AttentionStrip from "@/Components/Dashboard/AttentionStrip.vue";
import KpiStrip from "@/Components/Dashboard/KpiStrip.vue";
import ActivityPanel from "@/Components/Dashboard/ActivityPanel.vue";
import BrainPanel from "@/Components/Dashboard/BrainPanel.vue";
import CampaignsTable from "@/Components/Dashboard/CampaignsTable.vue";
import ScheduledPanel from "@/Components/Dashboard/ScheduledPanel.vue";
import HealthPanel from "@/Components/Dashboard/HealthPanel.vue";
import SystemStatusLine from "@/Components/Dashboard/SystemStatusLine.vue";
import TelemetryNotice from "@/Components/Dashboard/TelemetryNotice.vue";
import { useDateTime } from "@/Composables/useDateTime";

/**
 * The dashboard answers three questions, top to bottom: does anything need my reaction,
 * how is sending going, and what should I do next.
 */
const { t } = useI18n();
const page = usePage();
const { userTimezone, getCurrentDateFormatted, formatRelative } = useDateTime();

const PERIODS = [7, 30, 90];
const PERIOD_KEY = "netsendo.dashboard.period";
// CRON runs every minute; a quarter of an hour of silence means it stopped
const CRON_STALE_MINUTES = 15;

const storedPeriod = () => {
    try {
        const value = Number(localStorage.getItem(PERIOD_KEY));
        return PERIODS.includes(value) ? value : 30;
    } catch {
        return 30;
    }
};

const period = ref(storedPeriod());
const stats = ref(null);
const loadingStats = ref(true);
const cron = ref(null);
const audit = ref(null);
const loadingAudit = ref(true);

const getJson = async (url) => {
    const response = await fetch(url, { headers: { Accept: "application/json" } });
    if (!response.ok) throw new Error(String(response.status));
    return response.json();
};

const loadStats = async () => {
    loadingStats.value = true;
    const requested = period.value;

    try {
        const data = await getJson(route("api.dashboard.stats", { period: requested }));
        // A slower answer for a period the user already left must not overwrite the newer one
        if (requested === period.value) stats.value = data;
    } catch {
        // The numbers stay as they were; the panels keep their loading or last state
    } finally {
        if (requested === period.value) loadingStats.value = false;
    }
};

const loadCron = async () => {
    try {
        cron.value = await getJson(route("settings.cron.status"));
    } catch {
        cron.value = null;
    }
};

const loadAudit = async () => {
    try {
        audit.value = await getJson(route("api.campaign-auditor.dashboard-widget"));
    } catch {
        audit.value = null;
    } finally {
        loadingAudit.value = false;
    }
};

watch(period, (value) => {
    try {
        localStorage.setItem(PERIOD_KEY, String(value));
    } catch {
        // Private mode: the choice simply lasts for this visit
    }
    loadStats();
});

onMounted(() => {
    loadStats();
    loadCron();
    loadAudit();
});

const periodOptions = computed(() =>
    PERIODS.map((days) => ({ value: days, label: t("dashboard.period.days", { count: days }) })),
);

const greeting = computed(() => {
    const hour = Number(
        new Intl.DateTimeFormat("en-GB", {
            hour: "numeric",
            hour12: false,
            timeZone: userTimezone.value,
        }).format(new Date()),
    );
    const part = hour < 12 ? "morning" : hour < 18 ? "afternoon" : "evening";
    const firstName = (page.props.auth.user.name || "").trim().split(/\s+/)[0];

    return t(`dashboard.greeting.${part}`, { name: firstName });
});

const cronState = computed(() => {
    if (!cron.value) return null;
    if (!cron.value.has_ever_run) return "never";
    if (!cron.value.last_run) return "ok";

    const minutes = (Date.now() - new Date(cron.value.last_run).getTime()) / 60000;
    return minutes > CRON_STALE_MINUTES ? "stale" : "ok";
});

const attention = computed(() => {
    const items = [];
    const cronSettings = route("settings.cron.index");

    if (!page.props.license?.active) {
        items.push({
            id: "license",
            text: t("dashboard.attention.license"),
            action: t("dashboard.activate_now"),
            href: route("license.index"),
        });
    }

    if (cronState.value === "never") {
        items.push({
            id: "cron-never",
            text: t("dashboard.cron.not_configured_desc"),
            action: t("dashboard.attention.cron_instructions"),
            href: cronSettings + "?tab=instructions",
        });
    } else if (cronState.value === "stale") {
        items.push({
            id: "cron-stale",
            text: t("dashboard.attention.cron_stale", {
                when: formatRelative(cron.value.last_run),
            }),
            action: t("dashboard.attention.check"),
            href: cronSettings,
        });
    }

    const failedRuns = cron.value?.stats_24h?.failed || 0;
    if (failedRuns > 0) {
        items.push({
            id: "cron-failed",
            text: t("dashboard.attention.cron_failed", { count: failedRuns }),
            action: t("dashboard.attention.check"),
            href: cronSettings,
        });
    }

    if (stats.value && !stats.value.mailboxes_count) {
        items.push({
            id: "mailbox",
            text: t("dashboard.attention.no_mailbox"),
            action: t("dashboard.attention.add_mailbox"),
            href: route("settings.mailboxes.index"),
        });
    }

    if (stats.value && !stats.value.ai_integrations_count) {
        items.push({
            id: "ai",
            text: t("dashboard.attention.no_ai"),
            action: t("dashboard.attention.connect_ai"),
            href: route("settings.ai-integrations.index"),
        });
    }

    if (audit.value?.hasAudit && audit.value.criticalCount > 0) {
        items.push({
            id: "audit",
            text: t("dashboard.attention.audit_critical", {
                count: audit.value.criticalCount,
            }),
            action: t("dashboard.attention.see"),
            href: route("campaign-auditor.index"),
        });
    }

    return items;
});
</script>

<template>
    <Head :title="$t('navigation.dashboard')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <h1 class="truncate text-xl font-semibold text-slate-900 dark:text-white">
                        {{ greeting }}
                    </h1>
                    <p class="truncate text-sm text-slate-600 dark:text-slate-400">
                        {{ getCurrentDateFormatted() }}
                    </p>
                </div>
                <CreateMenu class="shrink-0" />
            </div>
        </template>

        <div class="mx-auto flex max-w-screen-2xl flex-col gap-5">
            <!-- One-time question: opt-in anonymous usage report (administrator only) -->
            <TelemetryNotice />

            <div
                v-if="$page.props.flash?.success"
                role="status"
                class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300"
            >
                {{ $page.props.flash.success }}
            </div>

            <AttentionStrip :items="attention" />

            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">
                        {{ $t("dashboard.overview.title") }}
                    </h2>
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        {{ $t("dashboard.overview.compared", { count: period }) }}
                    </p>
                </div>
                <SegmentedControl
                    v-model="period"
                    :options="periodOptions"
                    :label="$t('dashboard.period.label')"
                />
            </div>

            <KpiStrip :stats="stats" :loading="loadingStats" />

            <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
                <ActivityPanel
                    class="xl:col-span-2"
                    :data="stats?.activity_chart || []"
                    :loading="loadingStats"
                />
                <BrainPanel />
            </div>

            <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
                <CampaignsTable
                    class="xl:col-span-2"
                    :campaigns="stats ? stats.recent_campaigns : null"
                    :loading="loadingStats"
                />
                <div class="flex min-w-0 flex-col gap-5">
                    <ScheduledPanel :items="stats ? stats.scheduled : null" />
                    <HealthPanel :audit="audit" :loading="loadingAudit" />
                </div>
            </div>

            <SystemStatusLine :cron="cron" :cron-state="cronState" :stats="stats" />
        </div>
    </AuthenticatedLayout>
</template>
