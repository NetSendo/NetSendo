<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    currentColor: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['close', 'select']);

const brandColors = ref([]);
const mediaColors = ref([]);
const customColor = ref('#000000');
const loading = ref(false);
const activeTab = ref('brand'); // 'brand', 'media', 'custom'

// Load colors when modal opens
watch(() => props.show, async (show) => {
    if (show) {
        await loadColors();
        if (props.currentColor) {
            customColor.value = props.currentColor;
        }
    }
});

onMounted(() => {
    if (props.currentColor) {
        customColor.value = props.currentColor;
    }
});

const loadColors = async () => {
    loading.value = true;
    try {
        const response = await axios.get(route('api.media.colors'));
        brandColors.value = response.data.brand_colors || [];
        mediaColors.value = response.data.media_colors || [];
    } catch (error) {
        console.error('Failed to load colors:', error);
    } finally {
        loading.value = false;
    }
};

const selectColor = (color) => {
    emit('select', color);
    emit('close');
};

const selectCustomColor = () => {
    emit('select', customColor.value);
    emit('close');
};

// Check if a color is light (for text contrast)
const isLightColor = (hex) => {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    return luminance > 0.5;
};

// Default color palette for fallback
const defaultPalette = [
    '#000000', '#434343', '#666666', '#999999', '#CCCCCC', '#FFFFFF',
    '#FF0000', '#FF6600', '#FFCC00', '#00FF00', '#00CCFF', '#0066FF',
    '#9900FF', '#FF00FF', '#FF6699', '#996633', '#003366', '#339966',
];
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl dark:bg-gray-800">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $t('colors.picker_title') }}
                    </h3>
                    <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Tabs -->
                <div class="flex border-b border-gray-200 dark:border-gray-700">
                    <button
                        @click="activeTab = 'brand'"
                        class="flex-1 px-4 py-3 text-sm font-medium transition-colors"
                        :class="activeTab === 'brand' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                    >
                        🎨 {{ $t('colors.brand_colors') }}
                    </button>
                    <button
                        @click="activeTab = 'media'"
                        class="flex-1 px-4 py-3 text-sm font-medium transition-colors"
                        :class="activeTab === 'media' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                    >
                        🖼️ {{ $t('colors.media_colors') }}
                    </button>
                    <button
                        @click="activeTab = 'custom'"
                        class="flex-1 px-4 py-3 text-sm font-medium transition-colors"
                        :class="activeTab === 'custom' ? 'border-b-2 border-indigo-600 text-indigo-600' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                    >
                        ✏️ {{ $t('colors.custom') }}
                    </button>
                </div>

                <!-- Content -->
                <div class="p-6">
                    <!-- Loading -->
                    <div v-if="loading" class="flex items-center justify-center py-8">
                        <svg class="h-8 w-8 animate-spin text-indigo-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>

                    <!-- Brand Colors Tab -->
                    <div v-else-if="activeTab === 'brand'">
                        <div v-if="brandColors.length === 0" class="py-8 text-center text-gray-500 dark:text-gray-400">
                            <p>{{ $t('colors.no_brand_colors') }}</p>
                            <a :href="route('brands.index')" class="mt-2 inline-block text-indigo-600 hover:text-indigo-700">
                                {{ $t('colors.create_brand') }}
                            </a>
                        </div>
                        <div v-else class="grid grid-cols-6 gap-3">
                            <button
                                v-for="color in brandColors"
                                :key="color"
                                @click="selectColor(color)"
                                class="group relative aspect-square rounded-lg border-2 border-gray-200 shadow-sm transition-all hover:scale-110 hover:shadow-md dark:border-gray-600"
                                :style="{ backgroundColor: color }"
                                :title="color"
                            >
                                <span
                                    class="absolute inset-0 flex items-center justify-center text-xs font-medium opacity-0 transition-opacity group-hover:opacity-100"
                                    :class="isLightColor(color) ? 'text-gray-800' : 'text-white'"
                                >
                                    {{ color }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Media Colors Tab -->
                    <div v-else-if="activeTab === 'media'">
                        <div v-if="mediaColors.length === 0" class="py-8 text-center text-gray-500 dark:text-gray-400">
                            <p>{{ $t('colors.no_media_colors') }}</p>
                            <p class="mt-1 text-sm">{{ $t('colors.upload_images_hint') }}</p>
                        </div>
                        <div v-else class="grid grid-cols-6 gap-3">
                            <button
                                v-for="color in mediaColors"
                                :key="color"
                                @click="selectColor(color)"
                                class="group relative aspect-square rounded-lg border-2 border-gray-200 shadow-sm transition-all hover:scale-110 hover:shadow-md dark:border-gray-600"
                                :style="{ backgroundColor: color }"
                                :title="color"
                            >
                                <span
                                    class="absolute inset-0 flex items-center justify-center text-xs font-medium opacity-0 transition-opacity group-hover:opacity-100"
                                    :class="isLightColor(color) ? 'text-gray-800' : 'text-white'"
                                >
                                    {{ color }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Custom Color Tab -->
                    <div v-else-if="activeTab === 'custom'">
                        <div class="space-y-4">
                            <!-- Color Input -->
                            <div class="flex items-center gap-4">
                                <input
                                    type="color"
                                    v-model="customColor"
                                    class="h-16 w-16 cursor-pointer rounded-lg border-2 border-gray-300 dark:border-gray-600"
                                />
                                <div class="flex-1">
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ $t('colors.hex_value') }}
                                    </label>
                                    <input
                                        type="text"
                                        v-model="customColor"
                                        maxlength="7"
                                        pattern="^#[0-9A-Fa-f]{6}$"
                                        class="w-full rounded-lg border-gray-300 font-mono text-sm uppercase dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                    />
                                </div>
                            </div>

                            <!-- Quick Default Palette -->
                            <div>
                                <p class="mb-2 text-sm font-medium text-gray-600 dark:text-gray-400">{{ $t('colors.quick_picks') }}</p>
                                <div class="grid grid-cols-9 gap-2">
                                    <button
                                        v-for="color in defaultPalette"
                                        :key="color"
                                        @click="customColor = color"
                                        class="aspect-square rounded border border-gray-200 transition-transform hover:scale-110 dark:border-gray-600"
                                        :style="{ backgroundColor: color }"
                                    />
                                </div>
                            </div>

                            <!-- Apply Button -->
                            <button
                                @click="selectCustomColor"
                                class="w-full rounded-lg bg-indigo-600 py-3 text-sm font-medium text-white hover:bg-indigo-700"
                            >
                                {{ $t('colors.apply_color') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
