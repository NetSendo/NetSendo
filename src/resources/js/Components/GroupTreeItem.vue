<script setup>
import { ref } from 'vue';

const props = defineProps({
    group: Object,
    depth: {
        type: Number,
        default: 0,
    },
});

const emit = defineEmits(['edit', 'delete']);

const expanded = ref(true);

const hasChildren = props.group.children && props.group.children.length > 0;

const toggleExpand = () => {
    expanded.value = !expanded.value;
};
</script>

<template>
    <div>
        <!-- Group Row -->
        <div
            class="flex items-center justify-between px-6 py-4 hover:bg-slate-700/50 transition-colors"
            :style="{ paddingLeft: `${24 + depth * 24}px` }"
        >
            <div class="flex items-center gap-2">
                <!-- Expand/Collapse Button -->
                <button
                    v-if="hasChildren"
                    @click="toggleExpand"
                    class="flex-shrink-0 w-5 h-5 flex items-center justify-center text-slate-400 hover:text-white transition-colors"
                >
                    <svg
                        class="w-4 h-4 transition-transform duration-200"
                        :class="{ 'rotate-90': expanded }"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
                <div v-else class="w-5"></div>

                <!-- Folder Icon -->
                <svg
                    class="w-5 h-5 flex-shrink-0"
                    :class="hasChildren ? 'text-indigo-400' : 'text-slate-500'"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                </svg>

                <!-- Name -->
                <span class="text-sm font-medium text-white">{{ group.name }}</span>

                <!-- Child count badge -->
                <span
                    v-if="hasChildren"
                    class="ml-1 text-xs text-slate-500"
                >
                    ({{ group.children.length }})
                </span>
            </div>

            <div class="flex items-center gap-6">
                <!-- Lists Count -->
                <span class="text-sm text-slate-400 w-20 text-center">
                    {{ group.contact_lists_count }}
                </span>

                <!-- Actions -->
                <div class="w-24 flex justify-end gap-1">
                    <button
                        @click="emit('edit', group)"
                        class="text-indigo-400 hover:text-indigo-300 p-1.5 rounded hover:bg-slate-700 transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </button>
                    <button
                        @click="emit('delete', group)"
                        class="text-red-400 hover:text-red-300 p-1.5 rounded hover:bg-slate-700 transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Children (recursive) -->
        <template v-if="hasChildren && expanded">
            <GroupTreeItem
                v-for="child in group.children"
                :key="child.id"
                :group="child"
                :depth="depth + 1"
                @edit="emit('edit', $event)"
                @delete="emit('delete', $event)"
            />
        </template>
    </div>
</template>
