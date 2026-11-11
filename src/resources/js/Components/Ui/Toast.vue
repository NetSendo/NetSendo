<script setup>
/**
 * The message from useToast(), drawn in the bottom right corner above everything else.
 */
defineProps({
    // { message, success } or null
    toast: { type: Object, default: null },
});

defineEmits(["close"]);
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transform transition duration-300 ease-out"
            enter-from-class="translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="toast"
                :role="toast.success ? 'status' : 'alert'"
                class="fixed bottom-6 right-6 z-50 flex max-w-sm items-start gap-3 rounded-xl border bg-white px-4 py-3 text-sm text-slate-900 shadow-lg max-sm:left-4 max-sm:right-4 max-sm:max-w-none dark:bg-slate-800 dark:text-white"
                :class="
                    toast.success
                        ? 'border-emerald-200 dark:border-emerald-500/30'
                        : 'border-rose-200 dark:border-rose-500/30'
                "
            >
                <span
                    aria-hidden="true"
                    class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                    :class="
                        toast.success
                            ? 'bg-emerald-600 dark:bg-emerald-400'
                            : 'bg-rose-600 dark:bg-rose-400'
                    "
                ></span>
                <span class="min-w-0 flex-1">{{ toast.message }}</span>
                <button
                    type="button"
                    :aria-label="$t('common.close')"
                    class="-mr-1 -mt-1 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700/60"
                    @click="$emit('close')"
                >
                    <svg
                        aria-hidden="true"
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                    </svg>
                </button>
            </div>
        </Transition>
    </Teleport>
</template>
