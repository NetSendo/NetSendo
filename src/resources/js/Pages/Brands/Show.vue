<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const { t } = useI18n();

const props = defineProps({
    brand: Object,
    logoColors: Array,
});

const extractingColors = ref(false);
const extractedColors = ref(props.logoColors || []);

const extractColors = async () => {
    if (!props.brand.logo) return;

    extractingColors.value = true;
    try {
        const response = await fetch(route('brands.extract-colors', props.brand.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
            },
        });
        const data = await response.json();
        if (data.success) {
            extractedColors.value = data.colors;
        }
    } catch (error) {
        console.error('Error extracting colors:', error);
    } finally {
        extractingColors.value = false;
    }
};

const deleteBrand = async () => {
    if (!confirm(t('brands.confirm_delete'))) return;
    router.delete(route('brands.destroy', props.brand.id));
};
</script>

<template>
    <Head :title="brand.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {{ brand.name }}
                </h2>
                <div class="flex items-center gap-2">
                    <a :href="route('brands.index')" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        ← {{ $t('common.back') }}
                    </a>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <!-- Brand Card -->
                <div class="overflow-hidden rounded-xl bg-white shadow-lg dark:bg-gray-800">
                    <!-- Color Bar -->
                    <div class="flex h-4">
                        <div v-if="brand.primary_color" class="flex-1" :style="{ backgroundColor: brand.primary_color }"></div>
                        <div v-if="brand.secondary_color" class="flex-1" :style="{ backgroundColor: brand.secondary_color }"></div>
                        <div v-if="!brand.primary_color && !brand.secondary_color" class="flex-1 bg-gradient-to-r from-indigo-500 to-purple-500"></div>
                    </div>

                    <div class="p-6">
                        <!-- Header -->
                        <div class="flex items-start gap-6 mb-6">
                            <div v-if="brand.logo" class="h-24 w-24 flex-shrink-0 overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-700">
                                <img :src="brand.logo.url || '/storage/' + brand.logo.stored_path" :alt="brand.name" class="h-full w-full object-contain" />
                            </div>
                            <div v-else class="flex h-24 w-24 flex-shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-100 to-purple-100 text-4xl dark:from-indigo-900 dark:to-purple-900">
                                🏷️
                            </div>
                            <div class="flex-1">
                                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ brand.name }}</h1>
                                <p v-if="brand.description" class="mt-2 text-gray-600 dark:text-gray-400">
                                    {{ brand.description }}
                                </p>
                                <div class="mt-4 flex items-center gap-4">
                                    <div v-if="brand.primary_color" class="flex items-center gap-2">
                                        <div class="h-6 w-6 rounded" :style="{ backgroundColor: brand.primary_color }"></div>
                                        <span class="text-sm text-gray-600 dark:text-gray-400">{{ brand.primary_color }}</span>
                                    </div>
                                    <div v-if="brand.secondary_color" class="flex items-center gap-2">
                                        <div class="h-6 w-6 rounded" :style="{ backgroundColor: brand.secondary_color }"></div>
                                        <span class="text-sm text-gray-600 dark:text-gray-400">{{ brand.secondary_color }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <a
                                    :href="route('brands.edit', brand.id)"
                                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    {{ $t('common.edit') }}
                                </a>
                                <button
                                    @click="deleteBrand"
                                    class="rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-600 dark:text-red-400"
                                >
                                    {{ $t('common.delete') }}
                                </button>
                            </div>
                        </div>

                        <!-- Logo Colors -->
                        <div v-if="brand.logo" class="border-t pt-6 dark:border-gray-700">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    {{ $t('media.show.extracted_colors') }}
                                </h3>
                                <button
                                    @click="extractColors"
                                    :disabled="extractingColors"
                                    class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 disabled:opacity-50"
                                >
                                    {{ extractingColors ? '...' : $t('brands.extract_colors') }}
                                </button>
                            </div>
                            <div v-if="extractedColors.length > 0" class="flex flex-wrap gap-3">
                                <div
                                    v-for="color in extractedColors"
                                    :key="color.id"
                                    class="group relative"
                                >
                                    <div
                                        class="h-12 w-12 rounded-lg shadow-md transition-transform hover:scale-110"
                                        :style="{ backgroundColor: color.hex_color }"
                                    ></div>
                                    <div class="absolute -bottom-6 left-1/2 -translate-x-1/2 whitespace-nowrap text-xs text-gray-500 opacity-0 transition-opacity group-hover:opacity-100">
                                        {{ color.hex_color }}
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-gray-500 dark:text-gray-400">
                                {{ $t('colors.no_media_colors') }}
                            </p>
                        </div>

                        <!-- Color Palettes -->
                        <div v-if="brand.palettes?.length" class="border-t pt-6 mt-6 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                                {{ $t('brands.palettes.title') }}
                            </h3>
                            <div class="space-y-4">
                                <div
                                    v-for="palette in brand.palettes"
                                    :key="palette.id"
                                    class="rounded-lg border p-4 dark:border-gray-700"
                                >
                                    <div class="flex items-center justify-between mb-3">
                                        <span class="font-medium text-gray-900 dark:text-white">
                                            {{ palette.name }}
                                            <span v-if="palette.is_default" class="ml-2 rounded bg-indigo-100 px-2 py-0.5 text-xs text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300">
                                                {{ $t('brands.palettes.is_default') }}
                                            </span>
                                        </span>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <div
                                            v-for="(color, idx) in palette.colors"
                                            :key="idx"
                                            class="h-8 w-8 rounded"
                                            :style="{ backgroundColor: color }"
                                            :title="color"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Brand Media -->
                        <div v-if="brand.media?.length" class="border-t pt-6 mt-6 dark:border-gray-700">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                                {{ $t('media.title') }} ({{ brand.media.length }})
                            </h3>
                            <div class="grid grid-cols-6 gap-3">
                                <div
                                    v-for="media in brand.media.slice(0, 12)"
                                    :key="media.id"
                                    class="aspect-square overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-700"
                                >
                                    <img
                                        :src="media.url || '/storage/' + media.stored_path"
                                        class="h-full w-full object-cover"
                                    />
                                </div>
                            </div>
                            <a
                                v-if="brand.media.length > 12"
                                :href="route('media.index') + '?brand_id=' + brand.id"
                                class="mt-4 inline-block text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                            >
                                {{ $t('common.view_all') }} →
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
