<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    automation: Object,
    logs: Object,
    stats: Object,
});

const statusColors = {
    success: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    partial: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    skipped: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
};
</script>

<template>
    <Head :title="$t('automations.logs_page.title', { name: automation.name })" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('automations.index')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    📋 {{ $t('automations.logs_page.title', { name: automation.name }) }}
                </h2>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Stats -->
                <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
                    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('automations.logs_page.stats.total') }}</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ stats.total }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                        <p class="text-sm text-green-600">{{ $t('automations.logs_page.stats.success') }}</p>
                        <p class="text-2xl font-bold text-green-600">{{ stats.success }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                        <p class="text-sm text-red-600">{{ $t('automations.logs_page.stats.failed') }}</p>
                        <p class="text-2xl font-bold text-red-600">{{ stats.failed }}</p>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t('automations.logs_page.stats.skipped') }}</p>
                        <p class="text-2xl font-bold text-gray-600 dark:text-gray-400">{{ stats.skipped }}</p>
                    </div>
                </div>

                <!-- Logs Table -->
                <div class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ $t('automations.logs_page.table.date') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ $t('automations.logs_page.table.subscriber') }}</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ $t('automations.logs_page.table.trigger') }}</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ $t('automations.logs_page.table.actions') }}</th>
                                <th class="px-6 py-3 text-center text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ $t('automations.logs_page.table.status') }}</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ $t('automations.logs_page.table.time') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="log in logs.data" :key="log.id" class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                    {{ log.executed_at }}
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ log.subscriber_email }}</div>
                                    <div v-if="log.subscriber_name !== '-'" class="text-gray-500 dark:text-gray-400">{{ log.subscriber_name }}</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                    {{ log.trigger_event }}
                                </td>
                                <td class="px-6 py-4 text-center text-sm text-gray-600 dark:text-gray-300">
                                    {{ log.actions_summary }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span :class="['inline-flex rounded-full px-2 py-1 text-xs font-semibold', statusColors[log.status]]">
                                        {{ log.status_label }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-500 dark:text-gray-400">
                                    {{ log.execution_time_ms ? `${log.execution_time_ms}ms` : '-' }}
                                </td>
                            </tr>
                            <tr v-if="logs.data.length === 0">
                                <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                    <svg class="mx-auto mb-4 h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                    </svg>
                                    <p class="text-lg font-medium">{{ $t('automations.logs_page.empty') }}</p>
                                    <p class="mt-1 text-sm">{{ $t('automations.logs_page.empty_hint') }}</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div v-if="logs.links && logs.links.length > 3" class="border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex gap-2">
                            <Link
                                v-for="link in logs.links"
                                :key="link.label"
                                :href="link.url"
                                v-html="link.label"
                                :class="[
                                    'rounded px-3 py-1 text-sm',
                                    link.active
                                        ? 'bg-indigo-600 text-white'
                                        : link.url
                                            ? 'bg-white text-gray-700 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300'
                                            : 'cursor-not-allowed bg-gray-100 text-gray-400 dark:bg-gray-700'
                                ]"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
