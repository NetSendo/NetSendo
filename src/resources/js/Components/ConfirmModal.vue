<script setup>
import { computed } from "vue";
import { useI18n } from "vue-i18n";
import Modal from "@/Components/Modal.vue";

const { t } = useI18n();

const props = defineProps({
    show: {
        type: Boolean,
        required: true,
    },
    title: {
        type: String,
        required: true,
    },
    message: {
        type: String,
        required: true,
    },
    confirmText: {
        type: String,
        default: null,
    },
    cancelText: {
        type: String,
        default: null,
    },
    type: {
        type: String,
        default: "warning", // 'warning', 'danger', 'info'
        validator: (value) => ["warning", "danger", "info"].includes(value),
    },
    processing: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["close", "confirm"]);

const iconColor = computed(() => {
    switch (props.type) {
        case "danger":
            return "bg-red-100 dark:bg-red-900/50 text-red-600 dark:text-red-400";
        case "warning":
            return "bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-400";
        case "info":
            return "bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400";
        default:
            return "bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400";
    }
});

const iconPath = computed(() => {
    switch (props.type) {
        case "danger":
            return "M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z";
        case "warning":
            return "M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z";
        case "info":
            return "M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z";
        default:
            return "M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z";
    }
});

const confirmButtonClass = computed(() => {
    switch (props.type) {
        case "danger":
            return "bg-red-600 hover:bg-red-500 text-white";
        case "warning":
            return "bg-amber-600 hover:bg-amber-500 text-white";
        case "info":
            return "bg-indigo-600 hover:bg-indigo-500 text-white";
        default:
            return "bg-indigo-600 hover:bg-indigo-500 text-white";
    }
});
</script>

<template>
    <Modal :show="show" @close="$emit('close')" max-width="md">
        <div class="p-6">
            <div class="flex items-start gap-4 mb-4">
                <div
                    class="flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full"
                    :class="iconColor"
                >
                    <svg
                        class="h-6 w-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            :d="iconPath"
                        />
                    </svg>
                </div>
                <div class="flex-1">
                    <h3
                        class="text-lg font-semibold text-slate-900 dark:text-white mb-2"
                    >
                        {{ title }}
                    </h3>
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        {{ message }}
                    </p>
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-6">
                <button
                    type="button"
                    @click="$emit('close')"
                    :disabled="processing"
                    class="rounded-xl px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors disabled:opacity-50"
                >
                    {{ cancelText || t("common.cancel") }}
                </button>
                <button
                    type="button"
                    @click="$emit('confirm')"
                    :disabled="processing"
                    class="rounded-xl px-4 py-2 text-sm font-semibold transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    :class="confirmButtonClass"
                >
                    <span v-if="processing">{{ t("common.processing") }}</span>
                    <span v-else>{{ confirmText || t("common.confirm") }}</span>
                </button>
            </div>
        </div>
    </Modal>
</template>
