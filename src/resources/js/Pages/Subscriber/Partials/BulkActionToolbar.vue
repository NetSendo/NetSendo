<script setup>
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps({
    selectedCount: {
        type: Number,
        required: true,
    },
    processing: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['delete', 'move', 'change-status', 'clear-selection']);
</script>

<template>
    <div class="bg-indigo-50 dark:bg-indigo-900/30 border border-indigo-200 dark:border-indigo-800 rounded-xl p-4 mb-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="text-indigo-700 dark:text-indigo-300 font-medium">
                {{ t('subscribers.bulk.selected', { count: selectedCount }) }}
            </span>
            <button 
                @click="$emit('clear-selection')"
                class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline"
            >
                {{ t('common.clear_selection') }}
            </button>
        </div>
        <div class="flex items-center gap-2">
            <!-- Move to list button -->
            <button 
                @click="$emit('move')" 
                :disabled="processing"
                class="inline-flex items-center gap-1.5 rounded-lg bg-white dark:bg-slate-800 px-3 py-1.5 text-sm font-medium text-slate-700 dark:text-slate-300 shadow-sm border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors disabled:opacity-50"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
                {{ t('subscribers.bulk.move') }}
            </button>
            
            <!-- Change status dropdown -->
            <select 
                @change="$emit('change-status', $event.target.value); $event.target.value = ''"
                :disabled="processing"
                class="rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-sm text-slate-700 dark:text-slate-300 focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-50"
            >
                <option value="">{{ t('subscribers.bulk.change_status') }}</option>
                <option value="active">{{ t('subscribers.statuses.active') }}</option>
                <option value="inactive">{{ t('subscribers.statuses.inactive') }}</option>
            </select>
            
            <!-- Delete button -->
            <button 
                @click="$emit('delete')" 
                :disabled="processing"
                class="inline-flex items-center gap-1.5 rounded-lg bg-red-50 dark:bg-red-900/30 px-3 py-1.5 text-sm font-medium text-red-700 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/50 transition-colors disabled:opacity-50"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
                {{ t('subscribers.bulk.delete') }}
            </button>
        </div>
    </div>
</template>
