<script setup>
import { ref } from "vue";

/**
 * Code to paste somewhere else: shown as written, scrolling sideways instead of wrapping,
 * with a button that copies it and says so.
 */
const props = defineProps({
    code: { type: String, required: true },
});

const copied = ref(false);
let timer = null;

const copy = async () => {
    try {
        await navigator.clipboard.writeText(props.code);
    } catch (error) {
        // No clipboard permission (plain http): the old way still works
        const textarea = document.createElement("textarea");
        textarea.value = props.code;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand("copy");
        document.body.removeChild(textarea);
    }
    copied.value = true;
    clearTimeout(timer);
    timer = setTimeout(() => (copied.value = false), 2000);
};
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-700/60 dark:bg-slate-900/60">
        <div class="flex justify-end border-b border-slate-200 px-2 py-1 dark:border-slate-700/60">
            <button
                type="button"
                class="min-h-8 rounded-lg px-2.5 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-200/70 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500 dark:text-slate-200 dark:hover:bg-slate-700/60"
                @click="copy"
            >
                <span aria-live="polite">{{ copied ? $t("common.copied") : $t("common.copy") }}</span>
            </button>
        </div>
        <pre
            class="overflow-x-auto p-4 font-mono text-xs leading-relaxed text-slate-800 dark:text-slate-200"
        ><code>{{ code }}</code></pre>
    </div>
</template>
