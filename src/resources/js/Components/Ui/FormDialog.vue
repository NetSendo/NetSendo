<script setup>
import Modal from "@/Components/Modal.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";

/**
 * A form in a dialog: the title on top, the fields in a body that scrolls, Cancel and Save
 * always in view at the bottom.
 *
 * Two things are shown next to the buttons because the dialog covers the page's corner and
 * the field an answer belongs to may be scrolled away: `toast` (what just happened: a test,
 * something copied) and `errors` (every reason the server refused the save).
 */
defineProps({
    show: { type: Boolean, required: true },
    title: { type: String, required: true },
    maxWidth: { type: String, default: "xl" },
    processing: { type: Boolean, default: false },
    submitLabel: { type: String, default: "" },
    // Validation messages of the last save, each once
    errors: { type: Array, default: () => [] },
    errorsTitle: { type: String, default: "" },
    // { message, success } from useToast(), or null
    toast: { type: Object, default: null },
    // A quiet line beside the buttons when nothing else is to be said
    note: { type: String, default: "" },
});

defineEmits(["close", "submit"]);
</script>

<template>
    <Modal :show="show" :max-width="maxWidth" @close="$emit('close')">
        <form class="flex max-h-[calc(100vh-3rem)] flex-col" @submit.prevent="$emit('submit')">
            <header
                class="flex items-center justify-between gap-4 border-b border-slate-200 px-6 py-4 dark:border-slate-700/60"
            >
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ title }}</h2>
                <button
                    type="button"
                    :aria-label="$t('common.close')"
                    class="-mr-2 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-700/60 dark:hover:text-white"
                    @click="$emit('close')"
                >
                    <svg
                        aria-hidden="true"
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                    </svg>
                </button>
            </header>

            <div v-if="$slots.tabs" class="border-b border-slate-200 px-6 py-3 dark:border-slate-700/60">
                <slot name="tabs" />
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
                <slot />
            </div>

            <div
                v-if="errors.length"
                role="alert"
                class="max-h-32 shrink-0 overflow-y-auto border-t border-rose-200 bg-rose-50 px-6 py-3 dark:border-rose-500/30 dark:bg-rose-500/10"
            >
                <p class="text-sm font-medium text-rose-900 dark:text-rose-200">
                    {{ errorsTitle || $t("common.notifications.validation_error") }}
                </p>
                <ul class="mt-1 list-disc space-y-0.5 pl-5 text-sm text-rose-800 dark:text-rose-300">
                    <li v-for="message in errors" :key="message">{{ message }}</li>
                </ul>
            </div>

            <footer
                class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 border-t border-slate-200 px-6 py-4 dark:border-slate-700/60"
            >
                <p
                    v-if="toast"
                    :role="toast.success ? 'status' : 'alert'"
                    class="flex min-w-0 flex-1 items-start gap-2 text-sm text-slate-900 max-sm:basis-full dark:text-white"
                >
                    <span
                        aria-hidden="true"
                        class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                        :class="toast.success ? 'bg-emerald-600 dark:bg-emerald-400' : 'bg-rose-600 dark:bg-rose-400'"
                    ></span>
                    <span class="min-w-0">{{ toast.message }}</span>
                </p>
                <p
                    v-else-if="note"
                    class="min-w-0 flex-1 text-sm text-slate-600 max-sm:basis-full dark:text-slate-400"
                >
                    {{ note }}
                </p>
                <span v-else class="flex-1"></span>

                <div class="flex shrink-0 items-center gap-3 max-sm:ml-auto">
                    <SecondaryButton type="button" @click="$emit('close')">
                        {{ $t("common.cancel") }}
                    </SecondaryButton>
                    <PrimaryButton type="submit" :disabled="processing">
                        {{ processing ? $t("common.saving") : submitLabel || $t("common.save") }}
                    </PrimaryButton>
                </div>
            </footer>
        </form>
    </Modal>
</template>
