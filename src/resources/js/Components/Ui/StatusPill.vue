<script setup>
/**
 * A state in two words at most. The dot and the tint repeat what the text says; the tones
 * differ in lightness as well as hue, so they also read in greyscale.
 */
defineProps({
    tone: {
        type: String,
        default: "neutral",
        validator: (value) => ["success", "info", "warning", "danger", "neutral"].includes(value),
    },
});

const TONES = {
    success: "bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300",
    info: "bg-indigo-50 text-indigo-800 dark:bg-indigo-500/10 dark:text-indigo-300",
    warning: "bg-amber-50 text-amber-900 dark:bg-amber-500/10 dark:text-amber-200",
    danger: "bg-rose-50 text-rose-800 dark:bg-rose-500/10 dark:text-rose-300",
    neutral: "bg-slate-100 text-slate-700 dark:bg-slate-700/60 dark:text-slate-300",
};
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold"
        :class="TONES[tone]"
    >
        <span aria-hidden="true" class="h-1.5 w-1.5 rounded-full bg-current"></span>
        <slot />
    </span>
</template>
