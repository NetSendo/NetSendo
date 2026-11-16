<script setup>
/**
 * The search box of a list: a magnifier, the words, and the same frame as the other filters.
 */
defineProps({
    modelValue: { type: String, default: "" },
    placeholder: { type: String, required: true },
});

defineEmits(["update:modelValue"]);
</script>

<template>
    <label class="relative min-w-0 flex-1 basis-64">
        <span class="sr-only">{{ placeholder }}</span>
        <svg
            aria-hidden="true"
            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500 dark:text-slate-400"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
            viewBox="0 0 24 24"
        >
            <circle cx="11" cy="11" r="7" />
            <path stroke-linecap="round" d="m21 21-4.3-4.3" />
        </svg>
        <input
            :value="modelValue"
            type="search"
            :placeholder="placeholder"
            class="min-h-10 w-full rounded-xl border border-slate-300 bg-white pl-9 pr-3 text-sm text-slate-900 placeholder:text-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-white dark:placeholder:text-slate-400"
            @input="$emit('update:modelValue', $event.target.value)"
        />
    </label>
</template>
