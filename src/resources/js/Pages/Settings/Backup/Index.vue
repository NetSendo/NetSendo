<script setup>
import { ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage();

const props = defineProps({
    backups: Array,
    disk: String,
});

const isCreating = ref(false);
const onlyDb = ref(true);

async function createBackup() {
    isCreating.value = true;
    router.post(route('settings.backup.create'), {
        only_db: onlyDb.value
    }, {
        onFinish: () => isCreating.value = false
    });
}

function downloadBackup(filename) {
    window.location.href = route('settings.backup.download', filename);
}

function deleteBackup(filename) {
    if (confirm(t('backup.confirm_delete', 'Czy na pewno chcesz usunąć ten backup?'))) {
        router.delete(route('settings.backup.destroy', filename));
    }
}

function formatDate(date) {
    return new Date(date).toLocaleString('pl-PL');
}
</script>

<template>
    <Head :title="t('backup.title')" />
    
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">
                    💾 {{ t('backup.title') }}
                </h2>
                <button 
                    @click="createBackup"
                    :disabled="isCreating"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-500 transition-colors flex items-center gap-2 disabled:opacity-50"
                >
                    <svg v-if="isCreating" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ isCreating ? t('backup.creating') : t('backup.create') }}
                </button>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Info Box -->
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                    <div class="flex items-start gap-3">
                        <svg class="h-5 w-5 text-blue-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-blue-800 dark:text-blue-200">
                                {{ t('backup.info_title') }}
                            </p>
                            <p class="text-sm text-blue-600 dark:text-blue-300 mt-1">
                                {{ t('backup.info_text') }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Create Options -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                        {{ t('backup.options') }}
                    </h3>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input 
                            type="checkbox" 
                            v-model="onlyDb"
                            class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500"
                        />
                        <span class="text-gray-700 dark:text-gray-300">
                            {{ t('backup.only_db') }}
                        </span>
                    </label>
                </div>

                <!-- Backup List -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                            {{ t('backup.list_title') }}
                        </h3>
                    </div>
                    
                    <div v-if="backups.length === 0" class="px-6 py-12 text-center">
                        <svg class="w-12 h-12 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                        </svg>
                        <p class="mt-4 text-gray-500 dark:text-gray-400">
                            {{ t('backup.no_backups') }}
                        </p>
                        <button 
                            @click="createBackup"
                            :disabled="isCreating"
                            class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-500 transition-colors disabled:opacity-50"
                        >
                            {{ t('backup.create_first') }}
                        </button>
                    </div>

                    <table v-else class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ t('backup.filename') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ t('backup.size') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ t('backup.date') }}
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ t('backup.actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="backup in backups" :key="backup.name" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-2">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                                        </svg>
                                        <span class="font-medium text-gray-900 dark:text-white">{{ backup.name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ backup.size_human }}
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ formatDate(backup.date) }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button 
                                            @click="downloadBackup(backup.name)"
                                            class="px-3 py-1 bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 rounded-lg hover:bg-green-200 dark:hover:bg-green-900/50 transition-colors"
                                        >
                                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                            {{ t('backup.download') }}
                                        </button>
                                        <button 
                                            @click="deleteBackup(backup.name)"
                                            class="px-3 py-1 bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 rounded-lg hover:bg-red-200 dark:hover:bg-red-900/50 transition-colors"
                                        >
                                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                            {{ t('backup.delete') }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
