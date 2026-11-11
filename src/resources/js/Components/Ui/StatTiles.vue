<script setup>
/**
 * A row of key figures in one frame, separated by hairlines however they wrap: a label, the
 * number, and one line that says what the number is made of.
 */
defineProps({
    // [{ key, label, value, caption? }]
    items: { type: Array, required: true },
    label: { type: String, default: "" },
});
</script>

<template>
    <section
        :aria-label="label || undefined"
        class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700/60 dark:bg-slate-800/50"
    >
        <dl class="-m-px grid grid-cols-2 xl:grid-cols-4">
            <div
                v-for="item in items"
                :key="item.key"
                class="flex flex-col gap-1.5 border-l border-t border-slate-200 p-5 dark:border-slate-700/60"
            >
                <dt class="text-sm font-medium text-slate-600 dark:text-slate-400">
                    {{ item.label }}
                </dt>
                <dd
                    class="text-3xl font-semibold leading-none tracking-tight tabular-nums text-slate-900 dark:text-white"
                >
                    {{ item.value }}
                </dd>
                <dd
                    v-if="item.caption"
                    class="text-xs tabular-nums text-slate-600 dark:text-slate-400"
                >
                    {{ item.caption }}
                </dd>
            </div>
        </dl>
    </section>
</template>
