<script setup>
import { ref } from "vue";
import { hintClass, labelClass } from "@/Components/Ui/fieldClass";

/**
 * An address or a value to paste somewhere else (a webhook URL, a redirect URI): shown in
 * full, with a button that copies it and says so.
 */
const props = defineProps({
    label: { type: String, default: "" },
    value: { type: String, default: "" },
    hint: { type: String, default: "" },
});

const copied = ref(false);
let timer = null;

const copy = async () => {
    try {
        await navigator.clipboard.writeText(props.value);
    } catch (error) {
        // No clipboard permission (plain http): the old way still works
        const textarea = document.createElement("textarea");
        textarea.value = props.value;
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
    <div>
        <p v-if="label" :class="labelClass">{{ label }}</p>
        <div class="flex items-stretch gap-2" :class="label ? 'mt-1' : ''">
            <code
                class="flex min-h-10 min-w-0 flex-1 items-center break-all rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-900 dark:border-slate-700/60 dark:bg-slate-900/60 dark:text-slate-100"
            >
                {{ value }}
            </code>
            <button
                type="button"
                class="inline-flex min-h-10 shrink-0 items-center rounded-xl border border-slate-300 px-3.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700/60"
                @click="copy"
            >
                <span aria-live="polite">{{ copied ? $t("common.copied") : $t("common.copy") }}</span>
            </button>
        </div>
        <p v-if="hint" :class="hintClass">{{ hint }}</p>
    </div>
</template>
