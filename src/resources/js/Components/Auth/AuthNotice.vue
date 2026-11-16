<script setup>
/** A message above a form: something went through (success) or needs a look (warning). */
defineProps({
    tone: {
        type: String,
        default: "success",
        validator: (value) => ["success", "warning"].includes(value),
    },
});
</script>

<template>
    <p
        role="status"
        class="mb-5 rounded-xl border px-3.5 py-2.5 text-sm"
        :class="
            tone === 'success'
                ? 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200'
                : 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100'
        "
    >
        <slot />
    </p>
</template>
