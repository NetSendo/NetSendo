<script setup>
// Right-hand inspector: edits the selected block's props or the selected row's
// layout and background.
import { computed } from 'vue';
import ItemsEditor from './ItemsEditor.vue';
import { BLOCK_FIELDS } from './blockMeta';

const props = defineProps({
    block: { type: Object, default: null },
    row: { type: Object, default: null },
    options: { type: Object, required: true },
});

const emit = defineEmits(['remove-block', 'duplicate-block', 'remove-row', 'duplicate-row']);

const fields = computed(() => (props.block ? BLOCK_FIELDS[props.block.type] || [] : []));

const visible = (field) => {
    if (!field.showIf) return true;
    return Object.entries(field.showIf).every(([key, value]) => props.block.props[key] === value);
};

const label = (field) => `webinars.builder.blocks.${props.block.type}.props.${field.key}`;
</script>

<template>
    <div v-if="block" class="space-y-4">
        <div class="flex items-center gap-2">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                {{ $t(`webinars.builder.blocks.${block.type}.label`) }}
            </h3>
            <div class="ml-auto flex items-center gap-1">
                <button type="button" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-700" :title="$t('webinars.builder.duplicate')" @click="emit('duplicate-block')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5h10"/></svg>
                </button>
                <button type="button" class="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600" :title="$t('webinars.builder.remove')" @click="emit('remove-block')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13"/></svg>
                </button>
            </div>
        </div>

        <div v-for="field in fields.filter(visible)" :key="field.key" class="space-y-1">
            <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {{ $t(label(field)) }}
            </label>

            <input
                v-if="field.type === 'text'"
                type="text"
                v-model="block.props[field.key]"
                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
            >

            <textarea
                v-else-if="field.type === 'textarea'"
                rows="4"
                v-model="block.props[field.key]"
                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
            ></textarea>

            <textarea
                v-else-if="field.type === 'code'"
                rows="6"
                v-model="block.props[field.key]"
                spellcheck="false"
                class="w-full rounded-md border-gray-300 font-mono text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
            ></textarea>

            <input
                v-else-if="field.type === 'datetime'"
                type="datetime-local"
                v-model="block.props[field.key]"
                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
            >

            <select
                v-else-if="field.type === 'select'"
                v-model="block.props[field.key]"
                class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
            >
                <option v-for="option in field.options" :key="option" :value="option">
                    {{ $t(`webinars.builder.values.${option}`) }}
                </option>
            </select>

            <div v-else-if="field.type === 'segment'" class="flex rounded-md border border-gray-200 p-0.5 dark:border-gray-700">
                <button
                    v-for="option in field.options"
                    :key="option"
                    type="button"
                    @click="block.props[field.key] = option"
                    class="flex-1 rounded px-2 py-1 text-xs font-medium transition"
                    :class="block.props[field.key] === option
                        ? 'bg-indigo-600 text-white'
                        : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'"
                >
                    {{ $t(`webinars.builder.values.${option}`) }}
                </button>
            </div>

            <div v-else-if="field.type === 'align'" class="flex rounded-md border border-gray-200 p-0.5 dark:border-gray-700">
                <button
                    v-for="option in ['left', 'center', 'right']"
                    :key="option"
                    type="button"
                    @click="block.props[field.key] = option"
                    class="flex-1 rounded px-2 py-1 text-xs font-medium transition"
                    :class="block.props[field.key] === option
                        ? 'bg-indigo-600 text-white'
                        : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'"
                >
                    {{ $t(`webinars.builder.values.${option}`) }}
                </button>
            </div>

            <label v-else-if="field.type === 'toggle'" class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" v-model="block.props[field.key]" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800">
                <span>{{ block.props[field.key] ? $t('webinars.builder.on') : $t('webinars.builder.off') }}</span>
            </label>

            <div v-else-if="field.type === 'range'" class="flex items-center gap-2">
                <input
                    type="range"
                    :min="field.min"
                    :max="field.max"
                    :step="field.step"
                    v-model.number="block.props[field.key]"
                    class="flex-1"
                >
                <span class="w-10 text-right text-xs text-gray-500">{{ block.props[field.key] }}%</span>
            </div>

            <ItemsEditor
                v-else-if="field.type === 'items'"
                v-model="block.props[field.key]"
                :fields="field.fields"
                :block-type="block.type"
                :prop-key="field.key"
            />
        </div>

        <p class="rounded-md bg-gray-50 p-2 text-[11px] leading-relaxed text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
            {{ $t('webinars.builder.tokens_hint') }}
        </p>
    </div>

    <div v-else-if="row" class="space-y-4">
        <div class="flex items-center gap-2">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $t('webinars.builder.row_settings') }}</h3>
            <div class="ml-auto flex items-center gap-1">
                <button type="button" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-700" :title="$t('webinars.builder.duplicate')" @click="emit('duplicate-row')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5h10"/></svg>
                </button>
                <button type="button" class="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600" :title="$t('webinars.builder.remove')" @click="emit('remove-row')">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13"/></svg>
                </button>
            </div>
        </div>

        <div class="space-y-1">
            <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $t('webinars.builder.row.background') }}</label>
            <select v-model="row.style.background" class="w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                <option v-for="option in options.rowBackgrounds" :key="option" :value="option">{{ $t(`webinars.builder.values.${option}`) }}</option>
            </select>
        </div>

        <div v-if="row.style.background === 'custom'" class="space-y-1">
            <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $t('webinars.builder.row.background_color') }}</label>
            <div class="flex items-center gap-2">
                <input type="color" v-model="row.style.background_color" class="h-8 w-10 rounded border border-gray-300 dark:border-gray-600">
                <input type="text" v-model="row.style.background_color" class="flex-1 rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
                <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $t('webinars.builder.row.padding') }}</label>
                <select v-model="row.style.padding" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option v-for="option in options.spacings" :key="option" :value="option">{{ $t(`webinars.builder.values.${option}`) }}</option>
                </select>
            </div>
            <div class="space-y-1">
                <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $t('webinars.builder.row.gap') }}</label>
                <select v-model="row.style.gap" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option v-for="option in options.spacings" :key="option" :value="option">{{ $t(`webinars.builder.values.${option}`) }}</option>
                </select>
            </div>
            <div class="space-y-1">
                <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $t('webinars.builder.row.width') }}</label>
                <select v-model="row.style.width" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option v-for="option in options.containers" :key="option" :value="option">{{ $t(`webinars.builder.values.${option}`) }}</option>
                </select>
            </div>
            <div class="space-y-1">
                <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $t('webinars.builder.row.radius') }}</label>
                <select v-model="row.style.radius" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option v-for="option in options.radii" :key="option" :value="option">{{ $t(`webinars.builder.values.${option}`) }}</option>
                </select>
            </div>
            <div class="space-y-1">
                <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $t('webinars.builder.row.align') }}</label>
                <select v-model="row.style.align" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option v-for="option in options.alignments" :key="option" :value="option">{{ $t(`webinars.builder.values.${option}`) }}</option>
                </select>
            </div>
            <div class="space-y-1">
                <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $t('webinars.builder.row.vertical_align') }}</label>
                <select v-model="row.style.vertical_align" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option v-for="option in ['top', 'middle', 'bottom']" :key="option" :value="option">{{ $t(`webinars.builder.values.${option}`) }}</option>
                </select>
            </div>
            <div class="space-y-1 col-span-2">
                <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $t('webinars.builder.row.margin_bottom') }}</label>
                <select v-model="row.style.margin_bottom" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option v-for="option in options.spacings" :key="option" :value="option">{{ $t(`webinars.builder.values.${option}`) }}</option>
                </select>
            </div>
        </div>
    </div>

    <p v-else class="px-1 text-sm text-gray-500 dark:text-gray-400">
        {{ $t('webinars.builder.nothing_selected') }}
    </p>
</template>
