<script setup>
/** A short list of mutually exclusive choices shown side by side (period, view, unit). */
defineProps({
    modelValue: { type: [String, Number], required: true },
    // [{ value, label, count? }]: a count is shown after the label, as on list tabs
    options: { type: Array, required: true },
    label: { type: String, required: true },
});

defineEmits(["update:modelValue"]);
</script>

<template>
    <div
        role="group"
        :aria-label="label"
        class="inline-flex max-w-full overflow-x-auto rounded-xl border border-slate-200 bg-white p-0.5 dark:border-slate-700/60 dark:bg-slate-800/50"
    >
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            :aria-pressed="option.value === modelValue"
            class="inline-flex min-h-9 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-[10px] px-3 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
            :class="
                option.value === modelValue
                    ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900'
                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white'
            "
            @click="$emit('update:modelValue', option.value)"
        >
            {{ option.label }}
            <span
                v-if="option.count !== undefined"
                class="tabular-nums"
                :class="
                    option.value === modelValue
                        ? 'text-slate-300 dark:text-slate-600'
                        : 'text-slate-500 dark:text-slate-400'
                "
                >{{ option.count }}</span
            >
        </button>
    </div>
</template>
