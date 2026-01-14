<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const { t } = useI18n();

const props = defineProps({
    brand: Object,
    userMedia: {
        type: Array,
        default: () => [],
    },
});

const isEditing = computed(() => !!props.brand);

const form = useForm({
    name: props.brand?.name || '',
    description: props.brand?.description || '',
    primary_color: props.brand?.primary_color || '#6366F1',
    secondary_color: props.brand?.secondary_color || '#8B5CF6',
    logo_media_id: props.brand?.logo_media_id || null,
});

const showMediaPicker = ref(false);
const selectedLogo = ref(props.brand?.logo || null);

const selectLogo = (media) => {
    form.logo_media_id = media.id;
    selectedLogo.value = media;
    showMediaPicker.value = false;
};

const removeLogo = () => {
    form.logo_media_id = null;
    selectedLogo.value = null;
};

const submit = () => {
    if (isEditing.value) {
        form.put(route('brands.update', props.brand.id));
    } else {
        form.post(route('brands.store'));
    }
};
</script>

<template>
    <Head :title="isEditing ? $t('brands.edit') : $t('brands.create')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {{ isEditing ? $t('brands.edit') : $t('brands.create') }}
                </h2>
                <a :href="route('brands.index')" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    ← {{ $t('common.back') }}
                </a>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-6 rounded-xl bg-white p-6 shadow-lg dark:bg-gray-800">
                    <!-- Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $t('brands.fields.name') }} *
                        </label>
                        <input
                            type="text"
                            id="name"
                            v-model="form.name"
                            :placeholder="$t('brands.fields.name_placeholder')"
                            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            required
                        />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $t('brands.fields.description') }}
                        </label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            :placeholder="$t('brands.fields.description_placeholder')"
                            rows="3"
                            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        ></textarea>
                    </div>

                    <!-- Logo -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ $t('brands.fields.logo') }}
                        </label>
                        <div class="flex items-center gap-4">
                            <div v-if="selectedLogo" class="relative">
                                <img
                                    :src="selectedLogo.url || '/storage/' + selectedLogo.stored_path"
                                    class="h-20 w-20 rounded-lg object-contain bg-gray-100 dark:bg-gray-700"
                                />
                                <button
                                    type="button"
                                    @click="removeLogo"
                                    class="absolute -right-2 -top-2 rounded-full bg-red-500 p-1 text-white hover:bg-red-600"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            <button
                                v-if="!selectedLogo"
                                type="button"
                                @click="showMediaPicker = true"
                                class="flex h-20 w-20 items-center justify-center rounded-lg border-2 border-dashed border-gray-300 text-gray-400 hover:border-indigo-500 hover:text-indigo-500 dark:border-gray-600"
                            >
                                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </button>
                            <button
                                v-else
                                type="button"
                                @click="showMediaPicker = true"
                                class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                            >
                                {{ $t('common.change') }}
                            </button>
                        </div>
                    </div>

                    <!-- Colors -->
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="primary_color" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ $t('brands.fields.primary_color') }}
                            </label>
                            <div class="mt-1 flex items-center gap-2">
                                <input
                                    type="color"
                                    id="primary_color"
                                    v-model="form.primary_color"
                                    class="h-10 w-14 cursor-pointer rounded border border-gray-300 dark:border-gray-600"
                                />
                                <input
                                    type="text"
                                    v-model="form.primary_color"
                                    class="block flex-1 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                        </div>
                        <div>
                            <label for="secondary_color" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ $t('brands.fields.secondary_color') }}
                            </label>
                            <div class="mt-1 flex items-center gap-2">
                                <input
                                    type="color"
                                    id="secondary_color"
                                    v-model="form.secondary_color"
                                    class="h-10 w-14 cursor-pointer rounded border border-gray-300 dark:border-gray-600"
                                />
                                <input
                                    type="text"
                                    v-model="form.secondary_color"
                                    class="block flex-1 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="flex items-center justify-end gap-4 pt-4 border-t dark:border-gray-700">
                        <a
                            :href="route('brands.index')"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                            {{ $t('common.cancel') }}
                        </a>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-indigo-600 px-6 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                        >
                            {{ form.processing ? '...' : (isEditing ? $t('common.save') : $t('brands.create')) }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Media Picker Modal -->
        <div v-if="showMediaPicker" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showMediaPicker = false">
            <div class="mx-4 max-h-[80vh] w-full max-w-2xl overflow-hidden rounded-xl bg-white shadow-2xl dark:bg-gray-800">
                <div class="flex items-center justify-between border-b p-4 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $t('brands.fields.select_logo') }}</h3>
                    <button @click="showMediaPicker = false" class="text-gray-500 hover:text-gray-700">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="max-h-96 overflow-y-auto p-4">
                    <div v-if="userMedia.length === 0" class="py-8 text-center text-gray-500">
                        {{ $t('media.no_media_found') }}
                    </div>
                    <div v-else class="grid grid-cols-4 gap-3">
                        <button
                            v-for="media in userMedia"
                            :key="media.id"
                            type="button"
                            @click="selectLogo(media)"
                            class="group relative aspect-square overflow-hidden rounded-lg border-2 transition-colors"
                            :class="form.logo_media_id === media.id ? 'border-indigo-500' : 'border-transparent hover:border-gray-300 dark:hover:border-gray-600'"
                        >
                            <img
                                :src="media.url || '/storage/' + media.stored_path"
                                class="h-full w-full object-cover"
                            />
                            <div v-if="form.logo_media_id === media.id" class="absolute inset-0 flex items-center justify-center bg-indigo-500/30">
                                <svg class="h-6 w-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
