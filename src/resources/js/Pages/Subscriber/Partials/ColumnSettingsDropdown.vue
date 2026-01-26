<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    columns: {
        type: Object,
        required: true,
    },
    orderedColumns: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['toggle', 'reorder']);

const open = ref(false);
const dropdownRef = ref(null);

const draggingKey = ref(null);
const dragOverKey = ref(null);

const safeOrderedColumns = computed(() =>
    (props.orderedColumns || []).filter(Boolean)
);

const activeColumns = computed(() =>
    safeOrderedColumns.value.filter((column) => props.columns[column.key])
);

const availableColumns = computed(() =>
    safeOrderedColumns.value.filter((column) => !props.columns[column.key])
);

const getColumnLabel = (column) =>
    column.labelKey ? t(column.labelKey) : column.label;

const handleDragStart = (key) => {
    draggingKey.value = key;
};

const handleDragOver = (key, event) => {
    event.preventDefault();
    dragOverKey.value = key;
};

const handleDrop = (key) => {
    if (!draggingKey.value || draggingKey.value === key) {
        dragOverKey.value = null;
        draggingKey.value = null;
        return;
    }
    emit('reorder', { from: draggingKey.value, to: key });
    dragOverKey.value = null;
    draggingKey.value = null;
};

const handleDragEnd = () => {
    dragOverKey.value = null;
    draggingKey.value = null;
};

// Close dropdown when clicking outside
const handleClickOutside = (event) => {
    if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
        open.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});
</script>

<template>
    <div class="relative" ref="dropdownRef">
        <button 
            @click="open = !open"
            class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300 transition-colors"
            :title="t('subscribers.column_settings')"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
        </button>
        
        <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div 
                v-if="open"
                class="absolute right-0 mt-2 w-64 origin-top-right rounded-xl bg-white dark:bg-slate-800 shadow-lg ring-1 ring-black ring-opacity-5 dark:ring-slate-700 z-50"
            >
                <div class="p-3">
                    <p
                        class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-3"
                    >
                        {{ t('subscribers.columns.active') }}
                    </p>
                    <div class="space-y-2">
                        <div
                            v-for="column in activeColumns"
                            :key="'active-' + column.key"
                            class="flex items-center gap-2 rounded-lg px-2 py-1.5 -mx-2 hover:bg-slate-50 dark:hover:bg-slate-700/50"
                            draggable="true"
                            @dragstart="handleDragStart(column.key)"
                            @dragover="handleDragOver(column.key, $event)"
                            @drop="handleDrop(column.key)"
                            @dragend="handleDragEnd"
                            :class="{
                                'bg-slate-100 dark:bg-slate-700/70':
                                    dragOverKey === column.key,
                            }"
                        >
                            <svg
                                class="h-4 w-4 text-slate-400 cursor-grab"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M8 9h.01M8 15h.01M12 9h.01M12 15h.01M16 9h.01M16 15h.01"
                                />
                            </svg>
                            <input
                                type="checkbox"
                                :id="'col-' + column.key"
                                :checked="columns[column.key]"
                                @change="$emit('toggle', column.key)"
                                class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700"
                            />
                            <span class="text-sm text-slate-700 dark:text-slate-300">
                                {{ getColumnLabel(column) }}
                            </span>
                        </div>
                    </div>

                    <div
                        class="border-t border-slate-200 dark:border-slate-700 my-3"
                    ></div>
                    <p
                        class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 mb-3"
                    >
                        {{ t('subscribers.columns.available') }}
                    </p>

                    <div class="space-y-2 max-h-48 overflow-y-auto">
                        <label
                            v-for="column in availableColumns"
                            :key="'available-' + column.key"
                            class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/50 rounded-lg px-2 py-1.5 -mx-2"
                        >
                            <input
                                type="checkbox"
                                :id="'col-' + column.key"
                                :checked="columns[column.key]"
                                @change="$emit('toggle', column.key)"
                                class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700"
                            />
                            <span class="text-sm text-slate-700 dark:text-slate-300">
                                {{ getColumnLabel(column) }}
                            </span>
                        </label>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>
