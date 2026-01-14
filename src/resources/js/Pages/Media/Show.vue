<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const { t } = useI18n();

const props = defineProps({
    media: Object,
});

// Editable fields
const isEditing = ref(false);
const editForm = ref({
    type: props.media.type,
    alt_text: props.media.alt_text || '',
});
const isSaving = ref(false);

const startEditing = () => {
    editForm.value = {
        type: props.media.type,
        alt_text: props.media.alt_text || '',
    };
    isEditing.value = true;
};

const cancelEditing = () => {
    isEditing.value = false;
};

const saveChanges = async () => {
    isSaving.value = true;
    try {
        await axios.put(route('media.update', props.media.id), editForm.value);
        router.reload();
        isEditing.value = false;
    } catch (error) {
        console.error('Save failed:', error);
        alert(error.response?.data?.message || t('common.error'));
    } finally {
        isSaving.value = false;
    }
};

const copyUrl = async () => {
    await navigator.clipboard.writeText(props.media.url);
    alert(t('media.show.copy_url') + ' ✓');
};

const deleteMedia = async () => {
    if (!confirm(t('media.confirm_delete'))) return;
    try {
        await axios.delete(route('media.destroy', props.media.id));
        router.visit(route('media.index'));
    } catch (error) {
        console.error('Delete failed:', error);
    }
};

const formatSize = (bytes) => {
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
    return bytes + ' B';
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('pl-PL', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const typeOptions = [
    { value: 'image', label: 'media.type_image', icon: '🖼️' },
    { value: 'logo', label: 'media.type_logo', icon: '🏷️' },
    { value: 'icon', label: 'media.type_icon', icon: '⭐' },
    { value: 'document', label: 'media.type_document', icon: '📄' },
];
</script>

<template>
    <Head :title="media.original_name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {{ $t('media.show.title') }}
                </h2>
                <a :href="route('media.index')" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    ← {{ $t('common.back') }}
                </a>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-xl bg-white shadow-lg dark:bg-gray-800">
                    <div class="grid gap-6 md:grid-cols-2">
                        <!-- Preview -->
                        <div class="flex items-center justify-center bg-gray-100 p-6 dark:bg-gray-900">
                            <img
                                v-if="media.mime_type?.startsWith('image/')"
                                :src="media.url"
                                :alt="media.alt_text || media.original_name"
                                class="max-h-96 max-w-full rounded-lg object-contain"
                            />
                            <div v-else class="flex flex-col items-center text-gray-400">
                                <svg class="h-24 w-24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                <span class="mt-2 text-sm">{{ media.mime_type }}</span>
                            </div>
                        </div>

                        <!-- Details -->
                        <div class="p-6">
                            <div class="mb-4 flex items-center justify-between">
                                <h1 class="text-xl font-bold text-gray-900 dark:text-white">
                                    {{ media.original_name }}
                                </h1>
                                <button
                                    v-if="!isEditing"
                                    @click="startEditing"
                                    class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                                >
                                    ✏️ {{ $t('common.edit') }}
                                </button>
                            </div>

                            <!-- View Mode -->
                            <dl v-if="!isEditing" class="space-y-3">
                                <div class="flex justify-between border-b pb-2 dark:border-gray-700">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $t('media.show.size') }}</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ formatSize(media.size) }}</dd>
                                </div>

                                <div v-if="media.width && media.height" class="flex justify-between border-b pb-2 dark:border-gray-700">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $t('media.show.dimensions') }}</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ media.width }} × {{ media.height }} px</dd>
                                </div>

                                <div class="flex justify-between border-b pb-2 dark:border-gray-700">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $t('media.show.type') }}</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-white">
                                        <span class="inline-flex items-center gap-1">
                                            {{ typeOptions.find(o => o.value === media.type)?.icon }}
                                            {{ $t(typeOptions.find(o => o.value === media.type)?.label || 'media.type_image') }}
                                        </span>
                                    </dd>
                                </div>

                                <div class="flex justify-between border-b pb-2 dark:border-gray-700">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $t('media.show.uploaded') }}</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ formatDate(media.created_at) }}</dd>
                                </div>

                                <div v-if="media.brand" class="flex justify-between border-b pb-2 dark:border-gray-700">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $t('media.show.brand') }}</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ media.brand.name }}</dd>
                                </div>

                                <div v-if="media.folder" class="flex justify-between border-b pb-2 dark:border-gray-700">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $t('media.show.folder') }}</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ media.folder.name }}</dd>
                                </div>

                                <div v-if="media.alt_text" class="flex justify-between border-b pb-2 dark:border-gray-700">
                                    <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $t('media.show.alt_text') }}</dt>
                                    <dd class="text-sm font-medium text-gray-900 dark:text-white">{{ media.alt_text }}</dd>
                                </div>
                            </dl>

                            <!-- Edit Mode -->
                            <div v-else class="space-y-4">
                                <!-- Type Selection -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        {{ $t('media.show.type') }}
                                    </label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <button
                                            v-for="option in typeOptions"
                                            :key="option.value"
                                            type="button"
                                            @click="editForm.type = option.value"
                                            class="flex items-center gap-2 rounded-lg border-2 p-3 text-sm font-medium transition-colors"
                                            :class="editForm.type === option.value
                                                ? 'border-indigo-500 bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300'
                                                : 'border-gray-200 text-gray-700 hover:border-gray-300 dark:border-gray-600 dark:text-gray-300'"
                                        >
                                            <span class="text-lg">{{ option.icon }}</span>
                                            {{ $t(option.label) }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Alt Text -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        {{ $t('media.show.alt_text') }}
                                    </label>
                                    <input
                                        v-model="editForm.alt_text"
                                        type="text"
                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                        :placeholder="$t('media.show.alt_text')"
                                    />
                                </div>

                                <!-- Edit Actions -->
                                <div class="flex gap-3 pt-2">
                                    <button
                                        @click="saveChanges"
                                        :disabled="isSaving"
                                        class="flex-1 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                                    >
                                        {{ isSaving ? '...' : $t('common.save') }}
                                    </button>
                                    <button
                                        @click="cancelEditing"
                                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300"
                                    >
                                        {{ $t('common.cancel') }}
                                    </button>
                                </div>
                            </div>

                            <!-- Extracted Colors -->
                            <div v-if="media.colors?.length && !isEditing" class="mt-6">
                                <h3 class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ $t('media.show.extracted_colors') }}
                                </h3>
                                <div class="flex flex-wrap gap-2">
                                    <div
                                        v-for="color in media.colors"
                                        :key="color.id"
                                        class="group relative"
                                    >
                                        <div
                                            class="h-10 w-10 rounded-lg shadow-md transition-transform hover:scale-110"
                                            :style="{ backgroundColor: color.hex_color }"
                                            :title="color.hex_color"
                                        ></div>
                                        <span v-if="color.is_dominant" class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-yellow-400 text-xs">★</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div v-if="!isEditing" class="mt-6 flex flex-wrap gap-3">
                                <button
                                    @click="copyUrl"
                                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    📋 {{ $t('media.show.copy_url') }}
                                </button>
                                <a
                                    :href="media.url"
                                    target="_blank"
                                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    🔗 {{ $t('common.open') }}
                                </a>
                                <button
                                    @click="deleteMedia"
                                    class="rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-600 dark:text-red-400"
                                >
                                    🗑️ {{ $t('common.delete') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
