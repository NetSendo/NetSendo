<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    inserts: {
        type: Array,
        default: () => [],
    },
    signatures: {
        type: Array,
        default: () => [],
    },
    systemVariables: {
        type: Array,
        default: () => [],
    },
    customFields: {
        type: Array,
        default: () => [],
    },
});

// Modal state
const showModal = ref(false);
const editingItem = ref(null);
const showDeleteConfirm = ref(null);
const copiedCode = ref(null);

// Form
const form = useForm({
    name: '',
    description: '',
    type: 'insert',
    content: '',
    content_plain: '',
});

// Open modal for creating
const openCreateModal = (type = 'insert') => {
    editingItem.value = null;
    form.reset();
    form.type = type;
    showModal.value = true;
};

// Open modal for editing
const openEditModal = (item) => {
    editingItem.value = item;
    form.name = item.name;
    form.description = item.description || '';
    form.type = item.type;
    form.content = item.content || '';
    form.content_plain = item.content_plain || '';
    showModal.value = true;
};

// Close modal
const closeModal = () => {
    showModal.value = false;
    editingItem.value = null;
    form.reset();
};

// Submit form
const submitForm = () => {
    if (editingItem.value) {
        form.put(route('inserts.update', editingItem.value.id), {
            onSuccess: closeModal,
        });
    } else {
        form.post(route('inserts.store'), {
            onSuccess: closeModal,
        });
    }
};

// Delete insert
const confirmDelete = (id) => {
    router.delete(route('inserts.destroy', id));
    showDeleteConfirm.value = null;
};

// Copy code to clipboard
const copyToClipboard = async (code) => {
    await navigator.clipboard.writeText(code);
    copiedCode.value = code;
    setTimeout(() => {
        copiedCode.value = null;
    }, 2000);
};

// Category icons
const categoryIcons = {
    subscriber: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />`,
    links: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />`,
    dates: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />`,
    system: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />`,
    special: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />`,
};

// Collapsed categories
const collapsedCategories = ref({});
const toggleCategory = (category) => {
    collapsedCategories.value[category] = !collapsedCategories.value[category];
};
</script>

<template>
    <Head :title="$t('inserts.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                        {{ $t('inserts.title') }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $t('inserts.subtitle') }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <button
                        @click="openCreateModal('signature')"
                        class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-white px-4 py-2.5 text-sm font-semibold text-emerald-700 transition-all hover:bg-emerald-50 dark:border-emerald-800 dark:bg-slate-900 dark:text-emerald-400 dark:hover:bg-emerald-900/20"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                        {{ $t('inserts.new_signature') }}
                    </button>
                    <button
                        @click="openCreateModal('insert')"
                        class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-500/30 transition-all hover:from-indigo-500 hover:to-purple-500 hover:shadow-indigo-500/40"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ $t('inserts.new_insert') }}
                    </button>
                </div>
            </div>
        </template>

        <div class="grid gap-8 lg:grid-cols-2">
            <!-- System Variables -->
            <div>
                <div class="mb-4 flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-blue-500 to-cyan-500">
                        <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ $t('inserts.system_variables') }}</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $t('inserts.system_variables_desc') }}</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div
                        v-for="category in systemVariables"
                        :key="category.category"
                        class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200/50 dark:bg-slate-900 dark:ring-slate-700/50"
                    >
                        <!-- Category Header -->
                        <button
                            @click="toggleCategory(category.category)"
                            class="flex w-full items-center justify-between px-4 py-3 text-left transition-colors hover:bg-slate-50 dark:hover:bg-slate-800"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100 dark:bg-slate-800">
                                    <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" v-html="categoryIcons[category.category] || categoryIcons.system"></svg>
                                </div>
                                <span class="font-medium text-slate-900 dark:text-white">{{ category.label }}</span>
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-400">
                                    {{ category.variables.length }}
                                </span>
                            </div>
                            <svg
                                class="h-5 w-5 text-slate-400 transition-transform"
                                :class="{ 'rotate-180': !collapsedCategories[category.category] }"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <!-- Variables List -->
                        <div v-show="!collapsedCategories[category.category]" class="border-t border-slate-100 dark:border-slate-800">
                            <div
                                v-for="variable in category.variables"
                                :key="variable.code"
                                class="flex items-center justify-between border-b border-slate-50 px-4 py-2.5 last:border-b-0 dark:border-slate-800/50"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <code class="rounded bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                                            {{ variable.code }}
                                        </code>
                                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ variable.label }}</span>
                                    </div>
                                    <p v-if="variable.description" class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                        {{ variable.description }}
                                    </p>
                                </div>
                                <button
                                    @click="copyToClipboard(variable.code)"
                                    class="ml-2 flex-shrink-0 rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300"
                                    :title="$t('common.copy')"
                                >
                                    <svg v-if="copiedCode !== variable.code" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    <svg v-else class="h-4 w-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Custom Fields -->
                    <div v-if="customFields.length > 0" class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200/50 dark:bg-slate-900 dark:ring-slate-700/50">
                        <button
                            @click="toggleCategory('custom')"
                            class="flex w-full items-center justify-between px-4 py-3 text-left transition-colors hover:bg-slate-50 dark:hover:bg-slate-800"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/30">
                                    <svg class="h-4 w-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                                    </svg>
                                </div>
                                <span class="font-medium text-slate-900 dark:text-white">{{ $t('inserts.custom_fields') }}</span>
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                    {{ customFields.length }}
                                </span>
                            </div>
                            <svg
                                class="h-5 w-5 text-slate-400 transition-transform"
                                :class="{ 'rotate-180': !collapsedCategories.custom }"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div v-show="!collapsedCategories.custom" class="border-t border-slate-100 dark:border-slate-800">
                            <div
                                v-for="field in customFields"
                                :key="field.id"
                                class="flex items-center justify-between border-b border-slate-50 px-4 py-2.5 last:border-b-0 dark:border-slate-800/50"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <code class="rounded bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                            [[{{ field.name }}]]
                                        </code>
                                        <span class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ field.label }}</span>
                                    </div>
                                </div>
                                <button
                                    @click="copyToClipboard('[[' + field.name + ']]')"
                                    class="ml-2 flex-shrink-0 rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300"
                                    :title="$t('common.copy')"
                                >
                                    <svg v-if="copiedCode !== '[[' + field.name + ']]'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    <svg v-else class="h-4 w-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Inserts & Signatures -->
            <div class="space-y-8">
                <!-- Inserts -->
                <div>
                    <div class="mb-4 flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-500">
                            <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ $t('inserts.your_inserts') }}</h2>
                            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $t('inserts.your_inserts_desc') }}</p>
                        </div>
                    </div>

                    <div v-if="inserts.length === 0" class="rounded-xl border-2 border-dashed border-slate-200 bg-white py-8 text-center dark:border-slate-700 dark:bg-slate-900">
                        <svg class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ $t('inserts.no_inserts') }}</p>
                        <button
                            @click="openCreateModal('insert')"
                            class="mt-3 text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400"
                        >
                            {{ $t('inserts.create_first_insert') }}
                        </button>
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="insert in inserts"
                            :key="insert.id"
                            class="group relative overflow-hidden rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200/50 transition-all hover:shadow-md dark:bg-slate-900 dark:ring-slate-700/50"
                        >
                            <div class="flex items-start justify-between">
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-semibold text-slate-900 dark:text-white">{{ insert.name }}</h3>
                                    <p v-if="insert.description" class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ insert.description }}</p>
                                    <div v-if="insert.content" class="mt-2 rounded-lg bg-slate-50 p-2 dark:bg-slate-800">
                                        <pre class="max-h-20 overflow-hidden text-xs text-slate-600 dark:text-slate-400">{{ insert.content?.substring(0, 200) }}{{ insert.content?.length > 200 ? '...' : '' }}</pre>
                                    </div>
                                </div>
                                <div class="ml-4 flex items-center gap-1">
                                    <button
                                        @click="openEditModal(insert)"
                                        class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300"
                                        :title="$t('common.edit')"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button
                                        @click="showDeleteConfirm = insert.id"
                                        class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                                        :title="$t('common.delete')"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Delete Confirmation -->
                            <div v-if="showDeleteConfirm === insert.id" class="absolute inset-0 flex items-center justify-center bg-slate-900/90 backdrop-blur-sm">
                                <div class="p-4 text-center">
                                    <p class="mb-3 text-sm text-white">{{ $t('inserts.confirm_delete') }}</p>
                                    <div class="flex justify-center gap-2">
                                        <button 
                                            @click="showDeleteConfirm = null"
                                            class="rounded-lg bg-slate-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-500"
                                        >
                                            {{ $t('common.cancel') }}
                                        </button>
                                        <button 
                                            @click="confirmDelete(insert.id)"
                                            class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-500"
                                        >
                                            {{ $t('common.delete') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Signatures -->
                <div>
                    <div class="mb-4 flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-500">
                            <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-slate-900 dark:text-white">{{ $t('inserts.your_signatures') }}</h2>
                            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $t('inserts.your_signatures_desc') }}</p>
                        </div>
                    </div>

                    <div v-if="signatures.length === 0" class="rounded-xl border-2 border-dashed border-slate-200 bg-white py-8 text-center dark:border-slate-700 dark:bg-slate-900">
                        <svg class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                        </svg>
                        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ $t('inserts.no_signatures') }}</p>
                        <button
                            @click="openCreateModal('signature')"
                            class="mt-3 text-sm font-medium text-emerald-600 hover:text-emerald-500 dark:text-emerald-400"
                        >
                            {{ $t('inserts.create_first_signature') }}
                        </button>
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="signature in signatures"
                            :key="signature.id"
                            class="group relative overflow-hidden rounded-xl bg-white p-4 shadow-sm ring-1 ring-emerald-200/50 transition-all hover:shadow-md dark:bg-slate-900 dark:ring-emerald-800/50"
                        >
                            <div class="flex items-start justify-between">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-semibold text-slate-900 dark:text-white">{{ signature.name }}</h3>
                                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                                            {{ $t('inserts.type_signature') }}
                                        </span>
                                    </div>
                                    <p v-if="signature.description" class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ signature.description }}</p>
                                    <div v-if="signature.content" class="mt-2 rounded-lg bg-emerald-50 p-2 dark:bg-emerald-900/20">
                                        <pre class="max-h-20 overflow-hidden text-xs text-slate-600 dark:text-slate-400">{{ signature.content?.substring(0, 200) }}{{ signature.content?.length > 200 ? '...' : '' }}</pre>
                                    </div>
                                </div>
                                <div class="ml-4 flex items-center gap-1">
                                    <button
                                        @click="openEditModal(signature)"
                                        class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300"
                                        :title="$t('common.edit')"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button
                                        @click="showDeleteConfirm = signature.id"
                                        class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                                        :title="$t('common.delete')"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Delete Confirmation -->
                            <div v-if="showDeleteConfirm === signature.id" class="absolute inset-0 flex items-center justify-center bg-slate-900/90 backdrop-blur-sm">
                                <div class="p-4 text-center">
                                    <p class="mb-3 text-sm text-white">{{ $t('inserts.confirm_delete') }}</p>
                                    <div class="flex justify-center gap-2">
                                        <button 
                                            @click="showDeleteConfirm = null"
                                            class="rounded-lg bg-slate-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-500"
                                        >
                                            {{ $t('common.cancel') }}
                                        </button>
                                        <button 
                                            @click="confirmDelete(signature.id)"
                                            class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-500"
                                        >
                                            {{ $t('common.delete') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create/Edit Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/80 p-4 backdrop-blur-sm">
                <div class="w-full max-w-2xl rounded-2xl bg-white shadow-2xl dark:bg-slate-900">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-slate-700">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">
                            {{ editingItem ? $t('inserts.edit') : (form.type === 'signature' ? $t('inserts.new_signature') : $t('inserts.new_insert')) }}
                        </h3>
                        <button @click="closeModal" class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 dark:hover:bg-slate-800">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <form @submit.prevent="submitForm" class="p-6">
                        <div class="space-y-4">
                            <!-- Type selector (only when creating) -->
                            <div v-if="!editingItem" class="flex gap-3">
                                <button
                                    type="button"
                                    @click="form.type = 'insert'"
                                    :class="form.type === 'insert' ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300' : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800'"
                                    class="flex flex-1 items-center justify-center gap-2 rounded-xl border-2 px-4 py-3 font-medium transition-all"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    {{ $t('inserts.type_insert') }}
                                </button>
                                <button
                                    type="button"
                                    @click="form.type = 'signature'"
                                    :class="form.type === 'signature' ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800'"
                                    class="flex flex-1 items-center justify-center gap-2 rounded-xl border-2 px-4 py-3 font-medium transition-all"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                    {{ $t('inserts.type_signature') }}
                                </button>
                            </div>

                            <!-- Name -->
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    {{ $t('inserts.name') }} *
                                </label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    required
                                    class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    :placeholder="$t('inserts.name_placeholder')"
                                />
                                <p v-if="form.errors.name" class="mt-1 text-sm text-red-500">{{ form.errors.name }}</p>
                            </div>

                            <!-- Description -->
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    {{ $t('inserts.description') }}
                                </label>
                                <input
                                    v-model="form.description"
                                    type="text"
                                    class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-900 placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    :placeholder="$t('inserts.description_placeholder')"
                                />
                            </div>

                            <!-- Content HTML -->
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    {{ $t('inserts.content_html') }}
                                </label>
                                <textarea
                                    v-model="form.content"
                                    rows="6"
                                    class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 font-mono text-sm text-slate-900 placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    :placeholder="$t('inserts.content_html_placeholder')"
                                ></textarea>
                            </div>

                            <!-- Content Plain -->
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                    {{ $t('inserts.content_plain') }}
                                </label>
                                <textarea
                                    v-model="form.content_plain"
                                    rows="4"
                                    class="w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 font-mono text-sm text-slate-900 placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                    :placeholder="$t('inserts.content_plain_placeholder')"
                                ></textarea>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $t('inserts.content_plain_hint') }}</p>
                            </div>
                        </div>

                        <!-- Modal Footer -->
                        <div class="mt-6 flex items-center justify-end gap-3">
                            <button
                                type="button"
                                @click="closeModal"
                                class="rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                            >
                                {{ $t('common.cancel') }}
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                :class="form.type === 'signature' ? 'from-emerald-600 to-teal-600 shadow-emerald-500/30 hover:from-emerald-500 hover:to-teal-500' : 'from-indigo-600 to-purple-600 shadow-indigo-500/30 hover:from-indigo-500 hover:to-purple-500'"
                                class="rounded-xl bg-gradient-to-r px-5 py-2.5 text-sm font-semibold text-white shadow-lg transition-all disabled:opacity-50"
                            >
                                {{ form.processing ? $t('common.saving') : (editingItem ? $t('common.save') : $t('common.create')) }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
