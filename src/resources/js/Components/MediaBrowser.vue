<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    brands: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close', 'select']);

const media = ref([]);
const loading = ref(false);
const search = ref('');
const selectedBrand = ref('');
const selectedMedia = ref(null);

// Load media when modal opens
watch(() => props.show, async (show) => {
    if (show) {
        await loadMedia();
    } else {
        media.value = [];
        selectedMedia.value = null;
    }
});

const loadMedia = async () => {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        if (search.value) params.append('search', search.value);
        if (selectedBrand.value) params.append('brand_id', selectedBrand.value);

        const response = await axios.get(route('api.media.browse') + '?' + params.toString());
        media.value = response.data.media || [];
    } catch (error) {
        console.error('Failed to load media:', error);
    } finally {
        loading.value = false;
    }
};

const selectMedia = (item) => {
    selectedMedia.value = item;
};

const confirmSelection = () => {
    if (selectedMedia.value) {
        emit('select', selectedMedia.value);
        emit('close');
    }
};

const handleSearch = () => {
    loadMedia();
};

// Upload
const isUploading = ref(false);
const uploadProgress = ref(0);

const handleFileUpload = async (e) => {
    const files = e.target.files;
    if (!files.length) return;

    isUploading.value = true;
    uploadProgress.value = 0;

    const formData = new FormData();
    for (let i = 0; i < files.length; i++) {
        formData.append('files[]', files[i]);
    }
    if (selectedBrand.value) formData.append('brand_id', selectedBrand.value);

    try {
        const response = await axios.post(route('media.bulk-store'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
            onUploadProgress: (e) => {
                uploadProgress.value = Math.round((e.loaded / e.total) * 100);
            }
        });
        await loadMedia();
        // Auto-select last uploaded
        if (response.data.media?.length) {
            selectedMedia.value = {
                id: response.data.media[response.data.media.length - 1].id,
                url: response.data.media[response.data.media.length - 1].url,
                name: response.data.media[response.data.media.length - 1].original_name,
            };
        }
    } catch (error) {
        console.error('Upload failed:', error);
    } finally {
        isUploading.value = false;
        uploadProgress.value = 0;
        e.target.value = '';
    }
};
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/50 p-4">
            <div class="w-full max-w-4xl rounded-2xl bg-white shadow-2xl dark:bg-gray-800">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $t('media.browser_title') }}
                    </h3>
                    <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Toolbar -->
                <div class="flex items-center gap-4 border-b border-gray-200 px-6 py-3 dark:border-gray-700">
                    <div class="flex-1">
                        <input
                            v-model="search"
                            type="text"
                            :placeholder="$t('media.search_placeholder')"
                            class="w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            @keyup.enter="handleSearch"
                        />
                    </div>
                    <select
                        v-if="brands.length"
                        v-model="selectedBrand"
                        @change="loadMedia"
                        class="rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    >
                        <option value="">{{ $t('media.all_brands') }}</option>
                        <option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
                    </select>
                    <label class="cursor-pointer rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        {{ $t('media.upload') }}
                        <input type="file" multiple accept="image/*" class="hidden" @change="handleFileUpload" />
                    </label>
                </div>

                <!-- Upload Progress -->
                <div v-if="isUploading" class="border-b border-gray-200 px-6 py-2 dark:border-gray-700">
                    <div class="flex items-center gap-2 text-sm text-blue-600 dark:text-blue-400">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ $t('media.uploading') }} {{ uploadProgress }}%
                    </div>
                </div>

                <!-- Media Grid -->
                <div class="max-h-[400px] overflow-y-auto p-6">
                    <div v-if="loading" class="flex items-center justify-center py-12">
                        <svg class="h-8 w-8 animate-spin text-indigo-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>

                    <div v-else-if="media.length === 0" class="py-12 text-center text-gray-500 dark:text-gray-400">
                        {{ $t('media.no_media_found') }}
                    </div>

                    <div v-else class="grid grid-cols-4 gap-4 sm:grid-cols-5 md:grid-cols-6">
                        <div
                            v-for="item in media"
                            :key="item.id"
                            @click="selectMedia(item)"
                            class="aspect-square cursor-pointer overflow-hidden rounded-lg border-2 bg-gray-100 transition-all hover:border-indigo-400 dark:bg-gray-900"
                            :class="selectedMedia?.id === item.id ? 'border-indigo-600 ring-2 ring-indigo-300' : 'border-transparent'"
                        >
                            <img :src="item.url" :alt="item.name" class="h-full w-full object-cover" loading="lazy" />
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between border-t border-gray-200 px-6 py-4 dark:border-gray-700">
                    <div v-if="selectedMedia" class="flex items-center gap-3">
                        <img :src="selectedMedia.url" :alt="selectedMedia.name" class="h-10 w-10 rounded object-cover" />
                        <span class="text-sm text-gray-600 dark:text-gray-400">{{ selectedMedia.name }}</span>
                    </div>
                    <span v-else class="text-sm text-gray-500">{{ $t('media.select_image') }}</span>

                    <div class="flex gap-3">
                        <button
                            @click="$emit('close')"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                        >
                            {{ $t('common.cancel') }}
                        </button>
                        <button
                            @click="confirmSelection"
                            :disabled="!selectedMedia"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{ $t('media.insert_image') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
