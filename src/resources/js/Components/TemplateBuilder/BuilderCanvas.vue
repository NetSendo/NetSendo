<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import draggable from 'vuedraggable';

const { t } = useI18n();

const props = defineProps({
    blocks: {
        type: Array,
        default: () => [],
    },
    selectedBlockId: String,
    settings: Object,
});

const emit = defineEmits(['select', 'reorder', 'delete', 'duplicate', 'move', 'add-block', 'update-column-blocks', 'remove-nested-block']);

const localBlocks = computed({
    get: () => props.blocks,
    set: (value) => emit('reorder', value),
});

const isDragging = ref(false);

// Get block icon based on type
const getBlockIcon = (type) => {
    const icons = {
        header: 'M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5z',
        text: 'M4 6h16M4 12h16m-7 6h7',
        image: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
        button: 'M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2 1m2-1l-2-1m2 1v2.5M14 4l-2-1-2 1M4 7l2-1M4 7l2 1M4 7v2.5M12 21l-2-1m2 1l2-1m-2 1v-2.5M6 18l-2-1v-2.5M18 18l2-1v-2.5',
        divider: 'M5 12h14',
        spacer: 'M8 7h8M8 17h8',
        columns: 'M9 4.5v15m6-15v15M4 4h16v16H4z',
        product: 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z',
        product_grid: 'M4 5a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM14 5a1 1 0 011-1h4a1 1 0 011 1v6a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h6a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4z',
        social: 'M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z',
        footer: 'M4 16.5v3h16v-3M4 5v2h16V5',
    };
    return icons[type] || 'M4 6h16M4 12h16m-7 6h7';
};

// Get block label
const getBlockLabel = (type) => {
    return t(`template_builder.blocks.${type}`, type);
};

// Handle block click
const selectBlock = (blockId) => {
    emit('select', blockId);
};

// Quick actions
const handleDelete = (e, blockId) => {
    e.stopPropagation();
    emit('delete', blockId);
};

const handleDuplicate = (e, blockId) => {
    e.stopPropagation();
    emit('duplicate', blockId);
};

const handleMoveUp = (e, blockId) => {
    e.stopPropagation();
    emit('move', blockId, 'up');
};

const handleMoveDown = (e, blockId) => {
    e.stopPropagation();
    emit('move', blockId, 'down');
};

// Handle block added from library (via drag)
const onBlockAdded = (event) => {
    // Select the newly added block
    const addedBlock = localBlocks.value[event.newIndex];
    if (addedBlock) {
        emit('select', addedBlock.id);
    }
};

// Get column blocks for a columns block - ensures proper array structure
const getColumnBlocks = (block) => {
    const columnCount = block.content?.columns || 2;
    const columnBlocks = block.content?.columnBlocks || [];

    // Ensure we have an array for each column
    const result = [];
    for (let i = 0; i < columnCount; i++) {
        result.push(columnBlocks[i] || []);
    }
    return result;
};

// Handle changes to blocks within a column
const onColumnBlocksChange = (parentBlockId, columnIndex, newBlocks, evt) => {
    emit('update-column-blocks', parentBlockId, columnIndex, newBlocks);
};

// Remove a nested block from a column
const removeNestedBlock = (parentBlockId, columnIndex, nestedBlockId) => {
    emit('remove-nested-block', parentBlockId, columnIndex, nestedBlockId);
};

// Strip HTML tags from text
const stripHtml = (html) => {
    if (!html) return '';
    return html.replace(/<[^>]*>/g, '').substring(0, 50);
};
</script>

<template>
    <div class="flex h-full flex-col overflow-hidden bg-slate-50 dark:bg-slate-950">
        <!-- Canvas toolbar -->
        <div class="flex shrink-0 items-center justify-between border-b border-slate-200 bg-white px-4 py-2 dark:border-slate-800 dark:bg-slate-900">
            <span class="text-sm text-slate-500 dark:text-slate-400">
                {{ blocks.length }} {{ $t('template_builder.blocks_count') }}
            </span>
        </div>

        <!-- Canvas area -->
        <div class="flex-1 overflow-y-auto p-6">
            <div
                class="builder-canvas-content mx-auto rounded-lg border-2 border-dashed bg-white shadow-sm transition-colors dark:bg-slate-900"
                :class="isDragging ? 'border-indigo-400' : 'border-slate-200 dark:border-slate-700'"
                :style="{ maxWidth: (settings?.width || 600) + 'px', backgroundColor: settings?.content_background || '#ffffff' }"
            >
                <!-- Empty state -->
                <div
                    v-if="blocks.length === 0"
                    class="flex flex-col items-center justify-center py-24 text-slate-400"
                >
                    <svg class="h-16 w-16 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <p class="mt-4 text-sm">{{ $t('template_builder.empty_canvas') }}</p>
                    <p class="mt-1 text-xs">{{ $t('template_builder.drag_blocks_here') }}</p>
                </div>

                <!-- Blocks list - always show draggable for drop target -->
                <draggable
                    v-model="localBlocks"
                    item-key="id"
                    group="blocks"
                    ghost-class="opacity-50"
                    :animation="200"
                    @start="isDragging = true"
                    @end="isDragging = false"
                    @add="onBlockAdded"
                    class="min-h-[200px]"
                    :class="{ 'border-2 border-dashed border-indigo-300 rounded-lg': blocks.length === 0 }"
                >
                    <template #item="{ element: block, index }">
                        <div
                            @click="selectBlock(block.id)"
                            class="group relative cursor-pointer border-2 border-transparent transition-all hover:border-indigo-200 dark:hover:border-indigo-800"
                            :class="selectedBlockId === block.id ? 'border-indigo-500 dark:border-indigo-400' : ''"
                        >
                            <!-- Block toolbar (visible on hover or selection) -->
                            <div
                                class="absolute -top-8 left-0 z-10 flex items-center gap-1 rounded-t-lg bg-indigo-500 px-2 py-1 text-xs text-white opacity-0 transition-opacity group-hover:opacity-100"
                                :class="selectedBlockId === block.id ? 'opacity-100' : ''"
                            >
                                <!-- Drag handle -->
                                <button class="drag-handle cursor-move p-1 hover:bg-indigo-600" :title="$t('template_builder.drag_to_move')">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                                    </svg>
                                </button>

                                <span class="px-1 font-medium">{{ getBlockLabel(block.type) }}</span>

                                <div class="ml-2 flex items-center gap-0.5">
                                    <button
                                        @click="handleMoveUp($event, block.id)"
                                        :disabled="index === 0"
                                        class="p-1 hover:bg-indigo-600 disabled:opacity-30"
                                        :title="$t('template_builder.move_up')"
                                    >
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                                        </svg>
                                    </button>
                                    <button
                                        @click="handleMoveDown($event, block.id)"
                                        :disabled="index === blocks.length - 1"
                                        class="p-1 hover:bg-indigo-600 disabled:opacity-30"
                                        :title="$t('template_builder.move_down')"
                                    >
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </button>
                                    <button
                                        @click="handleDuplicate($event, block.id)"
                                        class="p-1 hover:bg-indigo-600"
                                        :title="$t('template_builder.duplicate')"
                                    >
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </button>
                                    <button
                                        @click="handleDelete($event, block.id)"
                                        class="p-1 hover:bg-red-600"
                                        :title="$t('template_builder.delete')"
                                    >
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Block preview -->
                            <div class="relative min-h-[40px] p-2">
                                <!-- Header Block -->
                                <div v-if="block.type === 'header'"
                                    class="flex items-center justify-center p-6"
                                    :style="{ backgroundColor: block.content?.backgroundColor || '#6366f1' }">
                                    <img v-if="block.content?.logo" :src="block.content.logo" :style="{ width: (block.content?.logoWidth || 150) + 'px' }" alt="Logo" />
                                    <span v-else class="text-white opacity-50">{{ $t('template_builder.logo_placeholder') }}</span>
                                </div>

                                <!-- Text Block -->
                                <div v-else-if="block.type === 'text'"
                                    class="prose prose-sm max-w-none p-4 dark:prose-invert"
                                    v-html="block.content?.html || $t('template_builder.text_placeholder')">
                                </div>

                                <!-- Image Block -->
                                <div v-else-if="block.type === 'image'" class="p-2">
                                    <img v-if="block.content?.src" :src="block.content.src" :alt="block.content?.alt" class="w-full" />
                                    <div v-else class="flex h-32 w-full items-center justify-center bg-slate-100 text-slate-400 dark:bg-slate-800">
                                        <svg class="h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                </div>

                                <!-- Button Block -->
                                <div v-else-if="block.type === 'button'" class="flex justify-center p-4" :style="{ textAlign: block.content?.alignment || 'center' }">
                                    <a
                                        href="#"
                                        class="inline-block px-6 py-3 font-medium text-white no-underline"
                                        :style="{
                                            backgroundColor: block.content?.backgroundColor || '#6366f1',
                                            color: block.content?.textColor || '#ffffff',
                                            borderRadius: block.content?.borderRadius || '8px',
                                        }"
                                    >
                                        {{ block.content?.text || $t('template_builder.default_button') }}
                                    </a>
                                </div>

                                <!-- Divider Block -->
                                <div v-else-if="block.type === 'divider'" class="px-4 py-2">
                                    <hr :style="{ borderColor: block.content?.color || '#e2e8f0' }" />
                                </div>

                                <!-- Spacer Block -->
                                <div v-else-if="block.type === 'spacer'" :style="{ height: block.content?.height || '30px' }" class="bg-slate-50/50 dark:bg-slate-800/50">
                                    <div class="flex h-full items-center justify-center text-xs text-slate-400">
                                        {{ block.content?.height || '30px' }}
                                    </div>
                                </div>

                                <!-- Columns Block -->
                                <div v-else-if="block.type === 'columns'" class="p-4">
                                    <div class="flex" :style="{ gap: block.content?.gap || '20px' }">
                                        <div
                                            v-for="(colBlocks, colIndex) in getColumnBlocks(block)"
                                            :key="colIndex"
                                            class="flex-1"
                                        >
                                            <draggable
                                                :list="colBlocks"
                                                group="blocks"
                                                :animation="200"
                                                ghost-class="opacity-50"
                                                @change="(evt) => onColumnBlocksChange(block.id, colIndex, colBlocks, evt)"
                                                class="min-h-[60px] rounded-lg border-2 border-dashed border-slate-300 bg-slate-50/50 p-2 transition-colors dark:border-slate-600 dark:bg-slate-800/50"
                                                :class="{ 'border-indigo-400 bg-indigo-50 dark:bg-indigo-900/20': isDragging }"
                                            >
                                                <template #item="{ element: nestedBlock }">
                                                    <div
                                                        class="mb-2 rounded border border-slate-200 bg-white p-2 text-xs shadow-sm dark:border-slate-700 dark:bg-slate-800"
                                                        @click.stop="$emit('select', nestedBlock.id)"
                                                        :class="selectedBlockId === nestedBlock.id ? 'ring-2 ring-indigo-500' : ''"
                                                    >
                                                        <div class="flex items-center justify-between">
                                                            <span class="font-medium text-slate-600 dark:text-slate-300">
                                                                {{ getBlockLabel(nestedBlock.type) }}
                                                            </span>
                                                            <button
                                                                @click.stop="removeNestedBlock(block.id, colIndex, nestedBlock.id)"
                                                                class="text-slate-400 hover:text-red-500"
                                                            >
                                                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                                </svg>
                                                            </button>
                                                        </div>
                                                        <!-- Mini preview for nested blocks -->
                                                        <div v-if="nestedBlock.type === 'text'" class="mt-1 truncate text-slate-400">
                                                            {{ stripHtml(nestedBlock.content?.html || '') }}
                                                        </div>
                                                        <div v-else-if="nestedBlock.type === 'button'" class="mt-1">
                                                            <span class="inline-block rounded px-2 py-0.5 text-white" :style="{ backgroundColor: nestedBlock.content?.backgroundColor || '#6366f1' }">
                                                                {{ nestedBlock.content?.text || $t('template_builder.default_button') }}
                                                            </span>
                                                        </div>
                                                        <div v-else-if="nestedBlock.type === 'image' && nestedBlock.content?.src" class="mt-1">
                                                            <img :src="nestedBlock.content.src" class="w-full rounded" />
                                                        </div>
                                                    </div>
                                                </template>
                                                <template #footer>
                                                    <div v-if="colBlocks.length === 0" class="py-4 text-center text-xs text-slate-400">
                                                        {{ $t('template_builder.drop_here') }}
                                                    </div>
                                                </template>
                                            </draggable>
                                        </div>
                                    </div>
                                </div>

                                <!-- Product Block -->
                                <div v-else-if="block.type === 'product'" class="p-4">
                                    <div class="flex gap-4">
                                        <div class="h-24 w-24 shrink-0 rounded bg-slate-100 dark:bg-slate-800">
                                            <img v-if="block.content?.image" :src="block.content.image" class="h-full w-full object-cover" />
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="font-bold text-slate-900 dark:text-white">{{ block.content?.title || $t('template_builder.product_title') }}</h4>
                                            <p class="mt-1 text-sm text-slate-500 line-clamp-2">{{ block.content?.description }}</p>
                                            <p class="mt-2 font-bold text-indigo-600">
                                                <span v-if="block.content?.oldPrice" class="mr-2 text-sm text-slate-400 line-through">{{ block.content.oldPrice }} {{ block.content?.currency || $t('template_builder.default_currency') }}</span>
                                                {{ block.content?.price || $t('template_builder.default_price') }} {{ block.content?.currency || $t('template_builder.default_currency') }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Product Grid Block -->
                                <div v-else-if="block.type === 'product_grid'" class="grid grid-cols-2 gap-4 p-4">
                                    <div v-for="i in (block.content?.products?.length || 4)" :key="i" class="rounded border border-slate-200 p-3 text-center dark:border-slate-700">
                                        <div class="mx-auto mb-2 h-16 w-16 rounded bg-slate-100 dark:bg-slate-800"></div>
                                        <div class="text-xs text-slate-500">{{ $t('template_builder.product') }} {{ i }}</div>
                                    </div>
                                </div>

                                <!-- Social Block -->
                                <div v-else-if="block.type === 'social'" class="flex items-center justify-center gap-4 p-4">
                                    <div v-for="icon in (block.content?.icons || [])" :key="icon.type" class="h-8 w-8 rounded-full bg-slate-200 dark:bg-slate-700"></div>
                                    <span v-if="!block.content?.icons?.length" class="text-xs text-slate-400">{{ $t('template_builder.social_placeholder') }}</span>
                                </div>

                                <!-- Footer Block -->
                                <div v-else-if="block.type === 'footer'"
                                    class="p-6 text-center text-sm"
                                    :style="{ backgroundColor: block.content?.backgroundColor || '#1e293b', color: block.content?.textColor || '#94a3b8' }">
                                    <p class="font-medium">{{ block.content?.companyName || $t('template_builder.company_name') }}</p>
                                    <p class="mt-1 text-xs opacity-75">{{ block.content?.address }}</p>
                                    <p class="mt-3 text-xs underline">{{ block.content?.unsubscribeText || $t('template_builder.unsubscribe') }}</p>
                                    <p class="mt-2 text-xs opacity-50">{{ block.content?.copyright || (new Date().getFullYear() + ' ' + $t('template_builder.all_rights')) }}</p>
                                </div>

                                <!-- Fallback -->
                                <div v-else class="flex items-center gap-2 p-4 text-slate-400">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="getBlockIcon(block.type)" />
                                    </svg>
                                    <span>{{ getBlockLabel(block.type) }}</span>
                                </div>
                            </div>
                        </div>
                    </template>
                </draggable>

                <!-- Add block button at bottom -->
                <div v-if="blocks.length > 0" class="border-t border-dashed border-slate-200 p-4 text-center dark:border-slate-700">
                    <button
                        @click="$emit('add-block', 'text')"
                        class="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-indigo-500"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ $t('template_builder.add_block') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
