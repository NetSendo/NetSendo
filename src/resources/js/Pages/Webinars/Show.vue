<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    webinar: Object,
    stats: Object,
});

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('pl-PL', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
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
</script>

<template>
    <Head :title="webinar.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('webinars.index')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                    </Link>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        {{ webinar.name }}
                    </h2>
                    <span :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', getStatusColor(webinar.status)]">
                        {{ webinar.status }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        v-if="webinar.status === 'live' || webinar.status === 'scheduled'"
                        :href="route('webinars.studio', webinar.id)"
                        class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700"
                    >
                        <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                        {{ $t('webinars.show.studio') }}
                    </Link>
                    <Link
                        :href="route('webinars.edit', webinar.id)"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
                    >
                        {{ $t('webinars.show.edit') }}
                    </Link>
                    <Link
                        :href="route('webinars.analytics', webinar.id)"
                        class="inline-flex items-center px-4 py-2 bg-gray-100 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-200 uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-gray-600"
                    >
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        {{ $t('webinars.show.analytics') }}
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Quick Stats -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('webinars.show.registrations') }}</div>
                        <div class="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">{{ stats?.registrations || 0 }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('webinars.show.attended') }}</div>
                        <div class="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">{{ stats?.attended || 0 }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('webinars.show.peak_viewers') }}</div>
                        <div class="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">{{ stats?.peak_viewers || 0 }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('webinars.show.sessions') }}</div>
                        <div class="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">{{ webinar.sessions?.length || 0 }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Webinar Details -->
                    <div class="lg:col-span-2 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ $t('webinars.show.details') }}</h3>

                        <dl class="space-y-4">
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('webinars.show.type') }}</dt>
                                <dd class="text-sm text-gray-900 dark:text-white capitalize">{{ webinar.type }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('webinars.show.scheduled_at') }}</dt>
                                <dd class="text-sm text-gray-900 dark:text-white">{{ formatDate(webinar.scheduled_at) }}</dd>
                            </div>
                            <div v-if="webinar.description">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">{{ $t('webinars.show.description') }}</dt>
                                <dd class="text-sm text-gray-900 dark:text-white">{{ webinar.description }}</dd>
                            </div>
                            <div v-if="webinar.target_list">
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $t('webinars.show.target_list') }}</dt>
                                <dd class="text-sm text-gray-900 dark:text-white">{{ webinar.target_list.name }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Products -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ $t('webinars.show.products') }}</h3>

                        <div v-if="webinar.products && webinar.products.length > 0" class="space-y-3">
                            <div v-for="product in webinar.products" :key="product.id" class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ product.name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ product.price }} zł</p>
                                </div>
                                <span v-if="product.is_pinned" class="text-xs bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300 px-2 py-0.5 rounded">
                                    {{ $t('webinars.show.pinned') }}
                                </span>
                            </div>
                        </div>
                        <div v-else class="text-center text-gray-500 dark:text-gray-400 py-4">
                            {{ $t('webinars.show.no_products') }}
                        </div>
                    </div>
                </div>

                <!-- Sessions -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">{{ $t('webinars.show.sessions_title') }}</h3>

                    <div v-if="webinar.sessions && webinar.sessions.length > 0">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $t('webinars.show.session_date') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $t('webinars.show.session_status') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $t('webinars.show.session_viewers') }}</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $t('webinars.show.session_duration') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <tr v-for="session in webinar.sessions" :key="session.id">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ formatDate(session.started_at || session.scheduled_at) }}</td>
                                    <td class="px-4 py-3">
                                        <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', getStatusColor(session.status)]">
                                            {{ session.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ session.peak_viewers || 0 }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ session.duration || '-' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="text-center text-gray-500 dark:text-gray-400 py-8">
                        {{ $t('webinars.show.no_sessions') }}
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
