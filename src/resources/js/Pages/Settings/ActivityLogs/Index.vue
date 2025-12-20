<script setup>
import { ref, computed, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

const props = defineProps({
    logs: Object,
    users: Array,
    actionCategories: Object,
    stats: Object,
    filters: Object,
});

// Filter state
const filterUser = ref(props.filters?.user_id || '');
const filterCategory = ref(props.filters?.category || '');
const filterAction = ref(props.filters?.action || '');
const filterFrom = ref(props.filters?.from || '');
const filterTo = ref(props.filters?.to || '');
const searchQuery = ref(props.filters?.search || '');

const showCleanupModal = ref(false);
const cleanupDays = ref(90);
const isProcessing = ref(false);

// Apply filters
const applyFilters = () => {
    router.get(route('settings.activity-logs.index'), {
        user_id: filterUser.value || undefined,
        category: filterCategory.value || undefined,
        action: filterAction.value || undefined,
        from: filterFrom.value || undefined,
        to: filterTo.value || undefined,
        search: searchQuery.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    });
};

// Clear filters
const clearFilters = () => {
    filterUser.value = '';
    filterCategory.value = '';
    filterAction.value = '';
    filterFrom.value = '';
    filterTo.value = '';
    searchQuery.value = '';
    router.get(route('settings.activity-logs.index'));
};

// Export
const exportLogs = () => {
    window.location.href = route('settings.activity-logs.export', {
        user_id: filterUser.value || undefined,
        category: filterCategory.value || undefined,
        from: filterFrom.value || undefined,
        to: filterTo.value || undefined,
    });
};

// Cleanup
const performCleanup = () => {
    if (isProcessing.value) return;
    
    isProcessing.value = true;
    router.delete(route('settings.activity-logs.cleanup'), {
        data: { days: cleanupDays.value },
        onFinish: () => {
            isProcessing.value = false;
            showCleanupModal.value = false;
        },
    });
};

// Debounced search
let searchTimeout = null;
watch(searchQuery, (val) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 500);
});

// Format date
const formatDate = (dateString) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString(locale.value, {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Get action icon
const getActionIcon = (action) => {
    const category = action.split('.')[0];
    const icons = {
        auth: '🔐',
        subscriber: '👤',
        message: '✉️',
        list: '📋',
        template: '📄',
        automation: '⚡',
        funnel: '🔀',
        settings: '⚙️',
        form: '📝',
        backup: '💾',
        mailbox: '📬',
        api_key: '🔑',
    };
    return icons[category] || '📌';
};

// Get action color
const getActionColor = (action) => {
    const event = action.split('.')[1];
    if (event === 'created' || event === 'login') return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400';
    if (event === 'deleted' || event === 'failed' || event === 'logout') return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
    if (event === 'updated') return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400';
    return 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300';
};

// Get description from properties
const getDescription = (log) => {
    const props = log.properties || {};
    return props.description || props.name || props.email || props.subject || '';
};

// Available actions for selected category
const availableActions = computed(() => {
    if (!filterCategory.value || !props.actionCategories) return [];
    return props.actionCategories[filterCategory.value] || [];
});

// Has active filters
const hasActiveFilters = computed(() => {
    return filterUser.value || filterCategory.value || filterAction.value || filterFrom.value || filterTo.value || searchQuery.value;
});
</script>

<template>
    <Head :title="$t('settings.activity_logs')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                        📋 {{ $t('settings.activity_logs') }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ t('settings.logs.subtitle') }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button 
                        @click="exportLogs"
                        class="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        {{ t('settings.logs.export_csv') }}
                    </button>
                    <button 
                        @click="showCleanupModal = true"
                        class="inline-flex items-center gap-2 rounded-lg bg-red-100 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-900/50"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        {{ t('settings.logs.cleanup') }}
                    </button>
                </div>
            </div>
        </template>

        <!-- Stats Cards -->
        <div class="mb-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-slate-800">
                <div class="text-sm text-slate-500 dark:text-slate-400">{{ t('settings.logs.all_entries') }}</div>
                <div class="text-2xl font-bold text-slate-900 dark:text-white">{{ stats?.total?.toLocaleString(locale) || 0 }}</div>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-slate-800">
                <div class="text-sm text-slate-500 dark:text-slate-400">{{ t('settings.logs.today') }}</div>
                <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ stats?.today?.toLocaleString(locale) || 0 }}</div>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-slate-800">
                <div class="text-sm text-slate-500 dark:text-slate-400">{{ t('settings.logs.this_week') }}</div>
                <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ stats?.this_week?.toLocaleString(locale) || 0 }}</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="mb-6 rounded-xl bg-white p-4 shadow-sm dark:bg-slate-800">
            <div class="flex flex-wrap items-end gap-4">
                <!-- Search -->
                <div class="min-w-[200px] flex-1">
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ t('common.search') }}</label>
                    <input 
                        v-model="searchQuery"
                        type="text"
                        :placeholder="t('settings.logs.search')"
                        class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                    />
                </div>

                <!-- User -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ t('settings.logs.user') }}</label>
                    <select 
                        v-model="filterUser"
                        @change="applyFilters"
                        class="rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                    >
                        <option value="">{{ t('settings.logs.all_users') }}</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">
                            {{ user.name }}
                        </option>
                    </select>
                </div>

                <!-- Category -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ t('settings.logs.category') }}</label>
                    <select 
                        v-model="filterCategory"
                        @change="applyFilters"
                        class="rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                    >
                        <option value="">{{ t('settings.logs.all_categories') }}</option>
                        <option v-for="(actions, cat) in actionCategories" :key="cat" :value="cat">
                            {{ $t('settings.logs.categories.' + cat) }}
                        </option>
                    </select>
                </div>

                <!-- Date From -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ t('settings.logs.from') }}</label>
                    <input 
                        v-model="filterFrom"
                        @change="applyFilters"
                        type="date"
                        class="rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                    />
                </div>

                <!-- Date To -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">{{ t('settings.logs.to') }}</label>
                    <input 
                        v-model="filterTo"
                        @change="applyFilters"
                        type="date"
                        class="rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                    />
                </div>

                <!-- Clear Filters -->
                <button 
                    v-if="hasActiveFilters"
                    @click="clearFilters"
                    class="rounded-lg px-3 py-2 text-sm text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700 dark:hover:text-slate-300"
                >
                    {{ t('settings.logs.clear_filters') }}
                </button>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="rounded-xl bg-white shadow-sm dark:bg-slate-800">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-800/50">
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ t('settings.logs.table.date') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ t('settings.logs.table.user') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ t('settings.logs.table.action') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ t('settings.logs.table.details') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ t('settings.logs.table.ip') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        <tr 
                            v-for="log in logs.data" 
                            :key="log.id"
                            class="hover:bg-slate-50 dark:hover:bg-slate-700/50"
                        >
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                {{ formatDate(log.created_at) }}
                            </td>
                            <td class="px-6 py-4">
                                <div v-if="log.user" class="flex items-center gap-2">
                                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-sm font-medium text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400">
                                        {{ log.user.name?.charAt(0)?.toUpperCase() || '?' }}
                                    </div>
                                    <span class="text-sm text-slate-900 dark:text-white">{{ log.user.name }}</span>
                                </div>
                                <span v-else class="text-sm text-slate-400">{{ t('settings.logs.system_user') }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <span 
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="getActionColor(log.action)"
                                >
                                    <span>{{ getActionIcon(log.action) }}</span>
                                    {{ log.action_name }}
                                </span>
                            </td>
                            <td class="max-w-xs truncate px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                                {{ getDescription(log) || '-' }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500 dark:text-slate-500">
                                {{ log.ip_address || '-' }}
                            </td>
                        </tr>
                        <tr v-if="!logs.data?.length">
                            <td colspan="5" class="px-6 py-12 text-center text-slate-500 dark:text-slate-400">
                                <div class="flex flex-col items-center">
                                    <svg class="mb-3 h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <span>{{ t('settings.logs.no_logs') }}</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="logs.last_page > 1" class="flex items-center justify-between border-t border-slate-200 px-6 py-4 dark:border-slate-700">
                <div class="text-sm text-slate-500 dark:text-slate-400">
                    {{ t('settings.logs.showing') }} {{ logs.from }} - {{ logs.to }} {{ t('settings.logs.of') }} {{ logs.total }} {{ t('settings.logs.results') }}
                </div>
                <div class="flex gap-1">
                    <Link 
                        v-for="link in logs.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        class="rounded-lg px-3 py-1 text-sm transition-colors"
                        :class="{
                            'bg-indigo-600 text-white': link.active,
                            'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700': !link.active && link.url,
                            'text-slate-400 cursor-not-allowed': !link.url,
                        }"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>

        <!-- Cleanup Modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showCleanupModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800">
                        <div class="mb-4 flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                                <svg class="h-5 w-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ t('settings.logs.cleanup_title') }}</h3>
                                <p class="text-sm text-slate-500 dark:text-slate-400">{{ t('settings.logs.cleanup_warning') }}</p>
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="mb-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                {{ t('settings.logs.cleanup_days') }}
                            </label>
                            <div class="flex items-center gap-2">
                                <input 
                                    v-model.number="cleanupDays"
                                    type="number"
                                    min="1"
                                    max="365"
                                    class="w-24 rounded-lg border-slate-300 text-center focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                />
                                <span class="text-slate-600 dark:text-slate-400">{{ t('settings.logs.cleanup_days_unit') }}</span>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3">
                            <button 
                                @click="showCleanupModal = false"
                                class="rounded-lg px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700"
                            >
                                {{ t('common.cancel') }}
                            </button>
                            <button 
                                @click="performCleanup"
                                :disabled="isProcessing"
                                class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                            >
                                <svg v-if="isProcessing" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ t('settings.logs.cleanup_button') }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>
