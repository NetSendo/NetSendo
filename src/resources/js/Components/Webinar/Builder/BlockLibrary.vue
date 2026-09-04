<script setup>
// Block palette: click or drag a block into the canvas.
import draggable from 'vuedraggable';
import { computed } from 'vue';
import { BLOCK_ICONS, GROUP_ORDER, newId, clone } from './blockMeta';

const props = defineProps({
    catalog: { type: Object, required: true },
    usedTypes: { type: Array, default: () => [] },
});

const emit = defineEmits(['add']);

const groups = computed(() => {
    const grouped = {};

    Object.entries(props.catalog).forEach(([type, definition]) => {
        const group = definition.group || 'basic';
        grouped[group] = grouped[group] || [];
        grouped[group].push({ type, ...definition });
    });

    return GROUP_ORDER.filter((group) => grouped[group]?.length).map((group) => ({
        key: group,
        blocks: grouped[group],
    }));
});

const isDisabled = (block) => Boolean(block.unique) && props.usedTypes.includes(block.type);

// vuedraggable needs a list to clone from; each drag produces a fresh block.
const cloneBlock = (item) => ({
    id: newId(),
    type: item.type,
    props: clone(item.props),
});
</script>

<template>
    <div class="space-y-5">
        <div v-for="group in groups" :key="group.key">
            <h4 class="px-1 mb-2 text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                {{ $t(`webinars.builder.groups.${group.key}`) }}
            </h4>

            <draggable
                :list="group.blocks"
                :group="{ name: 'blocks', pull: 'clone', put: false }"
                :sort="false"
                :clone="cloneBlock"
                item-key="type"
                class="grid grid-cols-2 gap-2"
            >
                <template #item="{ element: block }">
                    <button
                        type="button"
                        :disabled="isDisabled(block)"
                        @click="!isDisabled(block) && emit('add', block.type)"
                        class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-2.5 py-2 text-left text-xs font-medium text-gray-700 transition hover:border-indigo-400 hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-indigo-500 dark:hover:bg-indigo-900/30"
                        :title="$t(`webinars.builder.blocks.${block.type}.help`)"
                    >
                        <svg class="h-4 w-4 shrink-0 text-indigo-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <path :d="BLOCK_ICONS[block.type] || BLOCK_ICONS.text" />
                        </svg>
                        <span class="truncate">{{ $t(`webinars.builder.blocks.${block.type}.label`) }}</span>
                    </button>
                </template>
            </draggable>
        </div>
    </div>
</template>
