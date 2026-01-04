<script setup>
import { ref, computed } from "vue";
import { useI18n } from "vue-i18n";
import Modal from "@/Components/Modal.vue";

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
    processing: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["close", "submit"]);

const targetListId = ref("");

const handleSubmit = () => {
    if (!targetListId.value) return;
    emit("submit", {
        target_list_id: Number(targetListId.value),
    });
};

const resetForm = () => {
    targetListId.value = "";
};

// Reset form when modal opens
const handleClose = () => {
    resetForm();
    emit("close");
};

const modalTitle = computed(() => {
    return t("subscribers.bulk.copy_modal_title");
});

const modalDesc = computed(() => {
    return t("subscribers.bulk.copy_modal_desc", {
        count: props.selectedCount,
    });
});

const submitButtonText = computed(() => {
    return t("subscribers.bulk.copy");
});
</script>

<template>
    <Modal :show="show" @close="handleClose" max-width="md">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-4">
                <div
                    class="flex items-center justify-center h-10 w-10 rounded-full bg-indigo-100 dark:bg-indigo-900/50"
                >
                    <svg
                        class="h-5 w-5 text-indigo-600 dark:text-indigo-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                        />
                    </svg>
                </div>
                <div>
                    <h3
                        class="text-lg font-semibold text-slate-900 dark:text-white"
                    >
                        {{ t("subscribers.bulk.copy_modal_title") }}
                    </h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{
                            t("subscribers.bulk.copy_modal_desc", {
                                count: selectedCount,
                            })
                        }}
                    </p>
                </div>
            </div>

            <div class="space-y-4">
                <!-- Target list -->
                <div>
                    <label
                        class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5"
                    >
                        {{ t("subscribers.bulk.target_list") }}
                    </label>
                    <select
                        v-model="targetListId"
                        class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-2.5 text-slate-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    >
                        <option value="">
                            {{ t("subscribers.bulk.select_target") }}
                        </option>
                        <option
                            v-for="list in lists"
                            :key="list.id"
                            :value="list.id"
                        >
                            {{ list.name }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button
                    type="button"
                    @click="handleClose"
                    class="rounded-xl px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                >
                    {{ t("common.cancel") }}
                </button>
                <button
                    type="button"
                    @click="handleSubmit"
                    :disabled="!targetListId || processing"
                    class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span v-if="processing">{{ t("common.processing") }}</span>
                    <span v-else>{{ submitButtonText }}</span>
                </button>
            </div>
        </div>
    </Modal>
</template>
