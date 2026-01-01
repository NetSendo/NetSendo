<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    webinars: Object,
    filters: Object,
    types: Object,
    statuses: Object,
});

const search = ref(props.filters.search || '');
const selectedType = ref(props.filters.type || '');
const selectedStatus = ref(props.filters.status || '');

const applyFilters = () => {
    router.get(route('webinars.index'), {
        search: search.value,
        type: selectedType.value,
        status: selectedStatus.value,
    }, { preserveState: true, preserveScroll: true });
};

const getStatusColor = (status) => {
    const colors = {
        draft: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        scheduled: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        live: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300 animate-pulse',
        ended: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
        published: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    };
    return colors[status] || colors.draft;
};

const getTypeLabel = (type) => {
    return props.types[type] || type;
};

const getStatusLabel = (status) => {
    return props.statuses[status] || status;
};

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('pl-PL', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const deleteWebinar = (webinar) => {
    if (confirm(props.t ? props.t('webinars.index.delete_confirm', { name: webinar.name }) : `Are you sure you want to delete webinar "${webinar.name}"?`)) {
        router.delete(route('webinars.destroy', webinar.id));
    }
};

const duplicateWebinar = (webinar) => {
    router.post(route('webinars.duplicate', webinar.id));
};
</script>

<template>
    <Head title="Webinary" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $t('webinars.index.title') }}
                </h2>
                <Link
                    :href="route('webinars.create')"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    {{ $t('webinars.index.new_webinar') }}
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-4 flex flex-wrap gap-4">
                        <input
                            v-model="search"
                            @input="applyFilters"
                            type="text"
                            :placeholder="$t('webinars.index.search_placeholder')"
                            class="flex-1 min-w-[200px] rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <select
                            v-model="selectedType"
                            @change="applyFilters"
                            class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">{{ $t('webinars.index.all_types') }}</option>
                            <option v-for="(label, value) in types" :key="value" :value="value">
                                {{ label }}
                            </option>
                        </select>
                        <select
                            v-model="selectedStatus"
                            @change="applyFilters"
                            class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">{{ $t('webinars.index.all_statuses') }}</option>
                            <option v-for="(label, value) in statuses" :key="value" :value="value">
                                {{ label }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Webinars List -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div v-if="webinars.data.length === 0" class="p-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">{{ $t('webinars.index.empty_title') }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $t('webinars.index.empty_desc') }}
                        </p>
                        <div class="mt-6">
                            <Link
                                :href="route('webinars.create')"
                                class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500"
                            >
                                <svg class="-ml-0.5 mr-1.5 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                {{ $t('webinars.index.new_webinar') }}
                            </Link>
                        </div>
                    </div>

                    <ul v-else role="list" class="divide-y divide-gray-200 dark:divide-gray-700">
                        <li v-for="webinar in webinars.data" :key="webinar.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <div class="px-4 py-4 sm:px-6">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center min-w-0 gap-x-4">
                                        <div class="h-12 w-12 flex-none rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
                                            <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                            </svg>
                                        </div>
                                        <div class="min-w-0 flex-auto">
                                            <Link :href="route('webinars.show', webinar.id)" class="text-sm font-semibold leading-6 text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400">
                                                {{ webinar.name }}
                                            </Link>
                                            <div class="mt-1 flex items-center gap-x-2 text-xs leading-5 text-gray-500 dark:text-gray-400">
                                                <span :class="['inline-flex items-center rounded-full px-2 py-1 text-xs font-medium', getStatusColor(webinar.status)]">
                                                    {{ getStatusLabel(webinar.status) }}
                                                </span>
                                                <span class="text-gray-300 dark:text-gray-600">•</span>
                                                <span>{{ getTypeLabel(webinar.type) }}</span>
                                                <span v-if="webinar.scheduled_at" class="text-gray-300 dark:text-gray-600">•</span>
                                                <span v-if="webinar.scheduled_at" class="flex items-center">
                                                    <svg class="mr-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                    </svg>
                                                    {{ formatDate(webinar.scheduled_at) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-x-4">
                                        <div class="hidden sm:flex sm:flex-col sm:items-end text-sm text-gray-500 dark:text-gray-400">
                                            <span>{{ webinar.registrations_count || 0 }} {{ $t('webinars.index.registrations') }}</span>
                                            <span v-if="webinar.peak_viewers">{{ $t('webinars.index.peak_viewers', { count: webinar.peak_viewers }) }}</span>
                                        </div>
                                        <div class="flex items-center gap-x-2">
                                            <Link
                                                v-if="webinar.status === 'live'"
                                                :href="route('webinars.studio', webinar.id)"
                                                class="rounded-md bg-red-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-red-500 flex items-center gap-1"
                                            >
                                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M8 5v14l11-7z"/>
                                                </svg>
                                                {{ $t('webinars.index.studio') }}
                                            </Link>
                                            <Link
                                                v-else-if="webinar.status === 'scheduled'"
                                                :href="route('webinars.studio', webinar.id)"
                                                class="rounded-md bg-indigo-600 px-2.5 py-1.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 flex items-center gap-1"
                                            >
                                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M8 5v14l11-7z"/>
                                                </svg>
                                                {{ $t('webinars.index.start') }}
                                            </Link>
                                            <Link
                                                :href="route('webinars.analytics', webinar.id)"
                                                class="rounded-md bg-gray-100 dark:bg-gray-700 px-2.5 py-1.5 text-sm font-semibold text-gray-700 dark:text-gray-200 shadow-sm hover:bg-gray-200 dark:hover:bg-gray-600"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                                </svg>
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>

                    <!-- Pagination -->
                    <div v-if="webinars.links && webinars.links.length > 3" class="border-t border-gray-200 dark:border-gray-700 px-4 py-3">
                        <nav class="flex items-center justify-between">
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">
                                        Pokazano
                                        <span class="font-medium">{{ webinars.from }}</span>
                                        do
                                        <span class="font-medium">{{ webinars.to }}</span>
                                        z
                                        <span class="font-medium">{{ webinars.total }}</span>
                                        wyników
                                    </p>
                                </div>
                            </div>
                        </nav>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
