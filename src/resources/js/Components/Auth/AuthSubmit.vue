<script setup>
/** The one primary button of a signed-out screen; it shows that the form is on its way. */
defineProps({
    processing: { type: Boolean, default: false },
    processingLabel: { type: String, default: "" },
});
</script>

<template>
    <button
        type="submit"
        :disabled="processing"
        class="flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 text-sm font-semibold text-white transition-colors hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500 disabled:cursor-not-allowed disabled:opacity-60"
    >
        <svg
            v-if="processing"
            aria-hidden="true"
            class="h-4 w-4 animate-spin"
            fill="none"
            viewBox="0 0 24 24"
        >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" />
        </svg>
        <template v-if="processing && processingLabel">{{ processingLabel }}</template>
        <slot v-else />
    </button>
</template>
