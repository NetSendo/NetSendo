<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import html2canvas from 'html2canvas';
import BuilderCanvas from '@/Components/TemplateBuilder/BuilderCanvas.vue';
import BlockLibrary from '@/Components/TemplateBuilder/BlockLibrary.vue';
import BlockEditor from '@/Components/TemplateBuilder/BlockEditor.vue';
import PreviewPanel from '@/Components/TemplateBuilder/PreviewPanel.vue';
import StyleEditor from '@/Components/TemplateBuilder/StyleEditor.vue';
import AiAssistant from '@/Components/TemplateBuilder/AiAssistant.vue';

const { t } = useI18n();

const props = defineProps({
    template: Object,
    starterTemplate: Object,
    categories: Array,
    blockTypes: Object,
    blockCategories: Object,
    savedBlocks: Array,
    aiAvailable: Boolean,
    defaultSettings: Object,
});

// Template state
const templateName = ref(props.template?.name || props.starterTemplate?.name || t('template_builder.untitled'));
const templatePreheader = ref(props.template?.preheader || '');
const templateCategory = ref(props.template?.category || '');
const templateCategoryId = ref(props.template?.category_id || null);

// Builder state
const blocks = ref(props.template?.json_structure?.blocks || props.starterTemplate?.json_structure?.blocks || []);
const settings = ref(props.template?.settings || props.starterTemplate?.settings || { ...props.defaultSettings });
const selectedBlockId = ref(null);
const selectedBlock = computed(() => {
    // First, try to find in top-level blocks
    let block = blocks.value.find(b => b.id === selectedBlockId.value);
    if (block) return block;
    
    // If not found, search in nested column blocks
    for (const parentBlock of blocks.value) {
        if (parentBlock.type === 'columns' && parentBlock.content?.columnBlocks) {
            for (const columnBlocks of parentBlock.content.columnBlocks) {
                const nestedBlock = columnBlocks?.find(b => b.id === selectedBlockId.value);
                if (nestedBlock) return nestedBlock;
            }
        }
    }
    return null;
});

// UI state
const isSaving = ref(false);
const lastSaved = ref(null);
const showPreview = ref(false);
const showAiPanel = ref(false);
const showStylePanel = ref(false);
const previewMode = ref('desktop'); // desktop, tablet, mobile
const previewTheme = ref('light'); // light, dark

// Auto-save timer
let autoSaveTimer = null;

// Generate unique block ID
const generateBlockId = () => `block_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;

// Add new block
const addBlock = (type, position = null) => {
    const newBlock = {
        id: generateBlockId(),
        type,
        content: getDefaultContent(type),
        settings: getDefaultSettings(type),
    };

    if (position !== null) {
        blocks.value.splice(position, 0, newBlock);
    } else {
        blocks.value.push(newBlock);
    }

    selectedBlockId.value = newBlock.id;
    scheduleAutoSave();
};

// Get default content for block type
const getDefaultContent = (type) => {
    const defaults = {
        header: {
            logo: null,
            logoWidth: 150,
            backgroundColor: settings.value.primary_color || '#6366f1',
            padding: '20px',
            alignment: 'center',
        },
        text: {
            html: '<p>' + t('template_builder.default_text') + '</p>',
            padding: '20px',
            alignment: 'left',
        },
        image: {
            src: null,
            alt: '',
            href: null,
            width: '100%',
            alignment: 'center',
            padding: '10px',
        },
        button: {
            text: t('template_builder.default_button'),
            href: '#',
            backgroundColor: settings.value.primary_color || '#6366f1',
            textColor: '#ffffff',
            borderRadius: '8px',
            padding: '12px 24px',
            alignment: 'center',
        },
        divider: {
            color: '#e2e8f0',
            width: '100%',
            padding: '10px 0',
        },
        spacer: {
            height: '30px',
        },
        columns: {
            columns: 2,
            gap: '20px',
            columnBlocks: [[], []],
        },
        product: {
            image: null,
            title: t('template_builder.product_title'),
            description: t('template_builder.product_description'),
            price: '99.00',
            oldPrice: null,
            currency: 'PLN',
            buttonText: t('template_builder.buy_now'),
            buttonUrl: '#',
        },
        product_grid: {
            columns: 2,
            products: [],
        },
        social: {
            icons: [
                { type: 'facebook', url: '#' },
                { type: 'twitter', url: '#' },
                { type: 'instagram', url: '#' },
            ],
            iconSize: 32,
            alignment: 'center',
            padding: '20px',
        },
        footer: {
            companyName: t('template_builder.company_name'),
            address: t('template_builder.company_address'),
            unsubscribeText: t('template_builder.unsubscribe'),
            unsubscribeUrl: '{{unsubscribe_url}}',
            copyright: `© ${new Date().getFullYear()} ${t('template_builder.all_rights')}`,
            backgroundColor: '#1e293b',
            textColor: '#94a3b8',
            padding: '30px 20px',
        },
    };

    return defaults[type] || {};
};

// Get default settings for block type
const getDefaultSettings = (type) => {
    return {
        backgroundColor: 'transparent',
        padding: '0',
        margin: '0',
        borderRadius: '0',
    };
};

// Update block (supports both top-level and nested blocks)
const updateBlock = (blockId, updates) => {
    // First, try to find in top-level blocks
    const index = blocks.value.findIndex(b => b.id === blockId);
    if (index !== -1) {
        blocks.value[index] = { ...blocks.value[index], ...updates };
        scheduleAutoSave();
        return;
    }
    
    // If not found, search in nested column blocks
    for (const parentBlock of blocks.value) {
        if (parentBlock.type === 'columns' && parentBlock.content?.columnBlocks) {
            for (let colIndex = 0; colIndex < parentBlock.content.columnBlocks.length; colIndex++) {
                const columnBlocks = parentBlock.content.columnBlocks[colIndex];
                if (!columnBlocks) continue;
                
                const nestedIndex = columnBlocks.findIndex(b => b.id === blockId);
                if (nestedIndex !== -1) {
                    columnBlocks[nestedIndex] = { ...columnBlocks[nestedIndex], ...updates };
                    scheduleAutoSave();
                    return;
                }
            }
        }
    }
};

// Delete block
const deleteBlock = (blockId) => {
    const index = blocks.value.findIndex(b => b.id === blockId);
    if (index !== -1) {
        blocks.value.splice(index, 1);
        if (selectedBlockId.value === blockId) {
            selectedBlockId.value = null;
        }
        scheduleAutoSave();
    }
};

// Duplicate block
const duplicateBlock = (blockId) => {
    const block = blocks.value.find(b => b.id === blockId);
    if (block) {
        const index = blocks.value.findIndex(b => b.id === blockId);
        const clone = JSON.parse(JSON.stringify(block));
        clone.id = generateBlockId();
        blocks.value.splice(index + 1, 0, clone);
        selectedBlockId.value = clone.id;
        scheduleAutoSave();
    }
};

// Move block
const moveBlock = (blockId, direction) => {
    const index = blocks.value.findIndex(b => b.id === blockId);
    if (index === -1) return;

    const newIndex = direction === 'up' ? index - 1 : index + 1;
    if (newIndex < 0 || newIndex >= blocks.value.length) return;

    const [removed] = blocks.value.splice(index, 1);
    blocks.value.splice(newIndex, 0, removed);
    scheduleAutoSave();
};

// Reorder blocks (from drag & drop)
const reorderBlocks = (newBlocks) => {
    blocks.value = newBlocks;
    scheduleAutoSave();
};

// Update column blocks (nested blocks inside a column)
const updateColumnBlocks = (parentBlockId, columnIndex, newBlocks) => {
    const parentBlock = blocks.value.find(b => b.id === parentBlockId);
    if (parentBlock && parentBlock.type === 'columns') {
        // Ensure columnBlocks array exists
        if (!parentBlock.content.columnBlocks) {
            parentBlock.content.columnBlocks = [];
        }
        // Ensure column index array exists
        while (parentBlock.content.columnBlocks.length <= columnIndex) {
            parentBlock.content.columnBlocks.push([]);
        }
        // Update the column's blocks
        parentBlock.content.columnBlocks[columnIndex] = newBlocks;
        scheduleAutoSave();
    }
};

// Remove a nested block from a column
const removeNestedBlock = (parentBlockId, columnIndex, nestedBlockId) => {
    const parentBlock = blocks.value.find(b => b.id === parentBlockId);
    if (parentBlock && parentBlock.content?.columnBlocks?.[columnIndex]) {
        const index = parentBlock.content.columnBlocks[columnIndex].findIndex(b => b.id === nestedBlockId);
        if (index > -1) {
            parentBlock.content.columnBlocks[columnIndex].splice(index, 1);
            // Clear selection if this was the selected block
            if (selectedBlockId.value === nestedBlockId) {
                selectedBlockId.value = null;
            }
            scheduleAutoSave();
        }
    }
};

// Schedule auto-save
const scheduleAutoSave = () => {
    if (autoSaveTimer) {
        clearTimeout(autoSaveTimer);
    }
    autoSaveTimer = setTimeout(() => {
        if (props.template?.id) {
            saveTemplate(true);
        }
    }, 3000);
};

// Save template
const saveTemplate = async (isAutoSave = false) => {
    if (isSaving.value) return;

    isSaving.value = true;

    const data = {
        name: templateName.value,
        preheader: templatePreheader.value,
        category: templateCategory.value,
        category_id: templateCategoryId.value,
        json_structure: { blocks: blocks.value },
        settings: settings.value,
    };

    try {
        let templateId = props.template?.id;
        
        if (templateId) {
            // Update existing
            await axios.put(route('templates.update', templateId), data);
        } else {
            // Create new
            const response = await axios.post(route('templates.store'), data);
            if (response.data.redirect) {
                // For new templates, generate thumbnail before redirecting
                if (response.data.template_id) {
                    await generateThumbnail(response.data.template_id);
                }
                router.visit(response.data.redirect);
                return;
            }
            templateId = response.data.template_id;
        }
        
        lastSaved.value = new Date();
        
        // Generate thumbnail only for manual saves (not auto-save)
        if (!isAutoSave && templateId) {
            await generateThumbnail(templateId);
        }
    } catch (error) {
        console.error('Save failed:', error);
        if (!isAutoSave) {
            alert(t('template_builder.save_error'));
        }
    } finally {
        isSaving.value = false;
    }
};

// Generate thumbnail from canvas
const canvasRef = ref(null);
const generateThumbnail = async (templateId) => {
    try {
        // Find the canvas element
        const canvasElement = document.querySelector('.builder-canvas-content');
        if (!canvasElement) {
            console.warn('Canvas element not found for thumbnail generation');
            return;
        }
        
        // Generate canvas using html2canvas
        const canvas = await html2canvas(canvasElement, {
            scale: 0.5, // Reduce size for thumbnail
            useCORS: true,
            allowTaint: true,
            backgroundColor: '#ffffff',
            logging: false,
            width: 600,
            height: 450,
            windowWidth: 600,
        });
        
        // Convert to blob
        const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.8));
        if (!blob) return;
        
        // Upload to server
        const formData = new FormData();
        formData.append('thumbnail', blob, 'thumbnail.jpg');
        formData.append('template_id', templateId);
        
        await axios.post(route('api.templates.upload-thumbnail'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        
        console.log('Thumbnail generated successfully');
    } catch (error) {
        console.error('Thumbnail generation failed:', error);
        // Don't fail the save if thumbnail fails
    }
};

// Save and exit
const saveAndExit = async () => {
    await saveTemplate();
    router.visit(route('templates.index'));
};

// Handle AI generated content
const handleAiContent = (content, targetBlockId = null) => {
    if (targetBlockId) {
        const block = blocks.value.find(b => b.id === targetBlockId);
        if (block && block.type === 'text') {
            block.content.html = content;
            scheduleAutoSave();
        }
    }
};

// Cleanup
onBeforeUnmount(() => {
    if (autoSaveTimer) {
        clearTimeout(autoSaveTimer);
    }
});

// Keyboard shortcuts
const handleKeyboard = (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key === 's') {
        e.preventDefault();
        saveTemplate();
    }
    if (e.key === 'Delete' && selectedBlockId.value) {
        deleteBlock(selectedBlockId.value);
    }
    if (e.key === 'Escape') {
        selectedBlockId.value = null;
        showPreview.value = false;
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeyboard);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeyboard);
});
</script>

<template>
    <Head :title="templateName" />

    <div class="flex h-screen flex-col bg-slate-100 dark:bg-slate-950">
        <!-- Header Bar -->
        <header class="flex h-14 shrink-0 items-center justify-between border-b border-slate-200 bg-white px-4 dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center gap-4">
                <!-- Back button -->
                <button
                    @click="router.visit(route('templates.index'))"
                    class="flex items-center gap-2 text-sm text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    {{ $t('common.back') }}
                </button>

                <!-- Template name -->
                <input
                    v-model="templateName"
                    type="text"
                    class="w-64 border-0 bg-transparent text-lg font-medium text-slate-900 focus:outline-none focus:ring-0 dark:text-white"
                    :placeholder="$t('template_builder.untitled')"
                />
            </div>

            <div class="flex items-center gap-3">
                <!-- Last saved indicator -->
                <span v-if="lastSaved" class="text-xs text-slate-400">
                    {{ $t('template_builder.saved_at') }} {{ lastSaved.toLocaleTimeString() }}
                </span>

                <!-- Preview toggle -->
                <button
                    @click="showPreview = !showPreview"
                    :class="showPreview ? 'bg-indigo-100 text-indigo-600 dark:bg-indigo-900/50 dark:text-indigo-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'"
                    class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    {{ $t('template_builder.preview') }}
                </button>

                <!-- Save button -->
                <button
                    @click="saveTemplate()"
                    :disabled="isSaving"
                    class="flex items-center gap-2 rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-200 disabled:opacity-50 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                >
                    <svg v-if="isSaving" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    {{ isSaving ? $t('common.saving') : $t('common.save') }}
                </button>

                <!-- Save & Exit button -->
                <button
                    @click="saveAndExit()"
                    :disabled="isSaving"
                    class="flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-500 disabled:opacity-50"
                >
                    {{ $t('template_builder.save_exit') }}
                </button>
            </div>
        </header>

        <!-- Main Builder Area -->
        <div class="flex flex-1 overflow-hidden">
            <!-- Left Panel: Block Library -->
            <BlockLibrary
                :block-types="blockTypes"
                :block-categories="blockCategories"
                :saved-blocks="savedBlocks"
                :ai-available="aiAvailable"
                @add-block="addBlock"
                @show-ai="showAiPanel = true"
                class="w-64 shrink-0"
            />

            <!-- Center: Canvas or Preview -->
            <div class="flex-1 overflow-hidden">
                <PreviewPanel
                    v-if="showPreview"
                    :blocks="blocks"
                    :settings="settings"
                    :mode="previewMode"
                    :theme="previewTheme"
                    @update:mode="previewMode = $event"
                    @update:theme="previewTheme = $event"
                />
                <BuilderCanvas
                    v-else
                    :blocks="blocks"
                    :selected-block-id="selectedBlockId"
                    :settings="settings"
                    @select="selectedBlockId = $event"
                    @reorder="reorderBlocks"
                    @delete="deleteBlock"
                    @duplicate="duplicateBlock"
                    @move="(id, dir) => moveBlock(id, dir)"
                    @add-block="addBlock"
                    @update-column-blocks="updateColumnBlocks"
                    @remove-nested-block="removeNestedBlock"
                />
            </div>

            <!-- Right Panel: Block Editor / Style Editor -->
            <div class="w-80 shrink-0 overflow-y-auto border-l border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <!-- Tabs -->
                <div class="flex border-b border-slate-200 dark:border-slate-800">
                    <button
                        @click="showStylePanel = false"
                        :class="!showStylePanel ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400'"
                        class="flex-1 border-b-2 px-4 py-3 text-sm font-medium transition-colors"
                    >
                        {{ $t('template_builder.block_settings') }}
                    </button>
                    <button
                        @click="showStylePanel = true"
                        :class="showStylePanel ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400'"
                        class="flex-1 border-b-2 px-4 py-3 text-sm font-medium transition-colors"
                    >
                        {{ $t('template_builder.global_styles') }}
                    </button>
                </div>

                <!-- Panel Content -->
                <div class="p-4">
                    <StyleEditor
                        v-if="showStylePanel"
                        v-model:settings="settings"
                        v-model:preheader="templatePreheader"
                        :categories="categories"
                        v-model:category="templateCategory"
                        v-model:categoryId="templateCategoryId"
                    />
                    <BlockEditor
                        v-else-if="selectedBlock"
                        :block="selectedBlock"
                        :ai-available="aiAvailable"
                        @update="(updates) => updateBlock(selectedBlockId, updates)"
                        @delete="deleteBlock(selectedBlockId)"
                        @duplicate="duplicateBlock(selectedBlockId)"
                        @ai-content="handleAiContent"
                    />
                    <div v-else class="py-12 text-center text-slate-400">
                        <svg class="mx-auto h-12 w-12 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
                        </svg>
                        <p class="mt-3 text-sm">{{ $t('template_builder.select_block') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- AI Assistant Panel (Slide-over) -->
        <AiAssistant
            v-if="showAiPanel"
            :selected-block="selectedBlock"
            @close="showAiPanel = false"
            @insert-content="handleAiContent"
            @add-block="addBlock"
        />
    </div>
</template>
