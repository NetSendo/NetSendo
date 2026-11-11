<script setup>
/**
 * A filter with a handful of choices. The options come in the slot; the label is read out
 * by screen readers, the first option says what the filter is about for everyone else.
 */
defineProps({
    modelValue: { type: [String, Number], default: "" },
    label: { type: String, required: true },
});

defineEmits(["update:modelValue"]);
</script>

<template>
    <select
        :value="modelValue"
        :aria-label="label"
        class="min-h-10 max-w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-white"
        @change="$emit('update:modelValue', $event.target.value)"
    >
        <slot />
    </select>
</template>
