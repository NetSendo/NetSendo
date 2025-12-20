<script setup>
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const settings = defineModel('settings', { type: Object, default: () => ({}) });
const preheader = defineModel('preheader', { type: String, default: '' });
const category = defineModel('category', { type: String, default: '' });
const categoryId = defineModel('categoryId', { type: [Number, String], default: null });

const props = defineProps({
    categories: Array,
});

// Preset color palettes
const colorPresets = [
    { name: 'Indigo', primary: '#6366f1', secondary: '#4f46e5' },
    { name: 'Blue', primary: '#3b82f6', secondary: '#2563eb' },
    { name: 'Green', primary: '#10b981', secondary: '#059669' },
    { name: 'Rose', primary: '#f43f5e', secondary: '#e11d48' },
    { name: 'Orange', primary: '#f97316', secondary: '#ea580c' },
    { name: 'Purple', primary: '#a855f7', secondary: '#9333ea' },
];

// Font options
const fontOptions = [
    { value: 'Arial, Helvetica, sans-serif', label: 'Arial' },
    { value: 'Georgia, Times, serif', label: 'Georgia' },
    { value: 'Verdana, Geneva, sans-serif', label: 'Verdana' },
    { value: 'Tahoma, Geneva, sans-serif', label: 'Tahoma' },
    { value: "'Trebuchet MS', Helvetica, sans-serif", label: 'Trebuchet MS' },
];

// Width options
const widthOptions = [500, 550, 600, 650, 700];

// Update a setting
const updateSetting = (key, value) => {
    settings.value = { ...settings.value, [key]: value };
};

// Apply color preset
const applyPreset = (preset) => {
    updateSetting('primary_color', preset.primary);
    updateSetting('secondary_color', preset.secondary);
};
</script>

<template>
    <div class="space-y-6">
        <!-- Category -->
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.category') }}</label>
            <select 
                v-model="categoryId"
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
            >
                <option :value="null">{{ $t('template_builder.no_category') }}</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                    {{ cat.name }}
                </option>
            </select>
        </div>

        <!-- Preheader -->
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">
                {{ $t('template_builder.preheader') }}
                <span class="ml-1 text-slate-400">({{ $t('template_builder.optional') }})</span>
            </label>
            <textarea 
                v-model="preheader"
                rows="2"
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                :placeholder="$t('template_builder.preheader_placeholder')"
            ></textarea>
            <p class="mt-1 text-xs text-slate-400">{{ $t('template_builder.preheader_help') }}</p>
        </div>

        <hr class="border-slate-200 dark:border-slate-700" />

        <!-- Email Width -->
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.email_width') }}</label>
            <div class="flex gap-2">
                <button 
                    v-for="w in widthOptions" 
                    :key="w"
                    @click="updateSetting('width', w)"
                    :class="settings.width === w ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700'"
                    class="rounded-lg px-3 py-1.5 text-xs font-medium transition-colors"
                >
                    {{ w }}px
                </button>
            </div>
        </div>

        <!-- Font Family -->
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.font_family') }}</label>
            <select 
                :value="settings.font_family"
                @change="updateSetting('font_family', $event.target.value)"
                class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
            >
                <option v-for="font in fontOptions" :key="font.value" :value="font.value">
                    {{ font.label }}
                </option>
            </select>
        </div>

        <hr class="border-slate-200 dark:border-slate-700" />

        <!-- Color Presets -->
        <div>
            <label class="mb-2 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.color_presets') }}</label>
            <div class="grid grid-cols-3 gap-2">
                <button 
                    v-for="preset in colorPresets" 
                    :key="preset.name"
                    @click="applyPreset(preset)"
                    class="flex items-center gap-2 rounded-lg border border-slate-200 p-2 text-xs transition-all hover:border-indigo-300 hover:shadow-sm dark:border-slate-700 dark:hover:border-indigo-600"
                >
                    <div class="flex h-5 w-5 overflow-hidden rounded-full">
                        <div class="h-full w-1/2" :style="{ backgroundColor: preset.primary }"></div>
                        <div class="h-full w-1/2" :style="{ backgroundColor: preset.secondary }"></div>
                    </div>
                    <span class="text-slate-600 dark:text-slate-300">{{ $t(`template_builder.color_presets_list.${preset.name.toLowerCase()}`) }}</span>
                </button>
            </div>
        </div>

        <!-- Custom Colors -->
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.primary_color') }}</label>
                <div class="flex gap-2">
                    <input 
                        type="color" 
                        :value="settings.primary_color" 
                        @input="updateSetting('primary_color', $event.target.value)"
                        class="h-10 w-12 cursor-pointer rounded border border-slate-200 dark:border-slate-700"
                    />
                    <input 
                        type="text" 
                        :value="settings.primary_color" 
                        @input="updateSetting('primary_color', $event.target.value)"
                        class="flex-1 rounded-lg border border-slate-200 px-2 py-1 text-xs dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    />
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.secondary_color') }}</label>
                <div class="flex gap-2">
                    <input 
                        type="color" 
                        :value="settings.secondary_color" 
                        @input="updateSetting('secondary_color', $event.target.value)"
                        class="h-10 w-12 cursor-pointer rounded border border-slate-200 dark:border-slate-700"
                    />
                    <input 
                        type="text" 
                        :value="settings.secondary_color" 
                        @input="updateSetting('secondary_color', $event.target.value)"
                        class="flex-1 rounded-lg border border-slate-200 px-2 py-1 text-xs dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    />
                </div>
            </div>
        </div>

        <!-- Background Colors -->
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.page_background') }}</label>
                <div class="flex gap-2">
                    <input 
                        type="color" 
                        :value="settings.background_color" 
                        @input="updateSetting('background_color', $event.target.value)"
                        class="h-10 w-12 cursor-pointer rounded border border-slate-200 dark:border-slate-700"
                    />
                    <input 
                        type="text" 
                        :value="settings.background_color" 
                        @input="updateSetting('background_color', $event.target.value)"
                        class="flex-1 rounded-lg border border-slate-200 px-2 py-1 text-xs dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    />
                </div>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.content_background') }}</label>
                <div class="flex gap-2">
                    <input 
                        type="color" 
                        :value="settings.content_background" 
                        @input="updateSetting('content_background', $event.target.value)"
                        class="h-10 w-12 cursor-pointer rounded border border-slate-200 dark:border-slate-700"
                    />
                    <input 
                        type="text" 
                        :value="settings.content_background" 
                        @input="updateSetting('content_background', $event.target.value)"
                        class="flex-1 rounded-lg border border-slate-200 px-2 py-1 text-xs dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    />
                </div>
            </div>
        </div>

        <!-- Text Color -->
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-700 dark:text-slate-300">{{ $t('template_builder.text_color') }}</label>
            <div class="flex gap-2">
                <input 
                    type="color" 
                    :value="settings.text_color" 
                    @input="updateSetting('text_color', $event.target.value)"
                    class="h-10 w-14 cursor-pointer rounded border border-slate-200 dark:border-slate-700"
                />
                <input 
                    type="text" 
                    :value="settings.text_color" 
                    @input="updateSetting('text_color', $event.target.value)"
                    class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                />
            </div>
        </div>
    </div>
</template>
