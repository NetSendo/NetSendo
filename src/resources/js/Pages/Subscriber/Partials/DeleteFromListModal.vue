<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import Modal from '@/Components/Modal.vue';

const { t } = useI18n();

const props = defineProps({
    show: {
        type: Boolean,
        required: true,
    },
    subscriber: {
        type: Object,
        default: null,
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

const emit = defineEmits(['close', 'delete-from-lists', 'delete-completely']);

// Selected list IDs for deletion
const selectedListIds = ref([]);

// GDPR complete deletion option
const gdprDelete = ref(false);

// Get subscriber's lists
const subscriberLists = computed(() => {
    if (!props.subscriber || !props.subscriber.subscriber_lists) {
        return [];
    }
    return props.subscriber.subscriber_lists;
});

// Watch for modal opening to set defaults
watch(() => props.show, (newVal) => {
    if (newVal) {
        resetForm();
        // Pre-select current filtered list if applicable
        if (props.currentListId && subscriberLists.value.some(l => l.id === Number(props.currentListId))) {
            selectedListIds.value = [Number(props.currentListId)];
        }
    }
});

const resetForm = () => {
    selectedListIds.value = [];
    gdprDelete.value = false;
};

const handleClose = () => {
    resetForm();
    emit('close');
};

const handleDeleteFromLists = () => {
    if (selectedListIds.value.length === 0) return;
    emit('delete-from-lists', {
        subscriber_id: props.subscriber.id,
        list_ids: selectedListIds.value,
    });
};

const handleDeleteCompletely = () => {
    if (!gdprDelete.value) return;
    emit('delete-completely', {
        subscriber_id: props.subscriber.id,
    });
};

const toggleList = (listId) => {
    const index = selectedListIds.value.indexOf(listId);
    if (index === -1) {
        selectedListIds.value.push(listId);
    } else {
        selectedListIds.value.splice(index, 1);
    }
};
</script>

<template>
    <Modal :show="show" @close="handleClose" max-width="lg">
        <div class="p-6">
            <!-- Header -->
            <div class="flex items-center gap-3 mb-6">
                <div class="flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900/50">
                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                        {{ t('subscribers.delete_modal.title') }}
                    </h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ t('subscribers.delete_modal.description', { email: subscriber?.email }) }}
                    </p>
                </div>
            </div>

            <!-- Lists Section -->
            <div class="mb-6" v-if="subscriberLists.length > 0">
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-3">
                    {{ t('subscribers.delete_modal.select_lists') }}
                </label>
                <div class="space-y-2 max-h-48 overflow-y-auto rounded-xl border border-slate-200 dark:border-slate-700 p-3">
                    <label
                        v-for="list in subscriberLists"
                        :key="list.id"
                        class="flex items-center gap-3 p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer transition-colors"
                    >
                        <input
                            type="checkbox"
                            :checked="selectedListIds.includes(list.id)"
                            @change="toggleList(list.id)"
                            class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700"
                        />
                        <span class="text-sm text-slate-700 dark:text-slate-300">{{ list.name }}</span>
                        <span
                            v-if="list.type"
                            class="ml-auto text-xs px-2 py-0.5 rounded-full"
                            :class="{
                                'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400': list.type === 'email',
                                'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400': list.type === 'sms',
                            }"
                        >
                            {{ list.type.toUpperCase() }}
                        </span>
                    </label>
                </div>
            </div>

            <div v-else class="mb-6 text-sm text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800 rounded-xl p-4">
                {{ t('subscribers.delete_modal.no_lists') }}
            </div>

            <!-- GDPR Section -->
            <div class="mb-6 border-t border-slate-200 dark:border-slate-700 pt-6">
                <div class="flex items-start gap-3">
                    <div class="flex items-center justify-center h-10 w-10 rounded-full bg-amber-100 dark:bg-amber-900/50 flex-shrink-0">
                        <svg class="h-5 w-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-sm font-semibold text-slate-900 dark:text-white mb-1">
                            {{ t('subscribers.delete_modal.gdpr_section') }}
                        </h4>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mb-3">
                            {{ t('subscribers.delete_modal.gdpr_description') }}
                        </p>
                        <label class="flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition-colors"
                            :class="{
                                'border-red-500 bg-red-50 dark:bg-red-900/20': gdprDelete,
                                'border-slate-200 dark:border-slate-700 hover:border-slate-300 dark:hover:border-slate-600': !gdprDelete
                            }"
                        >
                            <input
                                type="checkbox"
                                v-model="gdprDelete"
                                class="h-4 w-4 rounded border-slate-300 text-red-600 focus:ring-red-500 dark:border-slate-600 dark:bg-slate-700"
                            />
                            <span class="text-sm font-medium" :class="{ 'text-red-700 dark:text-red-400': gdprDelete, 'text-slate-700 dark:text-slate-300': !gdprDelete }">
                                {{ t('subscribers.delete_modal.gdpr_warning') }}
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                <button
                    type="button"
                    @click="handleClose"
                    class="rounded-xl px-4 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                >
                    {{ t('common.cancel') }}
                </button>

                <button
                    v-if="!gdprDelete && subscriberLists.length > 0"
                    type="button"
                    @click="handleDeleteFromLists"
                    :disabled="selectedListIds.length === 0 || processing"
                    class="rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span v-if="processing">{{ t('common.processing') }}</span>
                    <span v-else>{{ t('subscribers.delete_modal.remove_from_lists') }} ({{ selectedListIds.length }})</span>
                </button>

                <button
                    v-if="gdprDelete"
                    type="button"
                    @click="handleDeleteCompletely"
                    :disabled="processing"
                    class="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span v-if="processing">{{ t('common.processing') }}</span>
                    <span v-else>{{ t('subscribers.delete_modal.delete_completely') }}</span>
                </button>
            </div>
        </div>
    </Modal>
</template>
