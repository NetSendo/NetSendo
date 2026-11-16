<script setup>
/**
 * The one card surface of the redesigned panel: flat, hairline border, no shadow or gradient.
 * A title and actions go in the header; `flush` drops the body padding for tables and lists
 * that draw their own rows edge to edge.
 */
defineProps({
    title: { type: String, default: "" },
    flush: { type: Boolean, default: false },
});
</script>

<template>
    <section
        class="flex min-w-0 flex-col rounded-2xl border border-slate-200 bg-white dark:border-slate-700/60 dark:bg-slate-800/50"
    >
        <header
            v-if="title || $slots.title || $slots.actions"
            class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-5 pt-5"
        >
            <h2
                class="flex items-center gap-2 text-base font-semibold text-slate-900 dark:text-white"
            >
                <slot name="title">{{ title }}</slot>
            </h2>
            <div
                v-if="$slots.actions"
                class="flex items-center gap-3 text-sm"
            >
                <slot name="actions" />
            </div>
        </header>
        <div class="flex min-w-0 flex-1 flex-col" :class="flush ? 'pt-3' : 'p-5'">
            <slot />
        </div>
    </section>
</template>
