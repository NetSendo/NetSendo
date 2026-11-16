<script setup>
import { computed, onMounted, ref } from "vue";

/** A labelled input of the signed-out screens, with its error; passwords get a show/hide switch. */
const props = defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    modelValue: { type: String, default: "" },
    type: { type: String, default: "text" },
    error: { type: String, default: "" },
    hint: { type: String, default: "" },
    autofocus: { type: Boolean, default: false },
});

defineEmits(["update:modelValue"]);

defineOptions({ inheritAttrs: false });

const input = ref(null);
const revealed = ref(false);

const isPassword = computed(() => props.type === "password");
const inputType = computed(() => (isPassword.value && revealed.value ? "text" : props.type));

onMounted(() => {
    if (props.autofocus) input.value?.focus();
});
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label :for="id" class="text-sm font-medium text-slate-800 dark:text-slate-200">
            {{ label }}
        </label>
        <div class="relative">
            <input
                :id="id"
                ref="input"
                :type="inputType"
                :value="modelValue"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="error ? `${id}-error` : hint ? `${id}-hint` : undefined"
                v-bind="$attrs"
                class="min-h-11 w-full rounded-xl border bg-white px-3.5 text-sm text-slate-900 placeholder:text-slate-500 focus:outline-none focus:ring-1 disabled:bg-slate-100 disabled:text-slate-600 dark:bg-slate-800/60 dark:text-white dark:placeholder:text-slate-400 dark:disabled:bg-slate-800 dark:disabled:text-slate-400"
                :class="[
                    error
                        ? 'border-rose-500 focus:border-rose-500 focus:ring-rose-500'
                        : 'border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600',
                    isPassword ? 'pr-11' : '',
                ]"
                @input="$emit('update:modelValue', $event.target.value)"
            />
            <button
                v-if="isPassword"
                type="button"
                :aria-label="revealed ? $t('auth.hide_password') : $t('auth.show_password')"
                :aria-pressed="revealed"
                class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-xl text-slate-600 hover:text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500 dark:text-slate-400 dark:hover:text-white"
                @click="revealed = !revealed"
            >
                <svg
                    aria-hidden="true"
                    class="h-5 w-5"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    viewBox="0 0 24 24"
                >
                    <template v-if="revealed">
                        <path d="M3 3l18 18" />
                        <path d="M10.6 10.6a2 2 0 002.8 2.8" />
                        <path d="M9.4 5.4A9.6 9.6 0 0112 5c4.5 0 8.3 2.9 9.5 7a10 10 0 01-3 4.5M6.2 6.6A10 10 0 002.5 12c1.2 4.1 5 7 9.5 7 1.4 0 2.7-.3 3.9-.8" />
                    </template>
                    <template v-else>
                        <path d="M2.5 12C3.7 7.9 7.5 5 12 5s8.3 2.9 9.5 7c-1.2 4.1-5 7-9.5 7s-8.3-2.9-9.5-7z" />
                        <circle cx="12" cy="12" r="3" />
                    </template>
                </svg>
            </button>
        </div>
        <p v-if="error" :id="`${id}-error`" class="text-sm text-rose-700 dark:text-rose-300">
            {{ error }}
        </p>
        <p v-else-if="hint" :id="`${id}-hint`" class="text-xs text-slate-600 dark:text-slate-400">
            {{ hint }}
        </p>
    </div>
</template>
