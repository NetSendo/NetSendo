<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    funnels: Object,
    filters: Object,
    statuses: Object,
    triggerTypes: Object,
});

// Filters
const selectedStatus = ref(props.filters?.status || '');
const selectedTriggerType = ref(props.filters?.trigger_type || '');
const searchQuery = ref(props.filters?.search || '');

const applyFilters = () => {
    router.get(route('funnels.index'), {
        status: selectedStatus.value || undefined,
        trigger_type: selectedTriggerType.value || undefined,
        search: searchQuery.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

// Debounced search
let searchTimeout;
watch(searchQuery, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 300);
});

// Actions
const duplicateFunnel = (funnel) => {
    if (confirm(t('funnels.confirm_duplicate'))) {
        router.post(route('funnels.duplicate', funnel.id));
    }
};

const deleteFunnel = (funnel) => {
    if (confirm(t('funnels.confirm_delete'))) {
        router.delete(route('funnels.destroy', funnel.id));
    }
};

const toggleStatus = (funnel) => {
    router.post(route('funnels.toggle-status', funnel.id));
};

const getStatusClass = (status) => {
    return {
        'active': 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        'paused': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        'draft': 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    }[status] || 'bg-gray-100 text-gray-800';
};

const getTriggerLabel = (type) => {
    return props.triggerTypes[type] || type;
};
</script>

<template>
    <Head :title="t('funnels.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                    {{ t('funnels.title') }}
                </h1>
                <Link
                    :href="route('funnels.create')"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ t('funnels.create') }}
                </Link>
            </div>
        </template>

        <div class="py-6">
            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
                <div class="p-4 flex flex-wrap gap-4 items-center">
                    <!-- Search -->
                    <div class="flex-1 min-w-[200px]">
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input
                                v-model="searchQuery"
                                type="text"
                                :placeholder="t('common.search')"
                                class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            />
                        </div>
                    </div>

                    <!-- Status filter -->
                    <select
                        v-model="selectedStatus"
                        @change="applyFilters"
                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                    >
                        <option value="">{{ t('funnels.all_statuses') }}</option>
                        <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
                    </select>

                    <!-- Trigger type filter -->
                    <select
                        v-model="selectedTriggerType"
                        @change="applyFilters"
                        class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                    >
                        <option value="">{{ t('funnels.all_triggers') }}</option>
                        <option v-for="(label, key) in triggerTypes" :key="key" :value="key">{{ label }}</option>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                {{ t('funnels.table.name') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                {{ t('funnels.table.trigger') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                {{ t('funnels.table.status') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                {{ t('funnels.table.subscribers') }}
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                {{ t('funnels.table.completed') }}
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                {{ t('common.actions') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="funnel in funnels.data" :key="funnel.id" class="hover:bg-gray-50 dark:hover:bg-gray-750">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <Link :href="route('funnels.edit', funnel.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium">
                                    {{ funnel.name }}
                                </Link>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ funnel.trigger_list?.name || funnel.trigger_form?.name || funnel.trigger_tag || '-' }}
                                </p>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                {{ getTriggerLabel(funnel.trigger_type) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span :class="['px-2 py-1 text-xs font-medium rounded-full', getStatusClass(funnel.status)]">
                                    {{ statuses[funnel.status] }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                {{ funnel.subscribers_count }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                {{ funnel.completed_count }}
                                <span v-if="funnel.subscribers_count > 0" class="text-gray-400">
                                    ({{ Math.round((funnel.completed_count / funnel.subscribers_count) * 100) }}%)
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                <div class="flex items-center justify-end gap-2">
                                    <!-- Toggle Status -->
                                    <button
                                        @click="toggleStatus(funnel)"
                                        :title="funnel.status === 'active' ? t('funnels.pause') : t('funnels.activate')"
                                        class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                    >
                                        <svg v-if="funnel.status === 'active'" class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <svg v-else class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </button>

                                    <!-- Stats -->
                                    <Link
                                        :href="route('funnels.stats', funnel.id)"
                                        :title="t('funnels.stats_btn')"
                                        class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                    >
                                        <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                        </svg>
                                    </Link>

                                    <!-- Edit -->
                                    <Link
                                        :href="route('funnels.edit', funnel.id)"
                                        :title="t('common.edit')"
                                        class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                    >
                                        <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </Link>

                                    <!-- Duplicate -->
                                    <button
                                        @click="duplicateFunnel(funnel)"
                                        :title="t('common.duplicate')"
                                        class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                    >
                                        <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </button>

                                    <!-- Delete -->
                                    <button
                                        @click="deleteFunnel(funnel)"
                                        :title="t('common.delete')"
                                        class="p-2 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                                    >
                                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Empty State -->
                <div v-if="funnels.data.length === 0" class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">{{ t('funnels.no_funnels') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ t('funnels.no_funnels_desc') }}</p>
                    <div class="mt-6">
                        <Link
                            :href="route('funnels.create')"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            {{ t('funnels.create') }}
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="funnels.data.length > 0 && funnels.last_page > 1" class="mt-6 bg-white dark:bg-gray-800 rounded-lg shadow px-6 py-4">
                <div class="flex justify-between items-center">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ t('common.showing') }} {{ funnels.from }}-{{ funnels.to }} {{ t('common.of') }} {{ funnels.total }}
                    </p>
                    <div class="flex gap-2">
                        <Link 
                            v-if="funnels.prev_page_url"
                            :href="funnels.prev_page_url"
                            class="px-4 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition-colors"
                        >
                            {{ t('common.previous') }}
                        </Link>
                        <Link 
                            v-if="funnels.next_page_url"
                            :href="funnels.next_page_url"
                            class="px-4 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg transition-colors"
                        >
                            {{ t('common.next') }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
