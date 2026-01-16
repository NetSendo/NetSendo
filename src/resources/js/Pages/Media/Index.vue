<script setup>
import { ref, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { compressImages, formatFileSize } from '@/Composables/useImageCompression.js';

const { t } = useI18n();

const props = defineProps({
    media: Object,
    folders: Array,
    brands: Array,
    filters: Object,
});

const selectedMedia = ref([]);
const isDragging = ref(false);
const isUploading = ref(false);
const uploadProgress = ref(0);
const showUploadModal = ref(false);

// Compression state
const isCompressing = ref(false);
const compressionProgress = ref({ current: 0, total: 0 });
const compressionStats = ref(null);

// Filters
const search = ref(props.filters?.search || '');
const selectedBrand = ref(props.filters?.brand_id || '');
const selectedFolder = ref(props.filters?.folder_id || '');
const selectedType = ref(props.filters?.type || '');

const applyFilters = () => {
    router.get(route('media.index'), {
        search: search.value || undefined,
        brand_id: selectedBrand.value || undefined,
        folder_id: selectedFolder.value || undefined,
        type: selectedType.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    search.value = '';
    selectedBrand.value = '';
    selectedFolder.value = '';
    selectedType.value = '';
    router.get(route('media.index'));
};

// Selection
const toggleSelect = (id) => {
    const idx = selectedMedia.value.indexOf(id);
    if (idx === -1) {
        selectedMedia.value.push(id);
    } else {
        selectedMedia.value.splice(idx, 1);
    }
};

const selectAll = () => {
    selectedMedia.value = props.media.data.map(m => m.id);
};

const deselectAll = () => {
    selectedMedia.value = [];
};

// Upload
const handleDrop = async (e) => {
    e.preventDefault();
    isDragging.value = false;
    const files = e.dataTransfer.files;
    if (files.length) {
        await uploadFiles(files);
    }
};

const handleFileSelect = async (e) => {
    const files = e.target.files;
    if (files.length) {
        await uploadFiles(files);
    }
    e.target.value = '';
};

const uploadFiles = async (files) => {
    isCompressing.value = true;
    compressionProgress.value = { current: 0, total: files.length };
    compressionStats.value = null;

    try {
        // Compress images before upload
        const { files: compressedFiles, stats } = await compressImages(
            files,
            { maxWidth: 2048, maxHeight: 2048, quality: 0.8, maxSizeKB: 1024 },
            (current, total) => {
                compressionProgress.value = { current, total };
            }
        );

        compressionStats.value = stats;
        isCompressing.value = false;
        isUploading.value = true;
        uploadProgress.value = 0;

        const formData = new FormData();
        for (let i = 0; i < compressedFiles.length; i++) {
            formData.append('files[]', compressedFiles[i]);
        }
        if (selectedBrand.value) formData.append('brand_id', selectedBrand.value);
        if (selectedFolder.value) formData.append('folder_id', selectedFolder.value);

        const response = await axios.post(route('media.bulk-store'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
            onUploadProgress: (e) => {
                uploadProgress.value = Math.round((e.loaded / e.total) * 100);
            }
        });

        if (response.data?.success) {
            // Success - reload media list
            router.reload({ only: ['media'] });
        }
    } catch (error) {
        console.error('Upload failed:', error);
        alert(error.response?.data?.message || t('common.error'));
    } finally {
        isUploading.value = false;
        isCompressing.value = false;
        uploadProgress.value = 0;
        compressionStats.value = null;
    }
};

// Bulk actions
const bulkDelete = async () => {
    if (!confirm(t('media.confirm_bulk_delete'))) return;

    try {
        await axios.post(route('media.bulk-destroy'), { ids: selectedMedia.value });
        selectedMedia.value = [];
        router.reload({ only: ['media'] });
    } catch (error) {
        console.error('Bulk delete failed:', error);
    }
};

// Delete single
const deleteMedia = async (id) => {
    if (!confirm(t('media.confirm_delete'))) return;
    try {
        await axios.delete(route('media.destroy', id));
        router.reload({ only: ['media'] });
    } catch (error) {
        console.error('Delete failed:', error);
    }
};

// View details
const viewMedia = (id) => {
    router.visit(route('media.show', id));
};

// Format file size
const formatSize = (bytes) => {
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(2) + ' KB';
    return bytes + ' B';
};

// Get type icon
const getTypeIcon = (type) => {
    switch (type) {
        case 'logo': return '🏷️';
        case 'icon': return '⭐';
        case 'document': return '📄';
        default: return '🖼️';
    }
};
</script>

<template>
    <Head :title="$t('media.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {{ $t('media.title') }}
                </h2>
                <div class="flex items-center gap-2">
                    <a :href="route('brands.index')" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700">
                        🎨 {{ $t('brands.manage') }}
                    </a>
                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                        {{ $t('media.upload') }}
                        <input type="file" multiple accept="image/*,.pdf,.svg" class="hidden" @change="handleFileSelect" />
                    </label>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Filters -->
                <div class="mb-6 rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex-1">
                            <input
                                v-model="search"
                                type="text"
                                :placeholder="$t('media.search_placeholder')"
                                class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                @keyup.enter="applyFilters"
                            />
                        </div>
                        <select v-model="selectedBrand" @change="applyFilters" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="">{{ $t('media.all_brands') }}</option>
                            <option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
                        </select>
                        <select v-model="selectedType" @change="applyFilters" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                            <option value="">{{ $t('media.all_types') }}</option>
                            <option value="image">{{ $t('media.type_image') }}</option>
                            <option value="logo">{{ $t('media.type_logo') }}</option>
                            <option value="icon">{{ $t('media.type_icon') }}</option>
                        </select>
                        <button @click="applyFilters" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300">
                            {{ $t('common.filter') }}
                        </button>
                        <button @click="clearFilters" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400">
                            {{ $t('common.clear') }}
                        </button>
                    </div>
                </div>

                <!-- Bulk Actions -->
                <div v-if="selectedMedia.length > 0" class="mb-4 flex items-center gap-4 rounded-lg bg-indigo-50 p-3 dark:bg-indigo-900/30">
                    <span class="text-sm font-medium text-indigo-700 dark:text-indigo-300">
                        {{ selectedMedia.length }} {{ $t('media.selected') }}
                    </span>
                    <button @click="bulkDelete" class="rounded bg-red-600 px-3 py-1 text-sm text-white hover:bg-red-700">
                        {{ $t('common.delete') }}
                    </button>
                    <button @click="deselectAll" class="text-sm text-gray-600 hover:text-gray-800 dark:text-gray-400">
                        {{ $t('common.deselect_all') }}
                    </button>
                </div>

                <!-- Compression Progress -->
                <div v-if="isCompressing" class="mb-4 rounded-lg bg-purple-50 p-4 dark:bg-purple-900/30">
                    <div class="flex items-center gap-3">
                        <svg class="h-5 w-5 animate-spin text-purple-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-sm font-medium text-purple-700 dark:text-purple-300">
                            {{ $t('media.compressing') }} ({{ compressionProgress.current }}/{{ compressionProgress.total }})
                        </span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-purple-200">
                        <div class="h-full bg-purple-600 transition-all" :style="{ width: (compressionProgress.current / compressionProgress.total * 100) + '%' }"></div>
                    </div>
                </div>

                <!-- Compression Stats (shown briefly after compression) -->
                <div v-if="compressionStats && compressionStats.compressedCount > 0 && !isCompressing" class="mb-4 rounded-lg bg-green-50 p-3 dark:bg-green-900/30">
                    <div class="flex items-center gap-2 text-sm text-green-700 dark:text-green-300">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>
                            {{ $t('media.compressed_info', {
                                count: compressionStats.compressedCount,
                                saved: formatFileSize(compressionStats.totalOriginal - compressionStats.totalCompressed)
                            }) }}
                        </span>
                    </div>
                </div>

                <!-- Upload Progress -->
                <div v-if="isUploading" class="mb-4 rounded-lg bg-blue-50 p-4 dark:bg-blue-900/30">
                    <div class="flex items-center gap-3">
                        <svg class="h-5 w-5 animate-spin text-blue-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-sm font-medium text-blue-700 dark:text-blue-300">{{ $t('media.uploading') }} {{ uploadProgress }}%</span>
                    </div>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-blue-200">
                        <div class="h-full bg-blue-600 transition-all" :style="{ width: uploadProgress + '%' }"></div>
                    </div>
                </div>

                <!-- Drop Zone / Media Grid -->
                <div
                    @drop="handleDrop"
                    @dragover.prevent="isDragging = true"
                    @dragleave="isDragging = false"
                    class="min-h-[400px] rounded-lg border-2 border-dashed p-4 transition-colors"
                    :class="isDragging ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20' : 'border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-800'"
                >
                    <!-- Empty state -->
                    <div v-if="media.data.length === 0" class="flex flex-col items-center justify-center py-16">
                        <svg class="mb-4 h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p class="mb-2 text-lg font-medium text-gray-600 dark:text-gray-400">{{ $t('media.empty_title') }}</p>
                        <p class="text-sm text-gray-500">{{ $t('media.empty_description') }}</p>
                    </div>

                    <!-- Media Grid -->
                    <div v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                        <div
                            v-for="item in media.data"
                            :key="item.id"
                            class="group relative aspect-square overflow-hidden rounded-lg border bg-gray-100 dark:border-gray-700 dark:bg-gray-900"
                            :class="selectedMedia.includes(item.id) ? 'ring-2 ring-indigo-500' : ''"
                        >
                            <!-- Thumbnail -->
                            <img
                                v-if="item.mime_type?.startsWith('image/')"
                                :src="item.url"
                                :alt="item.alt_text || item.original_name"
                                class="h-full w-full object-cover"
                                loading="lazy"
                            />
                            <div v-else class="flex h-full w-full items-center justify-center text-4xl">
                                {{ getTypeIcon(item.type) }}
                            </div>

                            <!-- Dominant color dot -->
                            <div
                                v-if="item.colors?.length"
                                class="absolute bottom-2 left-2 h-4 w-4 rounded-full border border-white shadow"
                                :style="{ backgroundColor: item.colors[0]?.hex_color }"
                            ></div>

                            <!-- Hover overlay -->
                            <div class="absolute inset-0 flex flex-col justify-between bg-black/50 p-2 opacity-0 transition-opacity group-hover:opacity-100">
                                <!-- Top: checkbox -->
                                <div class="flex justify-end">
                                    <input
                                        type="checkbox"
                                        :checked="selectedMedia.includes(item.id)"
                                        @change="toggleSelect(item.id)"
                                        class="h-5 w-5 rounded border-white text-indigo-600"
                                    />
                                </div>
                                <!-- Bottom: actions -->
                                <div class="flex items-center justify-between">
                                    <button @click="viewMedia(item.id)" class="rounded bg-white/90 px-2 py-1 text-xs font-medium text-gray-800 hover:bg-white">
                                        {{ $t('common.view') }}
                                    </button>
                                    <button @click="deleteMedia(item.id)" class="rounded bg-red-600 px-2 py-1 text-xs font-medium text-white hover:bg-red-700">
                                        {{ $t('common.delete') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div v-if="media.links?.length > 3" class="mt-6 flex justify-center">
                    <nav class="flex gap-1">
                        <template v-for="link in media.links" :key="link.label">
                            <a
                                v-if="link.url"
                                :href="link.url"
                                v-html="link.label"
                                class="rounded-lg px-3 py-2 text-sm"
                                :class="link.active ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300'"
                            />
                            <span v-else v-html="link.label" class="px-3 py-2 text-sm text-gray-400" />
                        </template>
                    </nav>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
