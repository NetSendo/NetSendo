<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from 'vue-i18n';
import { useDateTime } from '@/Composables/useDateTime';

const { t } = useI18n();
const { formatDate: formatDateBase } = useDateTime();

const props = defineProps({
    form: Object,
    stats: Object,
    recentSubmissions: Array,
    dateRange: Object,
});

const from = ref(props.dateRange.from);
const to = ref(props.dateRange.to);

// Stats cards
const statsCards = computed(() => [
    { label: t('forms.stats.total'), value: props.stats.total, color: 'indigo', icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
    { label: t('forms.stats.confirmed'), value: props.stats.confirmed, color: 'green', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
    { label: t('forms.stats.pending'), value: props.stats.pending, color: 'yellow', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
    { label: t('forms.stats.rejected'), value: props.stats.rejected, color: 'red', icon: 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z' },
]);

// Format date
function formatDate(date) {
    if (!date) return '-';
    return formatDateBase(date, null, {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Status badge
function getStatusClass(status) {
    switch (status) {
        case 'confirmed':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
        case 'pending':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400';
        case 'rejected':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
        case 'error':
            return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-400';
        default:
            return 'bg-gray-100 text-gray-800';
    }
}
</script>

<template>
    <Head :title="t('forms.stats.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('forms.edit', form.id)"
                    class="btn-icon"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </Link>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {{ t('forms.stats.title') }}
                    </h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ form.name }}</p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Stats cards -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                    <div
                        v-for="stat in statsCards"
                        :key="stat.label"
                        class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                :class="[
                                    'w-12 h-12 rounded-xl flex items-center justify-center',
                                    {
                                        'bg-indigo-100 dark:bg-indigo-900/40': stat.color === 'indigo',
                                        'bg-green-100 dark:bg-green-900/40': stat.color === 'green',
                                        'bg-yellow-100 dark:bg-yellow-900/40': stat.color === 'yellow',
                                        'bg-red-100 dark:bg-red-900/40': stat.color === 'red',
                                    }
                                ]"
                            >
                                <svg
                                    :class="[
                                        'w-6 h-6',
                                        {
                                            'text-indigo-600 dark:text-indigo-400': stat.color === 'indigo',
                                            'text-green-600 dark:text-green-400': stat.color === 'green',
                                            'text-yellow-600 dark:text-yellow-400': stat.color === 'yellow',
                                            'text-red-600 dark:text-red-400': stat.color === 'red',
                                        }
                                    ]"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="stat.icon"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ stat.value }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ stat.label }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chart placeholder -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm p-6 mb-8">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">
                        {{ t('forms.stats.submissions_over_time') }}
                    </h3>
                    <div class="h-64 flex items-center justify-center text-gray-400 dark:text-gray-500">
                        <div class="text-center">
                            <svg class="w-12 h-12 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            <p>{{ t('forms.stats.chart_placeholder') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Recent submissions -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            {{ t('forms.stats.recent_submissions') }}
                        </h3>
                    </div>

                    <div v-if="recentSubmissions.length === 0" class="p-12 text-center text-gray-500 dark:text-gray-400">
                        {{ t('forms.stats.no_submissions') }}
                    </div>

                    <table v-else class="w-full">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                    {{ t('forms.stats.email') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                    {{ t('forms.stats.name') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                    {{ t('forms.stats.status') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                    {{ t('forms.stats.source') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                    {{ t('forms.stats.date') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr
                                v-for="submission in recentSubmissions"
                                :key="submission.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                            >
                                <td class="px-6 py-4 text-gray-900 dark:text-gray-100">
                                    {{ submission.subscriber?.email || submission.submission_data?.email || '-' }}
                                </td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-400">
                                    {{ [submission.subscriber?.first_name, submission.subscriber?.last_name].filter(Boolean).join(' ') || '-' }}
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium"
                                        :class="getStatusClass(submission.status)"
                                    >
                                        {{ submission.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-400 text-sm">
                                    {{ submission.source }}
                                </td>
                                <td class="px-6 py-4 text-gray-600 dark:text-gray-400 text-sm">
                                    {{ formatDate(submission.created_at) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.btn-icon {
    @apply p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors;
}
</style>
