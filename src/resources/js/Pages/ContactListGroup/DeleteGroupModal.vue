<script setup>
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

const props = defineProps({
    show: Boolean,
    group: Object,
});

const emit = defineEmits(['close']);

const form = useForm({});

const closeModal = () => {
    emit('close');
};

const deleteGroup = () => {
    form.delete(route('groups.destroy', props.group.id), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
};
</script>

<template>
    <Modal :show="show" @close="closeModal">
        <div class="p-6">
            <h2 class="text-lg font-medium text-slate-900 dark:text-white">
                {{ $t('groups.delete_modal.title', { name: group?.name }) }}
            </h2>

            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                {{ $t('groups.delete_modal.message') }}
            </p>

            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton @click="closeModal"> {{ $t('common.cancel') }} </SecondaryButton>

                <DangerButton
                    @click="deleteGroup"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    {{ $t('common.delete') }}
                </DangerButton>
            </div>
        </div>
    </Modal>
</template>
