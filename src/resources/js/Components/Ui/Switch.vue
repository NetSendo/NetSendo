<script setup>
/**
 * On or off, changed in place. The label is what the switch turns on, read out together
 * with its state; the caller does the change and passes the new state back.
 */
defineProps({
    modelValue: { type: Boolean, default: false },
    label: { type: String, required: true },
    disabled: { type: Boolean, default: false },
});

defineEmits(["update:modelValue"]);
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="modelValue"
        :aria-label="label"
        :disabled="disabled"
        class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
        :class="modelValue ? 'bg-emerald-600 dark:bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600'"
        @click="$emit('update:modelValue', !modelValue)"
    >
        <span
            aria-hidden="true"
            class="inline-block h-5 w-5 rounded-full bg-white shadow-sm transition-transform"
            :class="modelValue ? 'translate-x-[22px]' : 'translate-x-0.5'"
        ></span>
    </button>
</template>
