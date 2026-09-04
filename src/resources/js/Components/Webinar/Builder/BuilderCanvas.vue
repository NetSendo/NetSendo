<script setup>
// The editing canvas: rows with 1-3 columns, blocks dragged between them.
import draggable from 'vuedraggable';
import { BLOCK_ICONS, blockSummary, columnsForLayout } from './blockMeta';

const props = defineProps({
    rows: { type: Array, required: true },
    selection: { type: Object, required: true },
    layouts: { type: Array, required: true },
});

const emit = defineEmits(['select-block', 'select-row', 'add-row', 'change-layout', 'move-row', 'duplicate-row', 'remove-row', 'clear-selection']);

const isSelectedBlock = (block) => props.selection.type === 'block' && props.selection.blockId === block.id;
const isSelectedRow = (row) => props.selection.type === 'row' && props.selection.rowId === row.id;

const gridStyle = (layout) => {
    const map = {
        '1': 'minmax(0, 1fr)',
        '1-1': 'repeat(2, minmax(0, 1fr))',
        '1-2': 'minmax(0, 1fr) minmax(0, 2fr)',
        '2-1': 'minmax(0, 2fr) minmax(0, 1fr)',
        '1-1-1': 'repeat(3, minmax(0, 1fr))',
    };
    return { gridTemplateColumns: map[layout] || map['1'] };
};
</script>

<template>
    <div class="mx-auto max-w-4xl space-y-4 pb-24" @click.self="emit('clear-selection')">
        <div
            v-for="(row, rowIndex) in rows"
            :key="row.id"
            class="group/row rounded-xl border-2 bg-white p-3 transition dark:bg-gray-800"
            :class="isSelectedRow(row)
                ? 'border-indigo-500 shadow-md'
                : 'border-gray-200 hover:border-indigo-300 dark:border-gray-700'"
            @click.self="emit('select-row', row.id)"
        >
            <!-- Row toolbar -->
            <div class="mb-2 flex items-center gap-1.5">
                <button
                    type="button"
                    class="flex items-center gap-1 rounded-md px-1.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700"
                    @click="emit('select-row', row.id)"
                >
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    {{ $t('webinars.builder.row') }} {{ rowIndex + 1 }}
                </button>

                <select
                    :value="row.layout"
                    @change="emit('change-layout', row.id, $event.target.value)"
                    class="rounded-md border-gray-200 py-0.5 pl-2 pr-6 text-[11px] text-gray-600 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                >
                    <option v-for="layout in layouts" :key="layout" :value="layout">{{ $t(`webinars.builder.layouts.${layout}`) }}</option>
                </select>

                <div class="ml-auto flex items-center gap-0.5 opacity-0 transition group-hover/row:opacity-100">
                    <button type="button" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700 disabled:opacity-30 dark:hover:bg-gray-700" :disabled="rowIndex === 0" @click="emit('move-row', rowIndex, -1)">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                    </button>
                    <button type="button" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700 disabled:opacity-30 dark:hover:bg-gray-700" :disabled="rowIndex === rows.length - 1" @click="emit('move-row', rowIndex, 1)">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
                    </button>
                    <button type="button" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-700" @click="emit('duplicate-row', row.id)">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5h10"/></svg>
                    </button>
                    <button type="button" class="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600" @click="emit('remove-row', row.id)">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13"/></svg>
                    </button>
                </div>
            </div>

            <!-- Columns -->
            <div class="grid gap-2" :style="gridStyle(row.layout)">
                <draggable
                    v-for="(column, columnIndex) in row.columns.slice(0, columnsForLayout(row.layout))"
                    :key="columnIndex"
                    :list="column.blocks"
                    group="blocks"
                    item-key="id"
                    :animation="180"
                    ghost-class="opacity-40"
                    handle=".block-handle"
                    class="min-h-[64px] space-y-2 rounded-lg border border-dashed border-gray-200 p-2 transition dark:border-gray-700"
                >
                    <template #item="{ element: block }">
                        <div
                            class="group/block flex cursor-pointer items-center gap-2 rounded-lg border bg-gray-50 px-2.5 py-2 transition dark:bg-gray-900/50"
                            :class="isSelectedBlock(block)
                                ? 'border-indigo-500 ring-1 ring-indigo-400'
                                : 'border-gray-200 hover:border-indigo-300 dark:border-gray-700'"
                            @click.stop="emit('select-block', row.id, columnIndex, block.id)"
                        >
                            <span class="block-handle cursor-grab text-gray-300 hover:text-gray-500">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="6" r="1.5"/><circle cx="15" cy="6" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="18" r="1.5"/><circle cx="15" cy="18" r="1.5"/></svg>
                            </span>
                            <svg class="h-4 w-4 shrink-0 text-indigo-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <path :d="BLOCK_ICONS[block.type] || BLOCK_ICONS.text" />
                            </svg>
                            <div class="min-w-0 flex-1">
                                <div class="text-xs font-semibold text-gray-700 dark:text-gray-200">{{ $t(`webinars.builder.blocks.${block.type}.label`) }}</div>
                                <div v-if="blockSummary(block)" class="truncate text-[11px] text-gray-400">{{ blockSummary(block) }}</div>
                            </div>
                        </div>
                    </template>

                    <template #footer>
                        <p v-if="column.blocks.length === 0" class="py-2 text-center text-[11px] text-gray-400">
                            {{ $t('webinars.builder.drop_here') }}
                        </p>
                    </template>
                </draggable>
            </div>
        </div>

        <!-- Add row -->
        <div class="flex flex-wrap items-center justify-center gap-2 rounded-xl border-2 border-dashed border-gray-300 p-4 dark:border-gray-700">
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $t('webinars.builder.add_row') }}</span>
            <button
                v-for="layout in layouts"
                :key="layout"
                type="button"
                @click="emit('add-row', layout)"
                class="rounded-md border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-600 transition hover:border-indigo-400 hover:text-indigo-600 dark:border-gray-600 dark:text-gray-300"
            >
                {{ $t(`webinars.builder.layouts.${layout}`) }}
            </button>
        </div>
    </div>
</template>
