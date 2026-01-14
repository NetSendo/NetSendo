<script setup>
import { ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const { t } = useI18n();

const props = defineProps({
    brands: Array,
});

const deleteBrand = async (id) => {
    if (!confirm(t('brands.confirm_delete'))) return;
    router.delete(route('brands.destroy', id));
};
</script>

<template>
    <Head :title="$t('brands.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {{ $t('brands.title') }}
                </h2>
                <div class="flex items-center gap-2">
                    <a :href="route('media.index')" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                        🖼️ {{ $t('media.title') }}
                    </a>
                    <a :href="route('brands.create')" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ $t('brands.create') }}
                    </a>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Empty State -->
                <div v-if="brands.length === 0" class="rounded-lg bg-white p-12 text-center shadow dark:bg-gray-800">
                    <div class="mx-auto mb-4 h-16 w-16 rounded-full bg-indigo-100 p-4 text-3xl dark:bg-indigo-900">🎨</div>
                    <h3 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">{{ $t('brands.empty_title') }}</h3>
                    <p class="mb-4 text-gray-500 dark:text-gray-400">{{ $t('brands.empty_description') }}</p>
                    <a :href="route('brands.create')" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        {{ $t('brands.create_first') }}
                    </a>
                </div>

                <!-- Brands Grid -->
                <div v-else class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="brand in brands"
                        :key="brand.id"
                        class="group relative overflow-hidden rounded-xl bg-white shadow-lg transition-all hover:shadow-xl dark:bg-gray-800"
                    >
                        <!-- Color Bar -->
                        <div class="flex h-3">
                            <div v-if="brand.primary_color" class="flex-1" :style="{ backgroundColor: brand.primary_color }"></div>
                            <div v-if="brand.secondary_color" class="flex-1" :style="{ backgroundColor: brand.secondary_color }"></div>
                            <div v-if="!brand.primary_color && !brand.secondary_color" class="flex-1 bg-gradient-to-r from-indigo-500 to-purple-500"></div>
                        </div>

                        <div class="p-6">
                            <!-- Logo & Name -->
                            <div class="mb-4 flex items-center gap-4">
                                <div v-if="brand.logo" class="h-14 w-14 flex-shrink-0 overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-700">
                                    <img :src="brand.logo.url || '/storage/' + brand.logo.stored_path" :alt="brand.name" class="h-full w-full object-contain" />
                                </div>
                                <div v-else class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-100 to-purple-100 text-2xl dark:from-indigo-900 dark:to-purple-900">
                                    🏷️
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="truncate text-lg font-semibold text-gray-900 dark:text-white">{{ brand.name }}</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ brand.media_count || 0 }} {{ $t('media.items') }}
                                    </p>
                                </div>
                            </div>

                            <!-- Description -->
                            <p v-if="brand.description" class="mb-4 line-clamp-2 text-sm text-gray-600 dark:text-gray-400">
                                {{ brand.description }}
                            </p>

                            <!-- Color Palette Preview -->
                            <div v-if="brand.palettes?.length" class="mb-4">
                                <p class="mb-2 text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ $t('colors.palette') }}</p>
                                <div class="flex gap-1">
                                    <template v-for="palette in brand.palettes.slice(0, 1)" :key="palette.id">
                                        <div
                                            v-for="(color, idx) in (palette.colors || []).slice(0, 8)"
                                            :key="idx"
                                            class="h-6 w-6 rounded"
                                            :style="{ backgroundColor: color }"
                                        ></div>
                                    </template>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center gap-2">
                                <a
                                    :href="route('brands.edit', brand.id)"
                                    class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-center text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                                >
                                    {{ $t('common.edit') }}
                                </a>
                                <a
                                    :href="route('brands.show', brand.id)"
                                    class="flex-1 rounded-lg bg-indigo-600 px-3 py-2 text-center text-sm font-medium text-white hover:bg-indigo-700"
                                >
                                    {{ $t('common.view') }}
                                </a>
                                <button
                                    @click="deleteBrand(brand.id)"
                                    class="rounded-lg border border-red-300 px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-600 dark:text-red-400 dark:hover:bg-red-900/20"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
