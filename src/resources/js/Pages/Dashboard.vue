<script setup>
import { ref, onMounted, computed } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import StatsCard from "@/Components/Dashboard/StatsCard.vue";
import ActivityChart from "@/Components/Dashboard/ActivityChart.vue";
import RecentCampaigns from "@/Components/Dashboard/RecentCampaigns.vue";
import QuickActions from "@/Components/Dashboard/QuickActions.vue";
import SetupTrackerBar from "@/Components/Dashboard/SetupTrackerBar.vue";
import HealthScoreWidget from "@/Components/Dashboard/HealthScoreWidget.vue";
import { useDateTime } from "@/Composables/useDateTime";

const page = usePage();
const {
    userTimezone,
    getCurrentTimeFormatted,
    getCurrentDateFormatted,
    startClock,
    formatDateTime,
} = useDateTime();

// CRON Status
const cronStatus = ref(null);
const loadingCronStatus = ref(true);

// Dashboard Stats
const dashboardStats = ref(null);
const loadingStats = ref(true);

// Clock display
const currentTimeDisplay = ref("");
const currentDateDisplay = ref("");

const updateClockDisplay = () => {
    currentTimeDisplay.value = getCurrentTimeFormatted();
    currentDateDisplay.value = getCurrentDateFormatted();
};

const fetchDashboardStats = async () => {
    try {
        const response = await fetch(route("api.dashboard.stats"));
        if (response.ok) {
            dashboardStats.value = await response.json();
        }
    } catch (e) {
        // Use fallback values
        dashboardStats.value = {
            subscribers: { total: 0, formatted: "0", trend: 0 },
            emails_sent: { total: 0, formatted: "0", trend: 0 },
            open_rate: { value: 0, formatted: "0%", trend: 0 },
            click_rate: { value: 0, formatted: "0%", trend: 0 },
        };
    } finally {
        loadingStats.value = false;
    }
};

const fetchCronStatus = async () => {
    try {
        const response = await fetch(route("settings.cron.status"));
        if (response.ok) {
            cronStatus.value = await response.json();
        }
    } catch (e) {
        // Ignore - non-critical
    } finally {
        loadingCronStatus.value = false;
    }
};

onMounted(() => {
    fetchDashboardStats();
    fetchCronStatus();

    // Start clock updates
    startClock();
    updateClockDisplay();

    // Update display every second
    setInterval(updateClockDisplay, 1000);
});
</script>

<template>
    <Head :title="$t('navigation.dashboard')" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                    {{ $t("navigation.dashboard") }}
                </h1>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{
                        $t("dashboard.welcome_back", {
                            name: $page.props.auth.user.name,
                        })
                    }}
                </p>
            </div>
        </template>

        <!-- Setup Tracker Bar (Critical Logic) -->
        <SetupTrackerBar :stats="dashboardStats" :cron-status="cronStatus" />

        <!-- License inactive alert -->
        <div
            v-if="!$page.props.license?.active"
            class="mb-6 overflow-hidden rounded-2xl bg-gradient-to-r from-amber-500 to-orange-500 p-0.5 shadow-lg"
        >
            <div class="rounded-xl bg-white p-5 dark:bg-slate-900">
                <div
                    class="flex flex-col items-start gap-4 md:flex-row md:items-center md:justify-between"
                >
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-orange-500"
                        >
                            <svg
                                class="h-7 w-7 text-white"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                />
                            </svg>
                        </div>
                        <div>
                            <h3
                                class="text-lg font-bold text-slate-900 dark:text-white"
                            >
                                {{ $t("dashboard.license_inactive") }}
                            </h3>
                            <p class="text-slate-600 dark:text-slate-400">
                                {{
                                    $t("dashboard.license_activate_description")
                                }}
                            </p>
                        </div>
                    </div>
                    <Link
                        :href="route('license.index')"
                        class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-3 font-semibold text-white shadow-lg transition-all hover:shadow-xl hover:shadow-indigo-500/25"
                    >
                        <svg
                            class="h-5 w-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"
                            />
                        </svg>
                        {{ $t("dashboard.activate_now") }}
                    </Link>
                </div>
            </div>
        </div>

        <!-- License active badge + Clock Widget Row -->
        <div v-else class="mb-6 grid gap-4 lg:grid-cols-2">
            <!-- License active badge -->
            <div
                class="flex items-center gap-3 rounded-xl bg-emerald-50 px-4 py-3 dark:bg-emerald-900/20 h-full"
            >
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/30"
                >
                    <svg
                        class="h-5 w-5 text-emerald-600 dark:text-emerald-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                        />
                    </svg>
                </div>
                <div>
                    <span
                        class="font-medium text-emerald-700 dark:text-emerald-300"
                    >
                        {{ $t("dashboard.license_active") }}
                    </span>
                    <span
                        class="ml-2 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold"
                        :class="{
                            'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400':
                                $page.props.license?.plan === 'GOLD',
                            'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300':
                                $page.props.license?.plan === 'SILVER',
                        }"
                    >
                        {{ $page.props.license?.plan }}
                    </span>
                </div>
            </div>

            <!-- Clock Widget -->
            <div
                class="overflow-hidden rounded-2xl bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-500 p-0.5 shadow-lg"
            >
                <div
                    class="rounded-xl bg-white/95 backdrop-blur-sm p-5 dark:bg-slate-900/95 h-full"
                >
                    <div
                        class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 shadow-lg shadow-indigo-500/25"
                            >
                                <svg
                                    class="h-7 w-7 text-white"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                            </div>
                            <div>
                                <p
                                    class="text-sm text-slate-500 dark:text-slate-400"
                                >
                                    {{ $t("dashboard.clock.title") }}
                                </p>
                                <p
                                    class="text-3xl font-bold tracking-tight bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent dark:from-indigo-400 dark:to-purple-400"
                                >
                                    {{ currentTimeDisplay }}
                                </p>
                            </div>
                        </div>
                        <div class="sm:text-right">
                            <p
                                class="text-sm text-slate-600 dark:text-slate-300"
                            >
                                {{ currentDateDisplay }}
                            </p>
                            <p
                                class="text-xs text-slate-400 dark:text-slate-500 mt-1 flex items-center sm:justify-end gap-1"
                            >
                                <svg
                                    class="h-3.5 w-3.5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                                {{ $t("dashboard.clock.timezone_label") }}:
                                <span class="font-medium">{{
                                    userTimezone
                                }}</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Flash success message -->
        <div
            v-if="$page.props.flash?.success"
            class="mb-6 flex items-center gap-3 rounded-xl bg-emerald-50 p-4 dark:bg-emerald-900/20"
        >
            <svg
                class="h-5 w-5 text-emerald-600 dark:text-emerald-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M5 13l4 4L19 7"
                />
            </svg>
            <span class="text-emerald-700 dark:text-emerald-300">
                {{ $page.props.flash.success }}
            </span>
        </div>

        <!-- Stats Grid -->
        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatsCard
                :title="$t('dashboard.stats.subscribers')"
                :value="dashboardStats?.subscribers?.formatted || '0'"
                :trend="dashboardStats?.subscribers?.trend || 0"
                :loading="loadingStats"
                color="indigo"
            >
                <template #icon>
                    <svg
                        class="h-6 w-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                        />
                    </svg>
                </template>
            </StatsCard>

            <StatsCard
                :title="$t('dashboard.stats.sent_emails')"
                :value="dashboardStats?.emails_sent?.formatted || '0'"
                :trend="dashboardStats?.emails_sent?.trend || 0"
                :loading="loadingStats"
                color="emerald"
            >
                <template #icon>
                    <svg
                        class="h-6 w-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                        />
                    </svg>
                </template>
            </StatsCard>

            <StatsCard
                :title="$t('dashboard.stats.open_rate')"
                :value="dashboardStats?.open_rate?.formatted || '0%'"
                :trend="dashboardStats?.open_rate?.trend || 0"
                :loading="loadingStats"
                color="amber"
            >
                <template #icon>
                    <svg
                        class="h-6 w-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                        />
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                        />
                    </svg>
                </template>
            </StatsCard>

            <StatsCard
                :title="$t('dashboard.stats.click_rate')"
                :value="dashboardStats?.click_rate?.formatted || '0%'"
                :trend="dashboardStats?.click_rate?.trend || 0"
                :loading="loadingStats"
                color="rose"
            >
                <template #icon>
                    <svg
                        class="h-6 w-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"
                        />
                    </svg>
                </template>
            </StatsCard>
        </div>

        <!-- Main content grid -->
        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Activity Chart (spans 2 columns) -->
            <div class="lg:col-span-2">
                <ActivityChart
                    :data="dashboardStats?.activity_chart || []"
                    :loading="loadingStats"
                />
            </div>

            <!-- Quick Actions + Health Score -->
            <div class="space-y-6">
                <QuickActions />
                <HealthScoreWidget />
            </div>
        </div>

        <!-- CRON Status Widget -->
        <div class="mt-6">
            <div
                class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800"
            >
                <div class="flex items-center justify-between mb-4">
                    <h3
                        class="text-lg font-semibold text-slate-900 dark:text-white flex items-center gap-2"
                    >
                        {{ $t("dashboard.cron.status_title") }}
                    </h3>
                    <Link
                        :href="route('settings.cron.index')"
                        class="text-sm text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                    >
                        {{ $t("dashboard.cron.settings_link") }}
                    </Link>
                </div>

                <!-- Loading state -->
                <div
                    v-if="loadingCronStatus"
                    class="flex items-center justify-center py-8"
                >
                    <svg
                        class="animate-spin h-6 w-6 text-indigo-500"
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

                <!-- CRON Active -->
                <div v-else-if="cronStatus?.has_ever_run" class="space-y-4">
                    <div class="flex items-center gap-3">
                        <span class="flex h-3 w-3 relative">
                            <span
                                class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"
                            ></span>
                            <span
                                class="relative inline-flex rounded-full h-3 w-3 bg-green-500"
                            ></span>
                        </span>
                        <span
                            class="text-lg font-medium text-green-600 dark:text-green-400"
                            >{{ $t("dashboard.cron.active") }}</span
                        >
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                        <div
                            class="bg-slate-50 dark:bg-slate-700/50 rounded-lg p-3 text-center"
                        >
                            <div
                                class="text-2xl font-bold text-indigo-600 dark:text-indigo-400"
                            >
                                {{ cronStatus.stats_24h?.total_runs || 0 }}
                            </div>
                            <div class="text-slate-500 dark:text-slate-400">
                                {{ $t("dashboard.cron.stats.runs_24h") }}
                            </div>
                        </div>
                        <div
                            class="bg-slate-50 dark:bg-slate-700/50 rounded-lg p-3 text-center"
                        >
                            <div
                                class="text-2xl font-bold text-green-600 dark:text-green-400"
                            >
                                {{ cronStatus.stats_24h?.emails_sent || 0 }}
                            </div>
                            <div class="text-slate-500 dark:text-slate-400">
                                {{ $t("dashboard.cron.stats.sent_emails") }}
                            </div>
                        </div>
                        <div
                            class="bg-slate-50 dark:bg-slate-700/50 rounded-lg p-3 text-center"
                        >
                            <div
                                class="text-2xl font-bold text-red-600 dark:text-red-400"
                            >
                                {{ cronStatus.stats_24h?.failed || 0 }}
                            </div>
                            <div class="text-slate-500 dark:text-slate-400">
                                {{ $t("dashboard.cron.stats.errors") }}
                            </div>
                        </div>
                        <div
                            class="bg-slate-50 dark:bg-slate-700/50 rounded-lg p-3 text-center"
                        >
                            <div
                                class="text-xs font-medium text-slate-700 dark:text-slate-300"
                            >
                                {{ formatDateTime(cronStatus.last_run) }}
                            </div>
                            <div class="text-slate-500 dark:text-slate-400">
                                {{ $t("dashboard.cron.stats.last_run") }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CRON Not Configured -->
                <div v-else class="flex flex-col items-center py-6 text-center">
                    <div
                        class="flex h-16 w-16 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/20 mb-4"
                    >
                        <svg
                            class="h-8 w-8 text-red-500"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                            />
                        </svg>
                    </div>
                    <h4
                        class="text-lg font-medium text-slate-900 dark:text-white mb-2"
                    >
                        {{ $t("dashboard.cron.not_configured") }}
                    </h4>
                    <p
                        class="text-sm text-slate-500 dark:text-slate-400 mb-4 max-w-md"
                    >
                        {{ $t("dashboard.cron.not_configured_desc") }}
                    </p>
                    <Link
                        :href="
                            route('settings.cron.index') + '?tab=instructions'
                        "
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 transition-colors"
                    >
                        {{ $t("dashboard.cron.view_instructions") }}
                    </Link>
                </div>
            </div>
        </div>

        <!-- Recent Campaigns -->
        <div class="mt-6">
            <RecentCampaigns
                :campaigns="dashboardStats?.recent_campaigns || []"
                :loading="loadingStats"
            />
        </div>
    </AuthenticatedLayout>
</template>
