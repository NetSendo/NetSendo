<script setup>
import { Link } from "@inertiajs/vue3";

/**
 * The main action of a list page: a plus and what it makes.
 */
defineProps({
    href: { type: String, required: true },
});
</script>

<template>
    <Link
        :href="href"
        class="inline-flex min-h-10 items-center gap-2 whitespace-nowrap rounded-xl bg-indigo-600 px-4 text-sm font-semibold text-white transition-colors hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
    >
        <svg
            aria-hidden="true"
            class="h-4 w-4"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2.4"
            stroke-linecap="round"
        >
            <path d="M12 5v14M5 12h14" />
        </svg>
        <slot />
    </Link>
</template>
