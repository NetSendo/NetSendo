<script setup>
/** The arrow in a sortable column header: up, down, or a faint pair when the column is not the sort. */
defineProps({
    // 'asc' | 'desc' | null
    state: { type: String, default: null },
});
</script>

<template>
    <svg
        aria-hidden="true"
        class="h-3.5 w-3.5"
        :class="state ? 'text-slate-900 dark:text-white' : 'text-slate-400 dark:text-slate-500'"
        fill="none"
        stroke="currentColor"
        stroke-width="2.2"
        stroke-linecap="round"
        stroke-linejoin="round"
        viewBox="0 0 24 24"
    >
        <path v-if="state === 'asc'" d="M12 19V5M6 11l6-6 6 6" />
        <path v-else-if="state === 'desc'" d="M12 5v14M6 13l6 6 6-6" />
        <path v-else d="M8 9l4-4 4 4M8 15l4 4 4-4" />
    </svg>
</template>
