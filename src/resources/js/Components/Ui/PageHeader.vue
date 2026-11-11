<script setup>
/**
 * What a page puts in the layout's header slot: its name, one line about it, and its main
 * action on the right. On a phone the layout wraps the two halves onto separate rows.
 */
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: "" },
});
</script>

<template>
    <div class="flex items-center justify-between gap-x-4">
        <div class="min-w-0">
            <h1 class="truncate text-xl font-semibold text-slate-900 dark:text-white">
                {{ title }}
            </h1>
            <p
                v-if="subtitle"
                class="truncate text-sm text-slate-600 max-lg:hidden dark:text-slate-400"
            >
                {{ subtitle }}
            </p>
        </div>
        <div
            v-if="$slots.default"
            class="flex shrink-0 items-center gap-2 max-sm:min-w-0 max-sm:shrink max-sm:flex-wrap"
        >
            <slot />
        </div>
    </div>
</template>
