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

const confirmButtonClass = computed(() => {
    switch (props.type) {
        case "danger":
            return "bg-rose-600 hover:bg-rose-500 text-white focus-visible:outline-rose-500";
        case "warning":
            return "bg-amber-600 hover:bg-amber-500 text-white focus-visible:outline-amber-500";
        case "info":
            return "bg-indigo-600 hover:bg-indigo-500 text-white focus-visible:outline-indigo-500";
        default:
            return "bg-indigo-600 hover:bg-indigo-500 text-white focus-visible:outline-indigo-500";
    }
});
</script>

<template>
    <Modal :show="show" max-width="md" @close="$emit('close')">
        <div class="flex flex-col gap-4 p-6">
            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                {{ title }}
            </h2>
            <p class="text-sm text-slate-600 dark:text-slate-400">
                {{ message }}
            </p>
            <!-- What exactly is about to change, e.g. the name of the thing being deleted -->
            <p
                v-if="$slots.default"
                class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-900 dark:bg-slate-700/60 dark:text-white"
            >
                <slot />
            </p>

            <div class="flex flex-wrap justify-end gap-3">
                <button
                    type="button"
                    :disabled="processing"
                    class="inline-flex min-h-10 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-600 dark:bg-transparent dark:text-slate-200 dark:hover:bg-slate-700/60"
                    @click="$emit('close')"
                >
                    {{ cancelText || t("common.cancel") }}
                </button>
                <button
                    type="button"
                    :disabled="processing"
                    class="inline-flex min-h-10 items-center justify-center rounded-xl px-4 text-sm font-semibold transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                    :class="confirmButtonClass"
                    @click="$emit('confirm')"
                >
                    <span v-if="processing">{{ t("common.processing") }}</span>
                    <span v-else>{{ confirmText || t("common.confirm") }}</span>
                </button>
            </div>
        </div>
    </Modal>
</template>
