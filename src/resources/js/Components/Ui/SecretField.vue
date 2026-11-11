<script setup>
import { ref } from "vue";
import { errorClass, fieldClass, hintClass, labelClass } from "@/Components/Ui/fieldClass";

/**
 * A field for a key, a token or a password: hidden as it is typed, with a button to look at
 * it. A saved secret is never sent back to the page; its masked form goes in `placeholder`,
 * and leaving the field empty keeps what is stored.
 */
defineProps({
    id: { type: String, required: true },
    modelValue: { type: String, default: "" },
    label: { type: String, required: true },
    placeholder: { type: String, default: "" },
    hint: { type: String, default: "" },
    error: { type: String, default: "" },
});

defineEmits(["update:modelValue"]);

const visible = ref(false);
</script>

<template>
    <div>
        <label :for="id" :class="labelClass">{{ label }}</label>
        <div class="relative mt-1">
            <input
                :id="id"
                :value="modelValue"
                :type="visible ? 'text' : 'password'"
                :placeholder="placeholder"
                autocomplete="off"
                spellcheck="false"
                class="pr-11 font-mono"
                :class="[fieldClass, { '!border-rose-500': error }]"
                @input="$emit('update:modelValue', $event.target.value)"
            />
            <button
                type="button"
                :aria-label="visible ? $t('common.hide') : $t('common.show')"
                :aria-pressed="visible"
                class="absolute right-1 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-700/60 dark:hover:text-white"
                @click="visible = !visible"
            >
                <svg
                    aria-hidden="true"
                    class="h-4 w-4"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    viewBox="0 0 24 24"
                >
                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" />
                    <circle cx="12" cy="12" r="3" />
                    <path v-if="visible" d="M4 4l16 16" />
                </svg>
            </button>
        </div>
        <p v-if="error" :class="errorClass">{{ error }}</p>
        <p v-else-if="hint" :class="hintClass">{{ hint }}</p>
    </div>
</template>
