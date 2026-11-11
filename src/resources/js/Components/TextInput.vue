<script setup>
import { onMounted, ref } from "vue";

defineOptions({
    inheritAttrs: false,
});

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute("autofocus")) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="min-h-10 rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 disabled:bg-slate-100 disabled:text-slate-600 dark:border-slate-600 dark:bg-slate-900/40 dark:text-white dark:placeholder:text-slate-400 dark:disabled:bg-slate-800 dark:disabled:text-slate-400"
        v-model="model"
        ref="input"
        v-bind="$attrs"
    />
</template>
