<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import InsertPickerModal from '@/Components/InsertPickerModal.vue';
import ProductPickerModal from '@/Components/ProductPickerModal.vue';

const { t } = useI18n();

const props = defineProps({
    block: Object,
    aiAvailable: Boolean,
    inserts: Array,
    signatures: Array,
    systemVariables: Array,
});

const emit = defineEmits(['update', 'delete', 'duplicate', 'ai-content']);

// Local copy of block content
const localContent = ref({ ...props.block?.content });
const localSettings = ref({ ...props.block?.settings });

// Track current block ID to prevent focus loss
const currentBlockId = ref(props.block?.id);

// Local text content for textarea (extracted from HTML to prevent focus loss)
const localTextContent = ref(props.block?.content?.html?.replace(/<[^>]*>/g, '') || '');

// Watch for block change - only update text when switching to DIFFERENT block
watch(() => props.block, (newBlock) => {
    if (newBlock) {
        localContent.value = { ...newBlock.content };
        localSettings.value = { ...newBlock.settings };

        // Only update localTextContent when the selected block CHANGES (not on content updates)
        if (newBlock.id !== currentBlockId.value) {
            currentBlockId.value = newBlock.id;
            localTextContent.value = newBlock.content?.html?.replace(/<[^>]*>/g, '') || '';
        }
    }
}, { deep: true });

// AI state
const isGenerating = ref(false);
const aiPrompt = ref('');
const aiTone = ref('casual');

// Image upload state
const isUploading = ref(false);
const uploadError = ref('');

// Product picker state
const showProductPicker = ref(false);
const productDataSource = ref('manual'); // manual, woocommerce, dynamic

// Handle product selection from picker
const handleProductSelect = (products) => {
    if (props.block.type === 'product_grid') {
        const mappedProducts = products.map(product => ({
            id: product.id,
            image: product.image || '',
            title: product.name,
            description: product.description || '',
            price: formatPrice(product.price, product.currency),
            oldPrice: product.sale_price && product.regular_price > product.sale_price
                ? formatPrice(product.regular_price, product.currency)
                : '',
            buttonUrl: product.url || '',
            buttonText: t('template_builder.buy_now'),
            woocommerce_product_id: product.id,
        }));

        // If we selected fewer products than current columns, we might Want to adjust columns or just fill available spots?
        // For now let's just replace the content.
        // We should probably preserve the grid settings like columns count if possible,
        // but the current implementation of updateProductsCount just generates empty objects.
        // Let's replace the products array.
        updateContent('products', mappedProducts);

        // Auto-update count to match selected
        // updateContent('products', mappedProducts); // This is array
        // But the grid also has 'columns' setting.
    } else if (products.length > 0) {
        const product = products[0];
        // Update all product fields with selected product data
        localContent.value = {
            ...localContent.value,
            title: product.name,
            description: product.description || '',
            price: formatPrice(product.price, product.currency),
            oldPrice: product.sale_price && product.regular_price > product.sale_price
                ? formatPrice(product.regular_price, product.currency)
                : '',
            buttonUrl: product.url || '',
            image: product.image || '',
            woocommerce_product_id: product.id,
        };
        productDataSource.value = 'woocommerce';
        emit('update', { content: { ...localContent.value } });
    }
    showProductPicker.value = false;
};

// Format price helper
const formatPrice = (price, currency = 'PLN') => {
    if (!price) return '';
    return new Intl.NumberFormat('pl-PL', {
        style: 'currency',
        currency: currency,
    }).format(price);
};

// Clear WooCommerce link
const clearProductLink = () => {
    localContent.value.woocommerce_product_id = null;
    productDataSource.value = 'manual';
    emit('update', { content: { ...localContent.value } });
};

// Update block content
const updateContent = (key, value) => {
    localContent.value[key] = value;
    emit('update', { content: { ...localContent.value } });
};

// Update block settings
const updateSettings = (key, value) => {
    localSettings.value[key] = value;
    emit('update', { settings: { ...localSettings.value } });
};

// Generate AI content for text block
const generateAiContent = async () => {
    if (!aiPrompt.value || isGenerating.value) return;

    isGenerating.value = true;
    try {
        const response = await axios.post(route('api.templates.ai.content'), {
            prompt: aiPrompt.value,
            block_type: props.block.type,
            tone: aiTone.value,
        });

        if (response.data.success) {
            updateContent('html', response.data.content);
            aiPrompt.value = '';
        }
    } catch (error) {
        console.error('AI generation failed:', error);
    } finally {
        isGenerating.value = false;
    }
};

// Improve existing text
const improveText = async (action = 'improve') => {
    if (!localContent.value.html || isGenerating.value) return;

    isGenerating.value = true;
    try {
        const response = await axios.post(route('api.templates.ai.improve'), {
            text: localContent.value.html,
            tone: aiTone.value,
            action: action,
        });

        if (response.data.success) {
            updateContent('html', response.data.content);
        }
    } catch (error) {
        console.error('AI improvement failed:', error);
    } finally {
        isGenerating.value = false;
    }
};

// File upload for images
const handleImageUpload = async (event) => {
    const file = event.target.files[0];
    if (!file) return;

    uploadError.value = '';

    // Client-side validation
    const maxSize = 5 * 1024 * 1024; // 5MB
    if (file.size > maxSize) {
        uploadError.value = t('template_builder.image_too_large');
        event.target.value = ''; // Reset input
        return;
    }

    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        uploadError.value = t('template_builder.image_invalid_format');
        event.target.value = ''; // Reset input
        return;
    }

    isUploading.value = true;
    const formData = new FormData();
    formData.append('image', file);

    try {
        const response = await axios.post(route('api.templates.upload-image'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        if (response.data.success) {
            if (props.block.type === 'header') {
                updateContent('logo', response.data.url);
            } else {
                updateContent('src', response.data.url);
            }
        } else {
             // Handle unsuccessful upload (but successful request)
            uploadError.value = response.data.message || t('template_builder.image_upload_error');
             // Optionally trigger a toast here if you have a toast library
        }
    } catch (error) {
        console.error('Image upload failed:', error);
        if (error.response?.status === 422) {
            const errors = error.response.data.errors;
            uploadError.value = errors?.image?.[0] || t('template_builder.image_upload_error');
        } else {
            uploadError.value = t('template_builder.image_upload_error');
        }
    } finally {
        isUploading.value = false;
        event.target.value = ''; // Reset input for next upload
    }
};

// Alignment options
const alignmentOptions = [
    { value: 'left', icon: 'M4 6h16M4 12h10M4 18h16' },
    { value: 'center', icon: 'M4 6h16M7 12h10M4 18h16' },
    { value: 'right', icon: 'M4 6h16M10 12h10M4 18h16' },
];

// Social icons options
const socialOptions = [
    { type: 'facebook', label: 'Facebook' },
    { type: 'twitter', label: 'Twitter / X' },
    { type: 'instagram', label: 'Instagram' },
    { type: 'linkedin', label: 'LinkedIn' },
    { type: 'youtube', label: 'YouTube' },
];

// Toggle social icon
const toggleSocialIcon = (type, checked) => {
    const icons = [...(localContent.value.icons || [])];
    if (checked) {
        if (!icons.some(i => i.type === type)) {
            icons.push({ type, url: '' });
        }
    } else {
        const index = icons.findIndex(i => i.type === type);
        if (index > -1) icons.splice(index, 1);
    }
    updateContent('icons', icons);
};

// Update social icon URL
const updateSocialUrl = (type, url) => {
    const icons = [...(localContent.value.icons || [])];
    const icon = icons.find(i => i.type === type);
    if (icon) {
        icon.url = url;
        updateContent('icons', icons);
    }
};

// Update products count for product grid
const updateProductsCount = (count) => {
    const products = [];
    for (let i = 0; i < count; i++) {
        products.push({ id: i + 1 });
    }
    updateContent('products', products);
    updateContent('products', products);
};

// Insert Picker state
const showInsertPicker = ref(false);
const activeInsertField = ref(null);
const activeInputCallback = ref(null);

const openInsertPicker = (field, callback = null) => {
    activeInsertField.value = field;
    activeInputCallback.value = callback;
    showInsertPicker.value = true;
};

const handleInsert = (content) => {
    if (activeInputCallback.value) {
        // Custom callback for complex handling
        activeInputCallback.value(content);
    } else if (activeInsertField.value === 'localText') {
        // Special case for main text area
        localTextContent.value += content;
        updateContent('html', '<p>' + localTextContent.value + '</p>');
    } else if (activeInsertField.value) {
        // Standard field update
        const current = localContent.value[activeInsertField.value] || '';
        updateContent(activeInsertField.value, current + content);
    }
    showInsertPicker.value = false;
    activeInsertField.value = null;
    activeInputCallback.value = null;
};
</script>

<template>
    <div class="space-y-6">
        <InsertPickerModal
            v-if="showInsertPicker"
            :show="showInsertPicker"
            :inserts="inserts"
            :signatures="signatures"
            :system-variables="systemVariables"
            @close="showInsertPicker = false"
            @insert="handleInsert"
        />

        <!-- Block header -->
        <div class="flex items-center justify-between">
            <h4 class="text-sm font-semibold text-slate-900 dark:text-white">
                {{ $t(`template_builder.blocks.${block.type}`) }}
            </h4>
            <div class="flex gap-1">
                <button
                    @click="$emit('duplicate')"
                    class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300"
                    :title="$t('template_builder.duplicate')"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                </button>
                <button
                    @click="$emit('delete')"
                    class="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                    :title="$t('template_builder.delete')"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- HEADER BLOCK -->
        <template v-if="block.type === 'header'">
            <div class="space-y-4">
                <!-- Logo upload -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.logo') }}</label>
                    <div v-if="localContent.logo" class="relative mb-2 rounded bg-slate-100 p-2 dark:bg-slate-800">
                        <img :src="localContent.logo" class="mx-auto max-h-16" />
                        <button @click="updateContent('logo', null)" class="absolute right-1 top-1 rounded bg-red-500 p-1 text-white hover:bg-red-600">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <label :class="['flex cursor-pointer items-center justify-center gap-2 rounded-lg border-2 border-dashed p-4 text-sm transition-colors', isUploading ? 'cursor-wait opacity-50' : '', uploadError ? 'border-red-300 bg-red-50 dark:border-red-700 dark:bg-red-900/20' : 'border-slate-200 bg-slate-50 hover:border-indigo-300 hover:bg-indigo-50 dark:border-slate-700 dark:bg-slate-800 dark:hover:border-indigo-600']">
                        <svg v-if="isUploading" class="h-5 w-5 animate-spin text-indigo-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        <span :class="uploadError ? 'text-red-600 dark:text-red-400' : 'text-slate-500 dark:text-slate-400'">{{ isUploading ? $t('common.uploading') : $t('template_builder.upload_logo') }}</span>
                        <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden" @change="handleImageUpload" :disabled="isUploading" />
                    </label>
                    <p v-if="uploadError" class="mt-2 text-xs text-red-600 dark:text-red-400">{{ uploadError }}</p>
                </div>

                <!-- Logo width -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.logo_width') }}</label>
                    <input
                        type="range"
                        :value="localContent.logoWidth"
                        @input="updateContent('logoWidth', parseInt($event.target.value))"
                        min="50"
                        max="300"
                        class="w-full"
                    />
                    <div class="text-right text-xs text-slate-400">{{ localContent.logoWidth }}px</div>
                </div>

                <!-- Background color -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.background_color') }}</label>
                    <div class="flex gap-2">
                        <input
                            type="color"
                            :value="localContent.backgroundColor"
                            @input="updateContent('backgroundColor', $event.target.value)"
                            class="h-10 w-14 cursor-pointer rounded border border-slate-200 dark:border-slate-700"
                        />
                        <input
                            type="text"
                            :value="localContent.backgroundColor"
                            @input="updateContent('backgroundColor', $event.target.value)"
                            class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                    </div>
                </div>
            </div>
        </template>

        <!-- TEXT BLOCK -->
        <template v-else-if="block.type === 'text'">
            <div class="space-y-4">
                <!-- Rich text editor (simplified) -->
                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <label class="text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.content') }}</label>
                        <button
                            @click="openInsertPicker('localText')"
                            class="text-xs font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300"
                        >
                            {{ $t('template_builder.insert_variable') }}
                        </button>
                    </div>
                    <textarea
                        v-model="localTextContent"
                        @blur="updateContent('html', '<p>' + localTextContent + '</p>')"
                        rows="6"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        :placeholder="$t('template_builder.text_placeholder')"
                    ></textarea>
                </div>

                <!-- Alignment -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.alignment') }}</label>
                    <div class="flex gap-1">
                        <button
                            v-for="opt in alignmentOptions"
                            :key="opt.value"
                            @click="updateContent('alignment', opt.value)"
                            :class="localContent.alignment === opt.value ? 'bg-indigo-100 text-indigo-600 dark:bg-indigo-900/50' : 'text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'"
                            class="rounded-lg p-2"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="opt.icon" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- AI Assistant -->
                <div v-if="aiAvailable" class="rounded-lg border border-indigo-200 bg-indigo-50 p-3 dark:border-indigo-800 dark:bg-indigo-900/20">
                    <label class="mb-2 flex items-center gap-2 text-xs font-medium text-indigo-700 dark:text-indigo-300">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        {{ $t('template_builder.ai_assistant') }}
                    </label>
                    <textarea
                        v-model="aiPrompt"
                        rows="2"
                        class="mb-2 w-full rounded-lg border border-indigo-200 bg-white px-3 py-2 text-sm dark:border-indigo-700 dark:bg-slate-800 dark:text-white"
                        :placeholder="$t('template_builder.ai_prompt_placeholder')"
                    ></textarea>
                    <select v-model="aiTone" class="mb-2 w-full rounded-lg border border-indigo-200 bg-white px-3 py-2 text-sm dark:border-indigo-700 dark:bg-slate-800 dark:text-white">
                        <option value="casual">{{ $t('template_builder.tone_casual') }}</option>
                        <option value="formal">{{ $t('template_builder.tone_formal') }}</option>
                        <option value="persuasive">{{ $t('template_builder.tone_persuasive') }}</option>
                    </select>
                    <div class="flex gap-2">
                        <button
                            @click="generateAiContent"
                            :disabled="isGenerating"
                            class="flex-1 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-medium text-white hover:bg-indigo-500 disabled:opacity-50"
                        >
                            {{ isGenerating ? $t('template_builder.generating') : $t('template_builder.generate') }}
                        </button>
                        <button
                            @click="improveText('improve')"
                            :disabled="isGenerating || !localContent.html"
                            class="rounded-lg border border-indigo-200 px-3 py-2 text-xs font-medium text-indigo-600 hover:bg-indigo-100 disabled:opacity-50 dark:border-indigo-700 dark:text-indigo-300"
                        >
                            {{ $t('template_builder.improve') }}
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- IMAGE BLOCK -->
        <template v-else-if="block.type === 'image'">
            <div class="space-y-4">
                <!-- Image upload -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.image') }}</label>
                    <div v-if="localContent.src" class="relative mb-2 rounded bg-slate-100 p-2 dark:bg-slate-800">
                        <img :src="localContent.src" class="mx-auto max-h-32" />
                        <button @click="updateContent('src', null)" class="absolute right-1 top-1 rounded bg-red-500 p-1 text-white hover:bg-red-600">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <label :class="['flex cursor-pointer items-center justify-center gap-2 rounded-lg border-2 border-dashed p-4 text-sm transition-colors', isUploading ? 'cursor-wait opacity-50' : '', uploadError ? 'border-red-300 bg-red-50 dark:border-red-700 dark:bg-red-900/20' : 'border-slate-200 bg-slate-50 hover:border-indigo-300 hover:bg-indigo-50 dark:border-slate-700 dark:bg-slate-800 dark:hover:border-indigo-600']">
                        <svg v-if="isUploading" class="h-5 w-5 animate-spin text-indigo-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <svg v-else class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        <span :class="uploadError ? 'text-red-600 dark:text-red-400' : 'text-slate-500 dark:text-slate-400'">{{ isUploading ? $t('common.uploading') : $t('template_builder.upload_image') }}</span>
                        <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden" @change="handleImageUpload" :disabled="isUploading" />
                    </label>
                    <p v-if="uploadError" class="mt-2 text-xs text-red-600 dark:text-red-400">{{ uploadError }}</p>
                </div>

                <!-- Alt text -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.alt_text') }}</label>
                    <input
                        type="text"
                        :value="localContent.alt"
                        @input="updateContent('alt', $event.target.value)"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        :placeholder="$t('template_builder.alt_placeholder')"
                    />
                </div>

                <!-- Link URL -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.link_url') }}</label>
                    <input
                        type="url"
                        :value="localContent.href"
                        @input="updateContent('href', $event.target.value)"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        placeholder="https://"
                    />
                </div>

                <!-- Alignment -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.alignment') }}</label>
                    <div class="flex gap-1">
                        <button
                            v-for="opt in alignmentOptions"
                            :key="opt.value"
                            @click="updateContent('alignment', opt.value)"
                            :class="localContent.alignment === opt.value ? 'bg-indigo-100 text-indigo-600 dark:bg-indigo-900/50' : 'text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'"
                            class="rounded-lg p-2"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="opt.icon" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- BUTTON BLOCK -->
        <template v-else-if="block.type === 'button'">
            <div class="space-y-4">
                <!-- Button text -->
                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <label class="text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.button_text') }}</label>
                        <button @click="openInsertPicker('text')" class="text-indigo-600 hover:text-indigo-500">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                        </button>
                    </div>
                    <input
                        type="text"
                        :value="localContent.text"
                        @input="updateContent('text', $event.target.value)"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    />
                </div>

                <!-- Button URL -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.url') }}</label>
                    <input
                        type="url"
                        :value="localContent.href"
                        @input="updateContent('href', $event.target.value)"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        placeholder="https://"
                    />
                </div>

                <!-- Button colors -->
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.background_color') }}</label>
                        <input
                            type="color"
                            :value="localContent.backgroundColor"
                            @input="updateContent('backgroundColor', $event.target.value)"
                            class="h-10 w-full cursor-pointer rounded border border-slate-200 dark:border-slate-700"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.text_color') }}</label>
                        <input
                            type="color"
                            :value="localContent.textColor"
                            @input="updateContent('textColor', $event.target.value)"
                            class="h-10 w-full cursor-pointer rounded border border-slate-200 dark:border-slate-700"
                        />
                    </div>
                </div>

                <!-- Border radius -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.border_radius') }}</label>
                    <select
                        :value="localContent.borderRadius"
                        @change="updateContent('borderRadius', $event.target.value)"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    >
                        <option value="0">{{ $t('template_builder.none') }}</option>
                        <option value="4px">{{ $t('template_builder.small') }}</option>
                        <option value="8px">{{ $t('template_builder.medium') }}</option>
                        <option value="16px">{{ $t('template_builder.large') }}</option>
                        <option value="9999px">{{ $t('template_builder.pill') }}</option>
                    </select>
                </div>

                <!-- Alignment -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.alignment') }}</label>
                    <div class="flex gap-1">
                        <button
                            v-for="opt in alignmentOptions"
                            :key="opt.value"
                            @click="updateContent('alignment', opt.value)"
                            :class="localContent.alignment === opt.value ? 'bg-indigo-100 text-indigo-600 dark:bg-indigo-900/50' : 'text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800'"
                            class="rounded-lg p-2"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="opt.icon" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- DIVIDER BLOCK -->
        <template v-else-if="block.type === 'divider'">
            <div class="space-y-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.line_color') }}</label>
                    <div class="flex gap-2">
                        <input
                            type="color"
                            :value="localContent.color"
                            @input="updateContent('color', $event.target.value)"
                            class="h-10 w-14 cursor-pointer rounded border border-slate-200 dark:border-slate-700"
                        />
                        <input
                            type="text"
                            :value="localContent.color"
                            @input="updateContent('color', $event.target.value)"
                            class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                    </div>
                </div>
            </div>
        </template>

        <!-- SPACER BLOCK -->
        <template v-else-if="block.type === 'spacer'">
            <div class="space-y-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.height') }}</label>
                    <select
                        :value="localContent.height"
                        @change="updateContent('height', $event.target.value)"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    >
                        <option value="10px">10px</option>
                        <option value="20px">20px</option>
                        <option value="30px">30px</option>
                        <option value="40px">40px</option>
                        <option value="50px">50px</option>
                        <option value="60px">60px</option>
                    </select>
                </div>
            </div>
        </template>

        <!-- COLUMNS BLOCK -->
        <template v-else-if="block.type === 'columns'">
            <div class="space-y-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.columns_count') }}</label>
                    <div class="flex gap-2">
                        <button
                            v-for="n in [2, 3, 4]"
                            :key="n"
                            @click="updateContent('columns', n)"
                            :class="localContent.columns === n ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300'"
                            class="flex-1 rounded-lg py-2 text-sm font-medium transition-colors"
                        >
                            {{ n }}
                        </button>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.gap') }}</label>
                    <select
                        :value="localContent.gap"
                        @change="updateContent('gap', $event.target.value)"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    >
                        <option value="10px">10px</option>
                        <option value="20px">20px</option>
                        <option value="30px">30px</option>
                        <option value="40px">40px</option>
                    </select>
                </div>
                <div class="rounded-lg bg-amber-50 p-3 text-xs text-amber-700 dark:bg-amber-900/20 dark:text-amber-300">
                    <strong>{{ $t('template_builder.note') }}:</strong> {{ $t('template_builder.columns_note') }}
                </div>
            </div>
        </template>

        <!-- PRODUCT BLOCK -->
        <template v-else-if="block.type === 'product'">
            <div class="space-y-4">
                <!-- WooCommerce Product Import -->
                <div class="rounded-xl border-2 border-dashed border-purple-200 bg-purple-50/50 p-4 dark:border-purple-800 dark:bg-purple-900/20">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-800">
                                <svg viewBox="0 0 24 24" fill="#96588a" class="h-5 w-5">
                                    <path d="M23.594 9.25h-.587c-.22 0-.421.124-.52.32l-.872 1.746a.586.586 0 0 1-.52.32h-5.76a.586.586 0 0 0-.52.32l-.873 1.747a.586.586 0 0 1-.52.32H7.52a.586.586 0 0 0-.52.32l-.873 1.746a.586.586 0 0 1-.52.32H.406A.407.407 0 0 0 0 16.816v1.932c0 .847.686 1.533 1.533 1.533h20.934c.847 0 1.533-.686 1.533-1.533V9.657a.407.407 0 0 0-.406-.407z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-900 dark:text-white">
                                    {{ $t('template_builder.import_from_woocommerce') }}
                                </p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $t('template_builder.import_from_woocommerce_desc') }}
                                </p>
                            </div>
                        </div>
                        <button
                            @click="showProductPicker = true"
                            class="inline-flex items-center gap-2 rounded-lg bg-purple-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-purple-500"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            {{ $t('template_builder.select_product') }}
                        </button>
                    </div>

                    <!-- Connected product indicator -->
                    <div v-if="localContent.woocommerce_product_id" class="mt-3 flex items-center justify-between rounded-lg bg-white p-2 dark:bg-slate-800">
                        <div class="flex items-center gap-2">
                            <svg class="h-4 w-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                            </svg>
                            <span class="text-xs text-slate-600 dark:text-slate-400">
                                {{ $t('template_builder.linked_to_woocommerce') }} #{{ localContent.woocommerce_product_id }}
                            </span>
                        </div>
                        <button
                            @click="clearProductLink"
                            class="text-xs text-red-500 hover:text-red-600"
                        >
                            {{ $t('template_builder.unlink') }}
                        </button>
                    </div>
                </div>

                <!-- Product image -->
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.product_image') }}</label>
                    <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border-2 border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500 transition-colors hover:border-indigo-300 hover:bg-indigo-50 dark:border-slate-700 dark:bg-slate-800 dark:hover:border-indigo-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        {{ $t('template_builder.upload_image') }}
                        <input type="file" accept="image/*" class="hidden" @change="handleImageUpload" />
                    </label>
                </div>

                <!-- Product title -->
                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <label class="text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.product_title') }}</label>
                        <button @click="openInsertPicker('title')" class="text-indigo-600 hover:text-indigo-500">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                        </button>
                    </div>
                    <input
                        type="text"
                        :value="localContent.title"
                        @input="updateContent('title', $event.target.value)"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    />
                </div>

                <!-- Product description -->
                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <label class="text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.description') }}</label>
                         <button @click="openInsertPicker('description')" class="text-indigo-600 hover:text-indigo-500">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                        </button>
                    </div>
                    <textarea
                        :value="localContent.description"
                        @input="updateContent('description', $event.target.value)"
                        rows="2"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    ></textarea>
                </div>

                <!-- Prices -->
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.price') }}</label>
                        <input
                            type="text"
                            :value="localContent.price"
                            @input="updateContent('price', $event.target.value)"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.old_price') }}</label>
                        <input
                            type="text"
                            :value="localContent.oldPrice"
                            @input="updateContent('oldPrice', $event.target.value)"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            :placeholder="$t('template_builder.optional')"
                        />
                    </div>
                </div>

                <!-- Button -->
                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <label class="text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.button_text') }}</label>
                        <button @click="openInsertPicker('buttonText')" class="text-indigo-600 hover:text-indigo-500">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                        </button>
                    </div>
                    <input
                        type="text"
                        :value="localContent.buttonText"
                        @input="updateContent('buttonText', $event.target.value)"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.button_url') }}</label>
                    <input
                        type="url"
                        :value="localContent.buttonUrl"
                        @input="updateContent('buttonUrl', $event.target.value)"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        placeholder="https://"
                    />
                </div>
            </div>
        </template>

        <!-- SOCIAL BLOCK -->
        <template v-else-if="block.type === 'social'">
            <div class="space-y-4">
                <div>
                    <label class="mb-2 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.social_icons') }}</label>
                    <div class="space-y-2">
                        <div v-for="option in socialOptions" :key="option.type" class="flex items-center gap-3">
                            <input
                                type="checkbox"
                                :id="`social-${option.type}`"
                                :checked="(localContent.icons || []).some(i => i.type === option.type)"
                                @change="toggleSocialIcon(option.type, $event.target.checked)"
                                class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <label :for="`social-${option.type}`" class="text-sm text-slate-700 dark:text-slate-300">
                                {{ option.label }}
                            </label>
                        </div>
                    </div>
                </div>
                <div v-if="(localContent.icons || []).length > 0" class="space-y-3">
                    <div v-for="icon in (localContent.icons || [])" :key="icon.type" class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
                        <label class="mb-1 block text-xs font-medium text-slate-600 dark:text-slate-400">{{ icon.type }} URL</label>
                        <input
                            type="url"
                            :value="icon.url"
                            @input="updateSocialUrl(icon.type, $event.target.value)"
                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            placeholder="https://"
                        />
                    </div>
                </div>
            </div>
        </template>

        <!-- PRODUCT GRID BLOCK -->
        <template v-else-if="block.type === 'product_grid'">
            <div class="space-y-4">
                <!-- WooCommerce Product Import -->
                <div class="rounded-xl border-2 border-dashed border-purple-200 bg-purple-50/50 p-4 dark:border-purple-800 dark:bg-purple-900/20">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-800">
                                <svg viewBox="0 0 24 24" fill="#96588a" class="h-5 w-5">
                                    <path d="M23.594 9.25h-.587c-.22 0-.421.124-.52.32l-.872 1.746a.586.586 0 0 1-.52.32h-5.76a.586.586 0 0 0-.52.32l-.873 1.747a.586.586 0 0 1-.52.32H7.52a.586.586 0 0 0-.52.32l-.873 1.746a.586.586 0 0 1-.52.32H.406A.407.407 0 0 0 0 16.816v1.932c0 .847.686 1.533 1.533 1.533h20.934c.847 0 1.533-.686 1.533-1.533V9.657a.407.407 0 0 0-.406-.407zM1.533 5.72h20.934c.847 0 1.533.686 1.533 1.532v.47a.407.407 0 0 1-.406.406h-5.01a.586.586 0 0 0-.52.32l-.873 1.747a.586.586 0 0 1-.52.32H9.767a.586.586 0 0 0-.52.32l-.873 1.746a.586.586 0 0 1-.52.32H.406A.407.407 0 0 1 0 10.496V7.252c0-.847.686-1.533 1.533-1.533z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-slate-900 dark:text-white">
                                    {{ $t('template_builder.import_from_woocommerce') }}
                                </p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $t('template_builder.import_from_woocommerce_desc') }}
                                </p>
                            </div>
                        </div>
                        <button
                            @click="showProductPicker = true"
                            class="inline-flex items-center gap-2 rounded-lg bg-purple-600 px-3 py-1.5 text-xs font-medium text-white transition-colors hover:bg-purple-500"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            {{ $t('template_builder.select_products') || $t('template_builder.select_product') }}
                        </button>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.columns_count') }}</label>
                    <div class="flex gap-2">
                        <button
                            v-for="n in [2, 3, 4]"
                            :key="n"
                            @click="updateContent('columns', n)"
                            :class="localContent.columns === n ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300'"
                            class="flex-1 rounded-lg py-2 text-sm font-medium transition-colors"
                        >
                            {{ n }}
                        </button>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.products_count') }}</label>
                    <select
                        :value="(localContent.products || []).length"
                        @change="updateProductsCount(parseInt($event.target.value))"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    >
                        <option value="2">2</option>
                        <option value="3">3</option>
                        <option value="4">4</option>
                        <option value="6">6</option>
                        <option value="8">8</option>
                    </select>
                </div>
            </div>
        </template>

        <!-- FOOTER BLOCK -->
        <template v-else-if="block.type === 'footer'">
            <div class="space-y-4">
                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <label class="text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.company_name') }}</label>
                         <button @click="openInsertPicker('companyName')" class="text-indigo-600 hover:text-indigo-500">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                        </button>
                    </div>
                    <input
                        type="text"
                        :value="localContent.companyName"
                        @input="updateContent('companyName', $event.target.value)"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    />
                </div>

                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <label class="text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.address') }}</label>
                         <button @click="openInsertPicker('address')" class="text-indigo-600 hover:text-indigo-500">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                        </button>
                    </div>
                    <input
                        type="text"
                        :value="localContent.address"
                        @input="updateContent('address', $event.target.value)"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    />
                </div>

                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <label class="text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.unsubscribe_text') }}</label>
                         <button @click="openInsertPicker('unsubscribeText')" class="text-indigo-600 hover:text-indigo-500">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                        </button>
                    </div>
                    <input
                        type="text"
                        :value="localContent.unsubscribeText"
                        @input="updateContent('unsubscribeText', $event.target.value)"
                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.background_color') }}</label>
                        <input
                            type="color"
                            :value="localContent.backgroundColor"
                            @input="updateContent('backgroundColor', $event.target.value)"
                            class="h-10 w-full cursor-pointer rounded border border-slate-200 dark:border-slate-700"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.text_color') }}</label>
                        <input
                            type="color"
                            :value="localContent.textColor"
                            @input="updateContent('textColor', $event.target.value)"
                            class="h-10 w-full cursor-pointer rounded border border-slate-200 dark:border-slate-700"
                        />
                    </div>
                </div>
            </div>
        </template>

        <!-- Default for other blocks -->
        <template v-else>
            <div class="py-8 text-center text-slate-400">
                <p class="text-sm">{{ $t('template_builder.no_settings') }}</p>
            </div>
        </template>
    </div>

    <!-- Product Picker Modal -->
    <ProductPickerModal
        :show="showProductPicker"
        :multi-select="block.type === 'product_grid'"
        @close="showProductPicker = false"
        @select="handleProductSelect"
    />
</template>
