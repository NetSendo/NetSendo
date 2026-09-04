<script setup>
// Starter looks. Applying one replaces the theme and (optionally) seeds the
// page with blocks built from the webinar's own content.
defineProps({
    presets: { type: Array, required: true },
    busy: { type: String, default: '' },
});

const emit = defineEmits(['apply-theme', 'apply-full']);
</script>

<template>
    <div class="space-y-3">
        <p class="text-xs leading-relaxed text-gray-500 dark:text-gray-400">{{ $t('webinars.builder.presets.hint') }}</p>

        <div
            v-for="preset in presets"
            :key="preset.key"
            class="rounded-xl border border-gray-200 p-3 transition hover:border-indigo-400 dark:border-gray-700"
        >
            <div class="flex items-center gap-2">
                <div class="flex overflow-hidden rounded-md border border-gray-200 dark:border-gray-600">
                    <span v-for="(color, index) in preset.swatches" :key="index" class="h-6 w-5" :style="{ background: color }"></span>
                </div>
                <span class="text-sm font-medium text-gray-800 dark:text-gray-100">{{ $t(`webinars.builder.presets.${preset.key}`) }}</span>
            </div>

            <div class="mt-2 flex gap-2">
                <button
                    type="button"
                    :disabled="busy === preset.key"
                    @click="emit('apply-full', preset.key)"
                    class="flex-1 rounded-md bg-indigo-600 px-2 py-1.5 text-xs font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-50"
                >
                    {{ busy === preset.key ? $t('webinars.builder.presets.applying') : $t('webinars.builder.presets.apply_full') }}
                </button>
                <button
                    type="button"
                    @click="emit('apply-theme', preset.key)"
                    class="rounded-md border border-gray-300 px-2 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                >
                    {{ $t('webinars.builder.presets.apply_theme') }}
                </button>
            </div>
        </div>
    </div>
</template>
