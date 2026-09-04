<script setup>
// Theme inspector: colours, typography and shape for the whole page.
const props = defineProps({
    theme: { type: Object, required: true },
    options: { type: Object, required: true },
});

const COLORS = [
    'background',
    'background_to',
    'surface',
    'surface_border',
    'text',
    'muted',
    'heading',
    'primary',
    'primary_to',
    'primary_text',
    'card_background',
    'card_text',
];

// rgba() values are legitimate for surfaces, but <input type="color"> only
// understands hex — show the picker when we can, always keep the text input.
const isHex = (value) => /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(String(value || ''));
</script>

<template>
    <div class="space-y-5">
        <div class="space-y-1">
            <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $t('webinars.builder.theme.background_type') }}</label>
            <div class="flex rounded-md border border-gray-200 p-0.5 dark:border-gray-700">
                <button
                    v-for="option in ['solid', 'gradient', 'image']"
                    :key="option"
                    type="button"
                    @click="theme.background_type = option"
                    class="flex-1 rounded px-2 py-1 text-xs font-medium transition"
                    :class="theme.background_type === option ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'"
                >
                    {{ $t(`webinars.builder.values.${option}`) }}
                </button>
            </div>
        </div>

        <div v-if="theme.background_type === 'gradient'" class="space-y-1">
            <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $t('webinars.builder.theme.background_angle') }}</label>
            <div class="flex items-center gap-2">
                <input type="range" min="0" max="360" step="5" v-model.number="theme.background_angle" class="flex-1">
                <span class="w-10 text-right text-xs text-gray-500">{{ theme.background_angle }}°</span>
            </div>
        </div>

        <div v-if="theme.background_type === 'image'" class="space-y-1">
            <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $t('webinars.builder.theme.background_image') }}</label>
            <input type="text" v-model="theme.background_image" placeholder="https://…" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
        </div>

        <div class="space-y-2">
            <h4 class="text-[11px] font-semibold uppercase tracking-wider text-gray-400">{{ $t('webinars.builder.theme.colors') }}</h4>
            <div v-for="key in COLORS" :key="key" class="flex items-center gap-2">
                <span class="w-28 shrink-0 text-xs text-gray-600 dark:text-gray-300">{{ $t(`webinars.builder.theme.${key}`) }}</span>
                <input
                    v-if="isHex(theme[key])"
                    type="color"
                    v-model="theme[key]"
                    class="h-7 w-9 shrink-0 rounded border border-gray-300 dark:border-gray-600"
                >
                <input
                    type="text"
                    v-model="theme[key]"
                    class="min-w-0 flex-1 rounded-md border-gray-300 text-xs dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                >
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
                <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $t('webinars.builder.theme.font') }}</label>
                <select v-model="theme.font" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option v-for="option in options.fonts" :key="option" :value="option">{{ $t(`webinars.builder.values.${option}`) }}</option>
                </select>
            </div>
            <div class="space-y-1">
                <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $t('webinars.builder.theme.heading_font') }}</label>
                <select v-model="theme.heading_font" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option v-for="option in options.fonts" :key="option" :value="option">{{ $t(`webinars.builder.values.${option}`) }}</option>
                </select>
            </div>
            <div class="space-y-1">
                <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $t('webinars.builder.theme.radius') }}</label>
                <select v-model="theme.radius" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option v-for="option in options.radii" :key="option" :value="option">{{ $t(`webinars.builder.values.${option}`) }}</option>
                </select>
            </div>
            <div class="space-y-1">
                <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $t('webinars.builder.theme.container') }}</label>
                <select v-model="theme.container" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option v-for="option in options.containers" :key="option" :value="option">{{ $t(`webinars.builder.values.${option}`) }}</option>
                </select>
            </div>
            <div class="space-y-1 col-span-2">
                <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $t('webinars.builder.theme.button_style') }}</label>
                <div class="flex rounded-md border border-gray-200 p-0.5 dark:border-gray-700">
                    <button
                        v-for="option in ['solid', 'gradient', 'outline']"
                        :key="option"
                        type="button"
                        @click="theme.button_style = option"
                        class="flex-1 rounded px-2 py-1 text-xs font-medium transition"
                        :class="theme.button_style === option ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'"
                    >
                        {{ $t(`webinars.builder.values.${option}`) }}
                    </button>
                </div>
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" v-model="theme.shadow" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
            {{ $t('webinars.builder.theme.shadow') }}
        </label>
    </div>
</template>
