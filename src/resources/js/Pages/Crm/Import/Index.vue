<script setup>
import { ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, router } from "@inertiajs/vue3";

const props = defineProps({});

const file = ref(null);
const previewData = ref(null);
const isUploading = ref(false);
const isImporting = ref(false);
const columnMapping = ref({});
const duplicateAction = ref("skip");
const defaultStatus = ref("lead");

// Handle file selection
const onFileSelect = async (event) => {
    file.value = event.target.files[0];
    if (!file.value) return;

    isUploading.value = true;
    const formData = new FormData();
    formData.append("file", file.value);

    try {
        const response = await fetch("/crm/import/preview", {
            method: "POST",
            headers: { "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content },
            body: formData,
        });
        previewData.value = await response.json();
        columnMapping.value = previewData.value.suggestedMapping || {};
    } catch (error) {
        console.error("Failed to preview file:", error);
    } finally {
        isUploading.value = false;
    }
};

// Execute import
const executeImport = async () => {
    if (!previewData.value?.tempPath) return;

    isImporting.value = true;
    router.post("/crm/import", {
        temp_path: previewData.value.tempPath,
        mapping: columnMapping.value,
        duplicate_action: duplicateAction.value,
        default_status: defaultStatus.value,
    }, {
        onFinish: () => { isImporting.value = false; },
    });
};

// Reset
const resetImport = () => {
    file.value = null;
    previewData.value = null;
    columnMapping.value = {};
};
</script>

<template>
    <Head title="Import CRM" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Import kontaktów</h1>
        </template>

        <!-- Upload Step -->
        <div v-if="!previewData" class="rounded-2xl bg-white p-8 shadow-sm dark:bg-slate-800">
            <div class="mx-auto max-w-md text-center">
                <svg class="mx-auto h-16 w-16 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
                <h2 class="mt-4 text-lg font-semibold text-slate-900 dark:text-white">Prześlij plik CSV</h2>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                    Załaduj plik CSV z kontaktami. Wymagane pole: email.
                </p>
                <label class="mt-6 inline-flex cursor-pointer items-center gap-2 rounded-xl bg-indigo-600 px-6 py-3 text-sm font-medium text-white transition hover:bg-indigo-700">
                    <svg v-if="isUploading" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span>{{ isUploading ? 'Wczytywanie...' : 'Wybierz plik' }}</span>
                    <input type="file" accept=".csv,.txt" class="hidden" @change="onFileSelect" :disabled="isUploading" />
                </label>
            </div>
        </div>

        <!-- Mapping Step -->
        <div v-else class="space-y-6">
            <!-- File Info -->
            <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="font-semibold text-slate-900 dark:text-white">{{ file?.name }}</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ previewData.totalRows }} rekordów</p>
                    </div>
                    <button @click="resetImport" class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400">
                        Wybierz inny plik
                    </button>
                </div>
            </div>

            <!-- Column Mapping -->
            <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                <h2 class="mb-4 font-semibold text-slate-900 dark:text-white">Mapowanie kolumn</h2>
                <div class="space-y-3">
                    <div v-for="header in previewData.headers" :key="header" class="flex items-center gap-4">
                        <span class="w-48 truncate font-mono text-sm text-slate-600 dark:text-slate-400">{{ header }}</span>
                        <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                        </svg>
                        <select v-model="columnMapping[header]"
                            class="flex-1 rounded-lg border-slate-200 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                            <option v-for="field in previewData.availableFields" :key="field.value" :value="field.value">
                                {{ field.label }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Options -->
            <div class="grid gap-6 md:grid-cols-2">
                <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                    <h3 class="mb-3 font-semibold text-slate-900 dark:text-white">Duplikaty (email)</h3>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2">
                            <input type="radio" v-model="duplicateAction" value="skip" class="text-indigo-600 focus:ring-indigo-500" />
                            <span class="text-sm text-slate-700 dark:text-slate-300">Pomiń istniejące</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" v-model="duplicateAction" value="update" class="text-indigo-600 focus:ring-indigo-500" />
                            <span class="text-sm text-slate-700 dark:text-slate-300">Aktualizuj istniejące</span>
                        </label>
                    </div>
                </div>
                <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                    <h3 class="mb-3 font-semibold text-slate-900 dark:text-white">Domyślny status</h3>
                    <select v-model="defaultStatus"
                        class="w-full rounded-lg border-slate-200 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                        <option value="lead">Lead</option>
                        <option value="prospect">Prospect</option>
                        <option value="client">Klient</option>
                        <option value="dormant">Uśpiony</option>
                    </select>
                </div>
            </div>

            <!-- Import Button -->
            <div class="flex justify-end">
                <button @click="executeImport" :disabled="isImporting"
                    class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-6 py-3 font-medium text-white transition hover:bg-indigo-700 disabled:opacity-50">
                    <svg v-if="isImporting" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    {{ isImporting ? 'Importowanie...' : 'Rozpocznij import' }}
                </button>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
