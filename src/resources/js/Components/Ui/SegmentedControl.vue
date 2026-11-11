<script setup>
/** A short list of mutually exclusive choices shown side by side (period, view, unit). */
defineProps({
    modelValue: { type: [String, Number], required: true },
    // [{ value, label }]
    options: { type: Array, required: true },
    label: { type: String, required: true },
});

defineEmits(["update:modelValue"]);
</script>

<template>
    <div
        role="group"
        :aria-label="label"
        class="inline-flex rounded-xl border border-slate-200 bg-white p-0.5 dark:border-slate-700/60 dark:bg-slate-800/50"
    >
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            :aria-pressed="option.value === modelValue"
            class="min-h-9 rounded-[10px] px-3 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
            :class="
                option.value === modelValue
                    ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900'
                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white'
            "
            @click="$emit('update:modelValue', option.value)"
        >
            {{ option.label }}
        </button>
    </div>
</template>
