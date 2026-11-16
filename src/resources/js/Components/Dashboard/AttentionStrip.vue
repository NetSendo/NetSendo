<script setup>
import { Link } from "@inertiajs/vue3";

/**
 * Everything that needs the user's reaction, in one place and only when there is something:
 * licence, CRON, missing mailbox, audit findings. Nothing to do means nothing is drawn.
 */
defineProps({
    // [{ id, text, action, href }]
    items: { type: Array, default: () => [] },
});
</script>

<template>
    <section
        v-if="items.length"
        :aria-label="$t('dashboard.attention.title')"
        class="flex flex-col gap-x-6 gap-y-3 rounded-2xl border border-amber-300 bg-amber-50 px-5 py-4 lg:flex-row lg:items-start dark:border-amber-500/30 dark:bg-amber-500/10"
    >
        <div
            class="flex shrink-0 items-center gap-2 text-sm font-semibold text-amber-900 dark:text-amber-200"
        >
            <svg
                aria-hidden="true"
                class="h-5 w-5"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <circle cx="12" cy="12" r="9" />
                <path d="M12 8v5M12 16.5h.01" />
            </svg>
            {{ $t("dashboard.attention.count", { count: items.length }) }}
        </div>
        <ul
            class="flex min-w-0 flex-1 flex-col gap-x-6 gap-y-1.5 text-sm text-amber-950 sm:flex-row sm:flex-wrap dark:text-amber-100"
        >
            <li v-for="item in items" :key="item.id">
                {{ item.text }}
                <Link
                    :href="item.href"
                    class="ml-1 whitespace-nowrap font-semibold underline underline-offset-2 hover:no-underline"
                    >{{ item.action }}</Link
                >
            </li>
        </ul>
    </section>
</template>
