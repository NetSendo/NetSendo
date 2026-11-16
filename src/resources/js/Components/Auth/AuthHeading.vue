<script setup>
/** What the screen is for, in one line, and one sentence of help under it. */
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: "" },
    // A small label above the title, e.g. "First run"
    eyebrow: { type: String, default: "" },
});
</script>

<template>
    <div class="mb-8 flex flex-col gap-2">
        <p
            v-if="eyebrow"
            class="text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400"
        >
            {{ eyebrow }}
        </p>
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">
            {{ title }}
        </h1>
        <p v-if="subtitle" class="text-sm text-slate-600 dark:text-slate-400">
            {{ subtitle }}
        </p>
    </div>
</template>
