<script setup>
/**
 * What a list shows when it has no rows: what is missing, one line on why it matters, and
 * the way out in the slot (clear the filters, or create the first one).
 */
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: "" },
});
</script>

<template>
    <div
        class="flex flex-col items-center gap-2 border-t border-slate-200 px-5 py-16 text-center dark:border-slate-700/60"
    >
        <p class="text-sm font-medium text-slate-900 dark:text-white">{{ title }}</p>
        <p v-if="description" class="max-w-md text-sm text-slate-600 dark:text-slate-400">
            {{ description }}
        </p>
        <div v-if="$slots.default" class="mt-2 flex flex-wrap items-center justify-center gap-3">
            <slot />
        </div>
    </div>
</template>
