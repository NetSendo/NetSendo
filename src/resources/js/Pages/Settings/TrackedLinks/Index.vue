<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import debounce from 'lodash/debounce';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

const props = defineProps({
    links: Object,
    stats: Object,
    trend: Array,
    messages: Array,
    filters: Object,
});

const filters = ref({
    message_id: props.filters?.message_id || '',
    from: props.filters?.from || '',
    to: props.filters?.to || '',
    search: props.filters?.search || '',
});

const applyFilters = debounce(() => {
    router.get(route('settings.tracked-links.index'), filters.value, {
        preserveState: true,
        preserveScroll: true,
    });
}, 300);

watch(filters, applyFilters, { deep: true });

const clearFilters = () => {
    filters.value = { message_id: '', from: '', to: '', search: '' };
    router.get(route('settings.tracked-links.index'));
};

const exportCsv = () => {
    const params = new URLSearchParams(filters.value);
    window.location.href = route('settings.tracked-links.export') + '?' + params.toString();
};

const truncateUrl = (url, maxLength = 60) => {
    if (!url) return '';
    return url.length > maxLength ? url.substring(0, maxLength) + '...' : url;
};

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString(locale.value, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

// Chart dimensions
const chartHeight = 120;
const chartMaxValue = computed(() => Math.max(...props.trend.map(d => d.clicks), 1));
</script>

<template>
    <Head :title="$t('tracked_links.title')" />

    <AuthenticatedLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ $t('tracked_links.title') }}
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ $t('tracked_links.subtitle') }}
                    </p>
                </div>
                <button
                    @click="exportCsv"
                    class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-700"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    {{ $t('common.export') }} CSV
                </button>
            </div>

            <!-- Stats Cards -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-slate-800">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/30">
                            <svg class="h-5 w-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ stats.total_clicks.toLocaleString() }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('tracked_links.total_clicks') }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-slate-800">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900/30">
                            <svg class="h-5 w-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ stats.unique_links.toLocaleString() }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('tracked_links.unique_links') }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-slate-800">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900/30">
                            <svg class="h-5 w-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ stats.unique_clickers.toLocaleString() }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('tracked_links.unique_clickers') }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-slate-800">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/30">
                            <svg class="h-5 w-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ stats.today_clicks.toLocaleString() }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $t('tracked_links.today_clicks') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Trend Chart -->
            <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-slate-800">
                <h3 class="mb-4 text-sm font-medium text-slate-900 dark:text-white">
                    {{ $t('tracked_links.trend_30_days') }}
                </h3>
                <div class="flex items-end gap-1" :style="{ height: chartHeight + 'px' }">
                    <div
                        v-for="(day, index) in trend"
                        :key="index"
                        class="group relative flex-1"
                        :title="`${day.label}: ${day.clicks} ${$t('tracked_links.clicks_count')}`"
                    >
                        <div
                            class="w-full rounded-t bg-indigo-500 transition-all hover:bg-indigo-600"
                            :style="{ height: (day.clicks / chartMaxValue * chartHeight) + 'px', minHeight: day.clicks > 0 ? '2px' : '0' }"
                        ></div>
                        <div class="absolute -top-6 left-1/2 -translate-x-1/2 rounded bg-slate-800 px-1.5 py-0.5 text-xs text-white opacity-0 transition group-hover:opacity-100 whitespace-nowrap z-10">
                            {{ day.clicks }}
                        </div>
                    </div>
                </div>
                <div class="mt-2 flex justify-between text-xs text-slate-400">
                    <span>{{ trend[0]?.label }}</span>
                    <span>{{ trend[trend.length - 1]?.label }}</span>
                </div>
            </div>

            <!-- Filters -->
            <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-slate-800">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">
                            {{ $t('tracked_links.search_url') }}
                        </label>
                        <input
                            v-model="filters.search"
                            type="text"
                            :placeholder="$t('tracked_links.search_placeholder')"
                            class="w-full rounded-lg border-slate-200 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">
                            {{ $t('tracked_links.message') }}
                        </label>
                        <select
                            v-model="filters.message_id"
                            class="w-full rounded-lg border-slate-200 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                        >
                            <option value="">{{ $t('tracked_links.all_messages') }}</option>
                            <option v-for="msg in messages" :key="msg.id" :value="msg.id">
                                {{ msg.subject }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">
                            {{ $t('settings.logs.from') }}
                        </label>
                        <input
                            v-model="filters.from"
                            type="date"
                            class="w-full rounded-lg border-slate-200 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">
                            {{ $t('settings.logs.to') }}
                        </label>
                        <input
                            v-model="filters.to"
                            type="date"
                            class="w-full rounded-lg border-slate-200 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                        />
                    </div>

                    <div class="flex items-end">
                        <button
                            @click="clearFilters"
                            class="w-full rounded-lg border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700"
                        >
                            {{ $t('settings.logs.clear_filters') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Links Table -->
            <div class="overflow-hidden rounded-xl bg-white shadow-sm dark:bg-slate-800">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 dark:bg-slate-700/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    URL
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    {{ $t('tracked_links.clicks') }}
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    {{ $t('tracked_links.unique') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    {{ $t('tracked_links.first_click') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    {{ $t('tracked_links.last_click') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            <tr v-for="link in links.data" :key="link.url" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                <td class="px-6 py-4">
                                    <a
                                        :href="link.url"
                                        target="_blank"
                                        class="group flex items-center gap-2 text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                                        :title="link.url"
                                    >
                                        <span>{{ truncateUrl(link.url) }}</span>
                                        <svg class="h-3 w-3 opacity-0 transition group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300">
                                        {{ link.clicks }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="text-sm text-slate-600 dark:text-slate-300">
                                        {{ link.unique_clicks }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-sm text-slate-500 dark:text-slate-400">
                                        {{ formatDate(link.first_click) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-sm text-slate-500 dark:text-slate-400">
                                        {{ formatDate(link.last_click) }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="links.data.length === 0">
                                <td colspan="5" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                    <svg class="mx-auto mb-3 h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                    </svg>
                                    {{ $t('tracked_links.no_links') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="links.last_page > 1" class="flex items-center justify-between border-t border-slate-100 px-6 py-4 dark:border-slate-700">
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $t('settings.logs.showing') }} {{ links.from }}-{{ links.to }} {{ $t('settings.logs.of') }} {{ links.total }} {{ $t('settings.logs.results') }}
                    </p>
                    <div class="flex gap-2">
                        <a
                            v-if="links.prev_page_url"
                            :href="links.prev_page_url"
                            class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700"
                        >
                            {{ $t('common.previous') }}
                        </a>
                        <a
                            v-if="links.next_page_url"
                            :href="links.next_page_url"
                            class="rounded-lg border border-slate-200 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700"
                        >
                            {{ $t('common.next') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
