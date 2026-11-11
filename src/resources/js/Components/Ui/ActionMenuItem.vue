<script setup>
import { Link } from "@inertiajs/vue3";

/** One entry of an ActionMenu: a link when it has `href`, a button otherwise. */
defineProps({
    href: { type: String, default: null },
    danger: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});
</script>

<template>
    <component
        :is="href ? Link : 'button'"
        :href="href || undefined"
        :type="href ? undefined : 'button'"
        :disabled="href ? undefined : disabled"
        role="menuitem"
        class="flex w-full items-center gap-2 px-3.5 py-2 text-left text-sm transition-colors focus:outline-none disabled:opacity-50"
        :class="
            danger
                ? 'text-rose-700 hover:bg-rose-50 focus:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/10 dark:focus:bg-rose-500/10'
                : 'text-slate-700 hover:bg-slate-100 focus:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700/60 dark:focus:bg-slate-700/60'
        "
    >
        <slot />
    </component>
</template>
