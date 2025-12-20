<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

const props = defineProps({
    stats: Object,
    year: Number,
    month: Number,
    availableYears: Array,
});

const selectedYear = ref(props.year);
const selectedMonth = ref(props.month);
const loading = ref(false);

const months = [
    { value: 1, label: t('common.months.january') },
    { value: 2, label: t('common.months.february') },
    { value: 3, label: t('common.months.march') },
    { value: 4, label: t('common.months.april') },
    { value: 5, label: t('common.months.may') },
    { value: 6, label: t('common.months.june') },
    { value: 7, label: t('common.months.july') },
    { value: 8, label: t('common.months.august') },
    { value: 9, label: t('common.months.september') },
    { value: 10, label: t('common.months.october') },
    { value: 11, label: t('common.months.november') },
    { value: 12, label: t('common.months.december') },
];

const currentMonthName = computed(() => {
    return months.find(m => m.value === props.month)?.label || '';
});

const canGoNext = computed(() => {
    const now = new Date();
    return !(props.year === now.getFullYear() && props.month === now.getMonth() + 1);
});

const canGoPrev = computed(() => {
    const oldest = props.availableYears[props.availableYears.length - 1];
    return !(props.year === oldest && props.month === 1);
});

const goToPrevMonth = () => {
    if (!canGoPrev.value) return;
    let newMonth = props.month - 1;
    let newYear = props.year;
    if (newMonth < 1) {
        newMonth = 12;
        newYear--;
    }
    navigateToMonth(newYear, newMonth);
};

const goToNextMonth = () => {
    if (!canGoNext.value) return;
    let newMonth = props.month + 1;
    let newYear = props.year;
    if (newMonth > 12) {
        newMonth = 1;
        newYear++;
    }
    navigateToMonth(newYear, newMonth);
};

const navigateToMonth = (year, month) => {
    loading.value = true;
    router.get(route('settings.stats.index'), { year, month }, {
        preserveState: true,
        onFinish: () => loading.value = false,
    });
};

const exportStats = () => {
    window.location.href = route('settings.stats.export', { year: props.year, month: props.month });
};

// Watch for dropdown changes
watch([selectedYear, selectedMonth], ([year, month]) => {
    if (year !== props.year || month !== props.month) {
        navigateToMonth(year, month);
    }
});

// Format numbers
const formatNumber = (n) => {
    return new Intl.NumberFormat(locale.value).format(n || 0);
};

// Calculate max value for chart scaling
const chartMaxValue = computed(() => {
    if (!props.stats?.daily) return 100;
    const maxSubs = Math.max(...props.stats.daily.map(d => d.new_subscribers));
    const maxOpens = Math.max(...props.stats.daily.map(d => d.opens));
    return Math.max(maxSubs, maxOpens, 10);
});

const getBarHeight = (value, max) => {
    if (!max) return '0%';
    return Math.round((value / max) * 100) + '%';
};
</script>

<template>
    <Head :title="t('settings.global_stats')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                        📊 {{ t('settings.global_stats') }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ t('settings.stats.stats_for') }} {{ currentMonthName }} {{ year }}
                    </p>
                </div>
                <button 
                    @click="exportStats"
                    class="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    {{ t('settings.stats.export_csv') }}
                </button>
            </div>
        </template>

        <!-- Month Navigation -->
        <div class="mb-6 flex items-center justify-between rounded-xl bg-white p-4 shadow-sm dark:bg-slate-800">
            <button 
                @click="goToPrevMonth"
                :disabled="!canGoPrev || loading"
                class="flex items-center gap-2 rounded-lg px-4 py-2 font-medium transition-colors disabled:opacity-50"
                :class="canGoPrev ? 'text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-900/20' : 'text-slate-400'"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                {{ t('settings.stats.prev_month') }}
            </button>

            <div class="flex items-center gap-3">
                <select 
                    v-model="selectedMonth"
                    class="rounded-lg border-slate-300 bg-transparent text-sm font-semibold focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800"
                >
                    <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
                </select>
                <select 
                    v-model="selectedYear"
                    class="rounded-lg border-slate-300 bg-transparent text-sm font-semibold focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800"
                >
                    <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
                </select>
            </div>

            <button 
                @click="goToNextMonth"
                :disabled="!canGoNext || loading"
                class="flex items-center gap-2 rounded-lg px-4 py-2 font-medium transition-colors disabled:opacity-50"
                :class="canGoNext ? 'text-indigo-600 hover:bg-indigo-50 dark:text-indigo-400 dark:hover:bg-indigo-900/20' : 'text-slate-400'"
            >
                {{ t('settings.stats.next_month') }}
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>

        <!-- Loading overlay -->
        <div v-if="loading" class="fixed inset-0 z-50 flex items-center justify-center bg-black/20">
            <div class="rounded-xl bg-white p-6 shadow-xl dark:bg-slate-800">
                <svg class="h-8 w-8 animate-spin text-indigo-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- New Subscribers -->
            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-slate-800">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ t('settings.stats.new_subscribers') }}</p>
                        <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ formatNumber(stats?.summary?.new_subscribers) }}</p>
                    </div>
                </div>
            </div>

            <!-- Emails Sent -->
            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-slate-800">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ t('settings.stats.emails_sent') }}</p>
                        <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ formatNumber(stats?.summary?.emails_sent) }}</p>
                    </div>
                </div>
            </div>

            <!-- Open Rate -->
            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-slate-800">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500 to-orange-600">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ t('settings.stats.open_rate') }}</p>
                        <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ stats?.summary?.open_rate || 0 }}%</p>
                        <p class="text-xs text-slate-400">{{ formatNumber(stats?.summary?.opens) }} {{ t('settings.stats.opens_count') }}</p>
                    </div>
                </div>
            </div>

            <!-- Click Rate -->
            <div class="rounded-xl bg-white p-6 shadow-sm dark:bg-slate-800">
                <div class="flex items-center gap-4">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-rose-500 to-pink-600">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ t('settings.stats.click_rate') }}</p>
                        <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ stats?.summary?.click_rate || 0 }}%</p>
                        <p class="text-xs text-slate-400">{{ formatNumber(stats?.summary?.clicks) }} {{ t('settings.stats.clicks_count') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Stats -->
        <div class="mb-6 flex gap-4">
            <div class="flex items-center gap-2 rounded-lg bg-red-50 px-4 py-2 dark:bg-red-900/20">
                <span class="text-sm text-red-600 dark:text-red-400">{{ t('settings.stats.table.unsubscribed') }}:</span>
                <span class="font-bold text-red-700 dark:text-red-300">{{ formatNumber(stats?.summary?.unsubscribed) }}</span>
            </div>
            <div class="flex items-center gap-2 rounded-lg bg-slate-100 px-4 py-2 dark:bg-slate-700">
                <span class="text-sm text-slate-600 dark:text-slate-400">{{ t('settings.stats.deleted') }}:</span>
                <span class="font-bold text-slate-700 dark:text-slate-300">{{ formatNumber(stats?.summary?.deleted) }}</span>
            </div>
        </div>

        <!-- Daily Chart -->
        <div class="mb-6 rounded-xl bg-white p-6 shadow-sm dark:bg-slate-800">
            <h3 class="mb-4 text-lg font-semibold text-slate-900 dark:text-white">{{ t('settings.stats.daily_trend') }}</h3>
            <div class="flex h-48 items-end gap-1 overflow-x-auto pb-6">
                <div 
                    v-for="day in stats?.daily" 
                    :key="day.date"
                    class="group relative flex min-w-[20px] flex-1 flex-col items-center"
                >
                    <!-- Bars -->
                    <div class="flex h-40 w-full items-end gap-0.5">
                        <div 
                            class="w-1/2 rounded-t bg-indigo-500 transition-all hover:bg-indigo-600"
                            :style="{ height: getBarHeight(day.new_subscribers, chartMaxValue) }"
                            :title="`${t('settings.stats.new')}: ${day.new_subscribers}`"
                        ></div>
                        <div 
                            class="w-1/2 rounded-t bg-emerald-500 transition-all hover:bg-emerald-600"
                            :style="{ height: getBarHeight(day.opens, chartMaxValue) }"
                            :title="`${t('settings.stats.table.opens')}: ${day.opens}`"
                        ></div>
                    </div>
                    <!-- Label -->
                    <span class="mt-1 text-[10px] text-slate-400">{{ day.label }}</span>
                    
                    <!-- Tooltip -->
                    <div class="pointer-events-none absolute bottom-full mb-2 hidden rounded-lg bg-slate-900 px-3 py-2 text-xs text-white shadow-lg group-hover:block">
                        <div class="font-medium">{{ day.date }}</div>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="h-2 w-2 rounded-full bg-indigo-500"></span>
                            {{ t('settings.stats.new') }}: {{ day.new_subscribers }}
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                            {{ t('settings.stats.table.opens') }}: {{ day.opens }}
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                            {{ t('settings.stats.table.clicks') }}: {{ day.clicks }}
                        </div>
                    </div>
                </div>
            </div>
            <!-- Legend -->
            <div class="flex items-center justify-center gap-6 border-t border-slate-200 pt-4 dark:border-slate-700">
                <div class="flex items-center gap-2">
                    <span class="h-3 w-3 rounded-full bg-indigo-500"></span>
                    <span class="text-sm text-slate-600 dark:text-slate-400">{{ t('settings.stats.new_subscribers') }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="h-3 w-3 rounded-full bg-emerald-500"></span>
                    <span class="text-sm text-slate-600 dark:text-slate-400">{{ t('settings.stats.table.opens') }}</span>
                </div>
            </div>
        </div>

        <!-- Per List Stats Table -->
        <div class="rounded-xl bg-white shadow-sm dark:bg-slate-800">
            <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-700">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ t('settings.stats.per_list') }}</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/50">
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ t('settings.stats.table.list') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ t('settings.stats.table.active') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ t('settings.stats.table.new') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ t('settings.stats.table.unsubscribed') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ t('settings.stats.table.opens') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ t('settings.stats.table.clicks') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        <tr 
                            v-for="list in stats?.lists" 
                            :key="list.id"
                            class="hover:bg-slate-50 dark:hover:bg-slate-700/50"
                        >
                            <td class="px-6 py-4">
                                <span class="font-medium text-slate-900 dark:text-white">{{ list.name }}</span>
                            </td>
                            <td class="px-6 py-4 text-right text-slate-600 dark:text-slate-400">
                                {{ formatNumber(list.active_subscribers) }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span class="text-emerald-600 dark:text-emerald-400">+{{ formatNumber(list.new_subscribers) }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span class="text-red-600 dark:text-red-400">-{{ formatNumber(list.unsubscribed) }}</span>
                            </td>
                            <td class="px-6 py-4 text-right text-slate-600 dark:text-slate-400">
                                {{ formatNumber(list.opens) }}
                            </td>
                            <td class="px-6 py-4 text-right text-slate-600 dark:text-slate-400">
                                {{ formatNumber(list.clicks) }}
                            </td>
                        </tr>
                        <tr v-if="!stats?.lists?.length">
                            <td colspan="6" class="px-6 py-8 text-center text-slate-500 dark:text-slate-400">
                                {{ t('settings.stats.no_lists') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
