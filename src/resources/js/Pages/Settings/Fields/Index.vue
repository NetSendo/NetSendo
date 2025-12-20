<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    fields: {
        type: Array,
        default: () => [],
    },
    standardFields: {
        type: Object,
        default: () => ({}),
    },
});

// Toast notification
const toast = ref(null);
const showToast = (message, success = true) => {
    toast.value = { message, success };
    setTimeout(() => {
        toast.value = null;
    }, 4000);
};

// Delete modal
const showDeleteModal = ref(false);
const deletingField = ref(null);

// Field type icons/labels
const fieldTypeLabels = computed(() => ({
    text: { label: t('fields.types.text'), icon: '📝' },
    number: { label: t('fields.types.number'), icon: '🔢' },
    date: { label: t('fields.types.date'), icon: '📅' },
    select: { label: t('fields.types.select'), icon: '📋' },
    radio: { label: t('fields.types.radio'), icon: '⭕' },
    checkbox: { label: t('fields.types.checkbox'), icon: '☑️' },
}));

// Confirm delete
const confirmDelete = (field) => {
    deletingField.value = field;
    showDeleteModal.value = true;
};

// Close delete modal
const closeDeleteModal = () => {
    showDeleteModal.value = false;
    deletingField.value = null;
};

// Delete field
const deleteField = () => {
    if (!deletingField.value) return;
    
    router.delete(route('settings.fields.destroy', deletingField.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeDeleteModal();
            showToast(t('fields.notifications.deleted'));
        },
        onError: () => {
            showToast(t('common.notifications.error'), false);
        },
    });
};

// Copy placeholder to clipboard
const copyPlaceholder = (fieldName) => {
    navigator.clipboard.writeText(`[[${fieldName}]]`);
    showToast(t('fields.notifications.copied'));
};

// Standard fields as array for display
const standardFieldsList = computed(() => {
    return Object.entries(props.standardFields).map(([name, info]) => ({
        name,
        label: t(`fields.standard_labels.${name}`, info.label),
        description: t(`fields.standard_descriptions.${name}`, info.description),
        placeholder: `[[${name}]]`,
    }));
});
</script>

<template>
    <Head :title="$t('fields.title')" />

    <AuthenticatedLayout>
        <!-- Toast Notification -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-300"
                enter-from-class="opacity-0 translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 translate-y-2"
            >
                <div 
                    v-if="toast"
                    class="fixed bottom-6 right-6 z-50 flex items-center gap-3 rounded-xl px-5 py-4 shadow-lg"
                    :class="toast.success ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white'"
                >
                    <svg v-if="toast.success" class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <svg v-else class="h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="font-medium">{{ toast.message }}</span>
                    <button @click="toast = null" class="ml-2 opacity-80 hover:opacity-100">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </Transition>
        </Teleport>

        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {{ $t('fields.title') }}
                </h2>
                <Link
                    :href="route('settings.fields.create')"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ $t('fields.add_new') }}
                </Link>
            </div>
        </template>

        <div class="space-y-8">
            <!-- Standard Fields Section -->
            <div>
                <h3 class="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-800 dark:text-gray-200">
                    <svg class="h-5 w-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                    </svg>
                    {{ $t('fields.standard_fields') }}
                </h3>
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                    {{ $t('fields.standard_description') }}
                </p>
                
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="field in standardFieldsList"
                        :key="field.name"
                        class="group relative rounded-lg border border-gray-200 bg-white p-4 transition-all hover:border-indigo-300 hover:shadow-sm dark:border-slate-700 dark:bg-slate-800 dark:hover:border-indigo-600"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <h4 class="font-medium text-gray-900 dark:text-white">{{ field.label }}</h4>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ field.description }}</p>
                            </div>
                            <button
                                @click="copyPlaceholder(field.name)"
                                class="rounded p-1.5 text-gray-400 opacity-0 transition-all hover:bg-gray-100 hover:text-gray-600 group-hover:opacity-100 dark:hover:bg-slate-700 dark:hover:text-gray-300"
                                :title="$t('fields.copy_placeholder')"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                </svg>
                            </button>
                        </div>
                        <div class="mt-3">
                            <code class="rounded bg-indigo-50 px-2 py-1 text-sm font-mono text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                                {{ field.placeholder }}
                            </code>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Custom Fields Section -->
            <div>
                <h3 class="mb-4 flex items-center gap-2 text-lg font-semibold text-gray-800 dark:text-gray-200">
                    <svg class="h-5 w-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                    </svg>
                    {{ $t('fields.custom_fields') }}
                    <span class="ml-2 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-normal text-gray-600 dark:bg-slate-700 dark:text-gray-400">
                        {{ fields.length }}
                    </span>
                </h3>
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                    {{ $t('fields.custom_description') }}
                </p>

                <!-- Empty State -->
                <div v-if="fields.length === 0" class="rounded-xl border-2 border-dashed border-gray-300 p-12 text-center dark:border-slate-700">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                    </svg>
                    <h3 class="mt-4 text-lg font-medium text-gray-900 dark:text-white">{{ $t('fields.empty.title') }}</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $t('fields.empty.description') }}</p>
                    <Link
                        :href="route('settings.fields.create')"
                        class="mt-6 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ $t('fields.add_first') }}
                    </Link>
                </div>

                <!-- Fields Table -->
                <div v-else class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-slate-700 dark:bg-slate-800">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                        <thead class="bg-gray-50 dark:bg-slate-900/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {{ $t('fields.table.name') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {{ $t('fields.table.type') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {{ $t('fields.table.placeholder') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {{ $t('fields.table.settings') }}
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    {{ $t('common.actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                            <tr v-for="field in fields" :key="field.id" class="hover:bg-gray-50 dark:hover:bg-slate-700/50">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">{{ field.label }}</div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ field.description || '-' }}</div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-slate-700 dark:text-gray-300">
                                        <span>{{ fieldTypeLabels[field.type]?.icon }}</span>
                                        {{ fieldTypeLabels[field.type]?.label || field.type }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <button
                                        @click="copyPlaceholder(field.name)"
                                        class="group flex items-center gap-2 rounded bg-indigo-50 px-2 py-1 transition-colors hover:bg-indigo-100 dark:bg-indigo-900/30 dark:hover:bg-indigo-900/50"
                                    >
                                        <code class="font-mono text-sm text-indigo-700 dark:text-indigo-300">
                                            [[<span>{{ field.name }}</span>]]
                                        </code>
                                        <svg class="h-3.5 w-3.5 text-indigo-400 opacity-0 transition-opacity group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                        </svg>
                                    </button>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="flex gap-2">
                                        <span v-if="field.is_required" class="rounded bg-rose-100 px-2 py-0.5 text-xs text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">
                                            {{ $t('fields.required') }}
                                        </span>
                                        <span v-if="field.is_public" class="rounded bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                            {{ $t('fields.public') }}
                                        </span>
                                        <span v-if="field.is_static" class="rounded bg-amber-100 px-2 py-0.5 text-xs text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                                            {{ $t('fields.static') }}
                                        </span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link
                                            :href="route('settings.fields.edit', field.id)"
                                            class="rounded-lg p-2 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-slate-700 dark:hover:text-gray-300"
                                        >
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </Link>
                                        <button
                                            @click="confirmDelete(field)"
                                            class="rounded-lg p-2 text-rose-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-900/20"
                                        >
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="closeDeleteModal">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ $t('fields.delete_modal.title') }}
                </h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{ $t('fields.delete_modal.message', { name: deletingField?.label }) }}
                </p>
                <p class="mt-2 text-sm text-rose-600 dark:text-rose-400">
                    {{ $t('fields.delete_modal.warning') }}
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeDeleteModal">
                        {{ $t('common.cancel') }}
                    </SecondaryButton>
                    <DangerButton @click="deleteField">
                        {{ $t('common.delete') }}
                    </DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
