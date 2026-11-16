<script setup>
/**
 * Small "GOLD" pill marking a feature of the paid licence. With `locked` it shows a
 * padlock, for controls a SILVER installation cannot use.
 */
defineProps({
    locked: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full bg-gradient-to-r from-amber-400 to-yellow-500 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-950 shadow-sm"
    >
        <svg
            v-if="locked"
            class="h-3 w-3"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="2.5"
            aria-hidden="true"
        >
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M16 11V7a4 4 0 00-8 0v4M5 11h14v10H5z"
            />
        </svg>
        {{ $t('license.gating.badge') }}
    </span>
</template>
