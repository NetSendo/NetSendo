<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    show: Boolean,
    list: Object,
    availableLists: Array, // Lists to transfer to (excluding current one)
});

const emit = defineEmits(['close']);

const form = useForm({
    action: 'delete_all', // 'transfer' or 'delete_all'
    transfer_to_id: '',
    force_delete: false,
});

const closeModal = () => {
    emit('close');
    form.reset();
};

const deleteList = () => {
    if (form.action === 'transfer' && !form.transfer_to_id) {
        return; // Validation handled by browser mostly, but good check
    }

    if (form.action === 'delete_all') {
        form.force_delete = true;
        form.transfer_to_id = null;
    } else {
        form.force_delete = false;
    }

    form.delete(route('sms-lists.destroy', props.list.id), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <Modal :show="show" @close="closeModal">
        <div class="p-6">
            <h2 class="text-lg font-medium text-slate-900 dark:text-white">
                {{ $t('sms_lists.delete_modal.title', { name: list?.name }) }}
            </h2>

            <div v-if="list?.subscribers_count > 0" class="mt-4">
                <p class="text-sm text-slate-500 dark:text-slate-400" v-html="$t('sms_lists.delete_modal.has_subscribers', { count: list.subscribers_count })"></p>

                <div class="mt-4 space-y-4">
                    <label class="flex items-center space-x-3">
                        <input
                            type="radio"
                            v-model="form.action"
                            value="transfer"
                            class="h-4 w-4 border-slate-300 text-indigo-600 focus:ring-indigo-600 dark:border-slate-700 dark:bg-slate-900"
                        />
                        <span class="block text-sm font-medium text-slate-700 dark:text-slate-300">
                            {{ $t('sms_lists.delete_modal.transfer_label') }}
                        </span>
                    </label>

                    <div v-if="form.action === 'transfer'" class="pl-7">
                        <select
                            v-model="form.transfer_to_id"
                            class="mt-1 block w-full rounded-md border-slate-300 py-2 pl-3 pr-10 text-base focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white sm:text-sm"
                        >
                            <option value="" disabled>{{ $t('sms_lists.delete_modal.transfer_placeholder') }}</option>
                            <option 
                                v-for="target in availableLists" 
                                :key="target.id" 
                                :value="target.id"
                            >
                                {{ target.name }}
                            </option>
                        </select>
                        <p v-if="availableLists.length === 0" class="mt-1 text-xs text-red-500">
                            {{ $t('sms_lists.delete_modal.transfer_empty') }}
                        </p>
                    </div>

                    <label class="flex items-center space-x-3">
                        <input
                            type="radio"
                            v-model="form.action"
                            value="delete_all"
                            class="h-4 w-4 border-slate-300 text-indigo-600 focus:ring-indigo-600 dark:border-slate-700 dark:bg-slate-900"
                        />
                        <span class="block text-sm font-medium text-slate-700 dark:text-slate-300 text-red-600 dark:text-red-400">
                            {{ $t('sms_lists.delete_modal.delete_all_label') }}
                        </span>
                    </label>
                </div>
            </div>

            <p v-else class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ $t('sms_lists.delete_modal.confirm_empty') }}
            </p>

            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton @click="closeModal"> {{ $t('common.cancel') }} </SecondaryButton>

                <DangerButton
                    @click="deleteList"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing || (form.action === 'transfer' && !form.transfer_to_id)"
                >
                    {{ $t('sms_lists.delete_modal.submit') }}
                </DangerButton>
            </div>
        </div>
    </Modal>
</template>
