<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Modal from '@/Components/Modal.vue';

const { t } = useI18n();

const props = defineProps({
    show: {
        type: Boolean,
        required: true,
    },
    selectedCount: {
        type: Number,
        required: true,
    },
    lists: {
        type: Array,
        required: true,
    },
    currentListId: {
        type: [Number, String, null],
        default: null,
    },
    processing: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'move']);

const sourceListId = ref(props.currentListId || '');
const targetListId = ref('');

// Filter out selected source list from available targets
const availableTargetLists = computed(() => {
    if (!sourceListId.value) return props.lists;
    return props.lists.filter(l => l.id !== Number(sourceListId.value));
});

const handleMove = () => {
    if (!sourceListId.value || !targetListId.value) return;
    emit('move', {
        source_list_id: Number(sourceListId.value),
        target_list_id: Number(targetListId.value),
    });
};

const resetForm = () => {
    sourceListId.value = props.currentListId || '';
    targetListId.value = '';
};

// Reset form when modal opens
const handleClose = () => {
    resetForm();
    emit('close');
};
</script>

<template>
    <Modal :show="show" @close="handleClose" max-width="md">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex items-center justify-center h-10 w-10 rounded-full bg-indigo-100 dark:bg-indigo-900/50">
                    <svg class="h-5 w-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                        {{ t('subscribers.bulk.move_modal_title') }}
                    </h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ t('subscribers.bulk.move_modal_desc', { count: selectedCount }) }}
                    </p>
                </div>
            </div>
            
            <div class="space-y-4">
                <!-- Source list -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ t('subscribers.bulk.source_list') }}
                    </label>
                    <select 
                        v-model="sourceListId" 
                        class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    >
                        <option value="">{{ t('subscribers.bulk.select_source') }}</option>
                        <option v-for="list in lists" :key="list.id" :value="list.id">
                            {{ list.name }}
                        </option>
                    </select>
                </div>
                
                <!-- Target list -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                        {{ t('subscribers.bulk.target_list') }}
                    </label>
                    <select 
                        v-model="targetListId" 
                        :disabled="!sourceListId"
                        class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <option value="">{{ t('subscribers.bulk.select_target') }}</option>
                        <option v-for="list in availableTargetLists" :key="list.id" :value="list.id">
                            {{ list.name }}
                        </option>
                    </select>
                    <p v-if="sourceListId && availableTargetLists.length === 0" class="mt-1.5 text-xs text-amber-600 dark:text-amber-400">
                        {{ t('subscribers.bulk.no_other_lists') }}
                    </p>
                </div>
            </div>
            
            <div class="flex justify-end gap-3 mt-6">
                <button 
                    type="button" 
                    @click="handleClose"
                    class="rounded-xl px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                >
                    {{ t('common.cancel') }}
                </button>
                <button 
                    type="button" 
                    @click="handleMove"
                    :disabled="!targetListId || !sourceListId || processing"
                    class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span v-if="processing">{{ t('common.processing') }}</span>
                    <span v-else>{{ t('subscribers.bulk.move') }}</span>
                </button>
            </div>
        </div>
    </Modal>
</template>
