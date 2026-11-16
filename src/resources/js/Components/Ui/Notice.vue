<script setup>
/**
 * Something the reader has to know before going on, set in the page (not a toast): the
 * result of a test, a warning, a state. The tone says how it ended; the text says what.
 */
defineProps({
    tone: {
        type: String,
        default: "info",
        validator: (value) => ["success", "info", "warning", "danger"].includes(value),
    },
    title: { type: String, default: "" },
});

const TONES = {
    success: "border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200",
    info: "border-indigo-200 bg-indigo-50 text-indigo-900 dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-200",
    warning: "border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200",
    danger: "border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200",
};
</script>

<template>
    <div
        :role="tone === 'danger' ? 'alert' : 'status'"
        class="rounded-xl border px-4 py-3 text-sm"
        :class="TONES[tone]"
    >
        <p v-if="title" class="font-medium">{{ title }}</p>
        <div v-if="$slots.default" :class="title ? 'mt-0.5' : ''" class="break-words">
            <slot />
        </div>
    </div>
</template>
