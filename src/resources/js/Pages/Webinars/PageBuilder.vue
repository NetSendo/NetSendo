<script setup>
/**
 * Visual builder for the public webinar funnel pages.
 *
 * Left: block palette / theme / presets. Middle: the canvas (rows, columns,
 * drag & drop) or a live server-rendered preview. Right: the inspector for the
 * selected block or row.
 */
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import BlockLibrary from '@/Components/Webinar/Builder/BlockLibrary.vue';
import BuilderCanvas from '@/Components/Webinar/Builder/BuilderCanvas.vue';
import PropertiesPanel from '@/Components/Webinar/Builder/PropertiesPanel.vue';
import ThemePanel from '@/Components/Webinar/Builder/ThemePanel.vue';
import PresetPicker from '@/Components/Webinar/Builder/PresetPicker.vue';
import { clone, columnsForLayout, newId } from '@/Components/Webinar/Builder/blockMeta';

const props = defineProps({
    webinar: { type: Object, required: true },
    page: { type: String, required: true },
    pages: { type: Array, required: true },
    fullPages: { type: Array, required: true },
    definition: { type: Object, required: true },
    catalog: { type: Object, required: true },
    presets: { type: Array, required: true },
    themeDefaults: { type: Object, required: true },
    options: { type: Object, required: true },
    builtPages: { type: Object, required: true },
});

// vue-i18n's global $t is template-only; the composable gives script access.
const { t } = useI18n();

const definition = reactive(clone(props.definition));
const selection = reactive({ type: 'none', rowId: null, columnIndex: null, blockId: null });
const sidebarTab = ref('blocks');
const mode = ref('canvas');
const device = ref('desktop');
const previewHtml = ref('');
const previewLoading = ref(false);
const saving = ref(false);
const dirty = ref(false);
const presetBusy = ref('');

// ---------------------------------------------------------------- selection

const selectedRow = computed(() => definition.rows.find((row) => row.id === selection.rowId) || null);

const selectedBlock = computed(() => {
    if (selection.type !== 'block' || !selectedRow.value) return null;
    const column = selectedRow.value.columns[selection.columnIndex];
    return column?.blocks.find((block) => block.id === selection.blockId) || null;
});

const usedTypes = computed(() =>
    definition.rows.flatMap((row) => row.columns.flatMap((column) => column.blocks.map((block) => block.type)))
);

const selectBlock = (rowId, columnIndex, blockId) => {
    Object.assign(selection, { type: 'block', rowId, columnIndex, blockId });
};

const selectRow = (rowId) => Object.assign(selection, { type: 'row', rowId, columnIndex: null, blockId: null });
const clearSelection = () => Object.assign(selection, { type: 'none', rowId: null, columnIndex: null, blockId: null });

// ------------------------------------------------------------------ editing

const emptyRow = (layout = '1') => ({
    id: newId('row'),
    layout,
    style: {
        background: 'none',
        background_color: '',
        padding: 'md',
        gap: 'md',
        align: 'left',
        vertical_align: 'top',
        width: 'normal',
        radius: 'xl',
        margin_bottom: 'md',
    },
    columns: Array.from({ length: columnsForLayout(layout) }, () => ({ blocks: [] })),
});

const addRow = (layout) => {
    const row = emptyRow(layout);
    definition.rows.push(row);
    selectRow(row.id);
};

const changeLayout = (rowId, layout) => {
    const row = definition.rows.find((r) => r.id === rowId);
    if (!row) return;

    const wanted = columnsForLayout(layout);

    // Never lose content: blocks from dropped columns move into the last one.
    while (row.columns.length > wanted) {
        const removed = row.columns.pop();
        row.columns[row.columns.length - 1].blocks.push(...removed.blocks);
    }
    while (row.columns.length < wanted) {
        row.columns.push({ blocks: [] });
    }

    row.layout = layout;
};

const moveRow = (index, delta) => {
    const target = index + delta;
    if (target < 0 || target >= definition.rows.length) return;
    const [moved] = definition.rows.splice(index, 1);
    definition.rows.splice(target, 0, moved);
};

const duplicateRow = (rowId) => {
    const index = definition.rows.findIndex((row) => row.id === rowId);
    if (index === -1) return;

    const copy = clone(definition.rows[index]);
    copy.id = newId('row');
    copy.columns.forEach((column) => {
        column.blocks = column.blocks
            .filter((block) => !props.catalog[block.type]?.unique)
            .map((block) => ({ ...block, id: newId() }));
    });

    definition.rows.splice(index + 1, 0, copy);
};

const removeRow = (rowId) => {
    definition.rows = definition.rows.filter((row) => row.id !== rowId);
    clearSelection();
};

const addBlock = (type) => {
    const catalogEntry = props.catalog[type];
    if (!catalogEntry) return;

    const block = { id: newId(), type, props: clone(catalogEntry.props) };

    let row = selectedRow.value;
    let columnIndex = selection.type === 'block' ? selection.columnIndex : 0;

    if (!row) {
        row = definition.rows[definition.rows.length - 1];
        columnIndex = 0;
    }
    if (!row) {
        row = emptyRow('1');
        definition.rows.push(row);
    }

    row.columns[columnIndex || 0].blocks.push(block);
    selectBlock(row.id, columnIndex || 0, block.id);
};

const duplicateBlock = () => {
    const row = selectedRow.value;
    if (!row || !selectedBlock.value) return;
    if (props.catalog[selectedBlock.value.type]?.unique) return;

    const blocks = row.columns[selection.columnIndex].blocks;
    const index = blocks.findIndex((block) => block.id === selection.blockId);
    const copy = { ...clone(blocks[index]), id: newId() };

    blocks.splice(index + 1, 0, copy);
    selectBlock(row.id, selection.columnIndex, copy.id);
};

const removeBlock = () => {
    const row = selectedRow.value;
    if (!row) return;

    const column = row.columns[selection.columnIndex];
    column.blocks = column.blocks.filter((block) => block.id !== selection.blockId);
    clearSelection();
};

// ------------------------------------------------------------------ presets

const applyPreset = async (preset, withContent) => {
    presetBusy.value = withContent ? preset : '';

    try {
        const { data } = await axios.post(
            route('webinars.pages.preset', [props.webinar.id, props.page]),
            { preset }
        );

        if (withContent) {
            definition.rows = data.definition.rows;
            definition.enabled = true;
        }
        Object.assign(definition.theme, data.definition.theme);
        clearSelection();
    } finally {
        presetBusy.value = '';
    }
};

// ------------------------------------------------------------------ preview

let previewTimer = null;

const refreshPreview = async () => {
    previewLoading.value = true;

    try {
        const { data } = await axios.post(
            route('webinars.pages.preview', [props.webinar.id, props.page]),
            { definition: JSON.parse(JSON.stringify(definition)) },
            { responseType: 'text' }
        );
        previewHtml.value = data;
    } catch (error) {
        previewHtml.value = `<p style="font-family: sans-serif; padding: 24px; color: #b91c1c;">${error?.message || 'Preview failed'}</p>`;
    } finally {
        previewLoading.value = false;
    }
};

const showPreview = () => {
    mode.value = 'preview';
    refreshPreview();
};

watch(
    () => JSON.stringify(definition),
    () => {
        dirty.value = true;

        if (mode.value !== 'preview') return;
        clearTimeout(previewTimer);
        previewTimer = setTimeout(refreshPreview, 500);
    }
);

// --------------------------------------------------------------------- save

const save = () => {
    saving.value = true;

    router.put(
        route('webinars.pages.update', [props.webinar.id, props.page]),
        { definition: JSON.parse(JSON.stringify(definition)) },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => { dirty.value = false; },
            onFinish: () => { saving.value = false; },
        }
    );
};

const resetToClassic = () => {
    if (!window.confirm(t('webinars.builder.reset_confirm'))) return;
    router.delete(route('webinars.pages.destroy', [props.webinar.id, props.page]));
};

const switchPage = (page) => {
    if (page === props.page) return;
    if (dirty.value && !window.confirm(t('webinars.builder.unsaved_confirm'))) return;
    router.get(route('webinars.pages.edit', [props.webinar.id, page]));
};

const publicUrl = computed(() =>
    props.page === 'purchase' ? props.webinar.thankyou_url : props.webinar.public_url
);
</script>

<template>
    <Head :title="$t('webinars.builder.title')" />

    <AuthenticatedLayout>
        <div class="flex h-[calc(100vh-4rem)] flex-col bg-gray-50 dark:bg-gray-900">
            <!-- Top bar -->
            <header class="flex flex-wrap items-center gap-3 border-b border-gray-200 bg-white px-4 py-2.5 dark:border-gray-700 dark:bg-gray-800">
                <Link :href="route('webinars.edit', webinar.id)" class="text-gray-400 transition hover:text-gray-700 dark:hover:text-gray-200">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 19l-7-7 7-7"/></svg>
                </Link>

                <div class="min-w-0">
                    <h1 class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ webinar.name }}</h1>
                    <p class="text-[11px] text-gray-400">{{ $t('webinars.builder.title') }}</p>
                </div>

                <!-- Page tabs -->
                <nav class="ml-2 flex flex-wrap gap-1">
                    <button
                        v-for="page in pages"
                        :key="page"
                        type="button"
                        @click="switchPage(page)"
                        class="flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium transition"
                        :class="page === props.page
                            ? 'bg-indigo-600 text-white'
                            : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'"
                    >
                        {{ $t(`webinars.builder.pages.${page}`) }}
                        <span v-if="builtPages[page]" class="h-1.5 w-1.5 rounded-full" :class="page === props.page ? 'bg-white' : 'bg-emerald-500'"></span>
                    </button>
                </nav>

                <div class="ml-auto flex items-center gap-2">
                    <label class="flex items-center gap-1.5 text-xs font-medium text-gray-600 dark:text-gray-300">
                        <input type="checkbox" v-model="definition.enabled" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700">
                        {{ $t('webinars.builder.enabled') }}
                    </label>

                    <div class="flex rounded-lg border border-gray-200 p-0.5 dark:border-gray-600">
                        <button type="button" @click="mode = 'canvas'" class="rounded px-2.5 py-1 text-xs font-medium transition" :class="mode === 'canvas' ? 'bg-gray-900 text-white dark:bg-gray-600' : 'text-gray-500'">
                            {{ $t('webinars.builder.canvas') }}
                        </button>
                        <button type="button" @click="showPreview" class="rounded px-2.5 py-1 text-xs font-medium transition" :class="mode === 'preview' ? 'bg-gray-900 text-white dark:bg-gray-600' : 'text-gray-500'">
                            {{ $t('webinars.builder.preview') }}
                        </button>
                    </div>

                    <a :href="publicUrl" target="_blank" rel="noopener" class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                        {{ $t('webinars.builder.open_public') }}
                    </a>

                    <button
                        type="button"
                        @click="resetToClassic"
                        class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-gray-600 transition hover:bg-red-50 hover:text-red-600 dark:border-gray-600 dark:text-gray-300"
                    >
                        {{ $t('webinars.builder.reset') }}
                    </button>

                    <button
                        type="button"
                        @click="save"
                        :disabled="saving"
                        class="rounded-lg bg-indigo-600 px-4 py-1.5 text-xs font-semibold text-white transition hover:bg-indigo-700 disabled:opacity-50"
                    >
                        {{ saving ? $t('webinars.builder.saving') : (dirty ? $t('webinars.builder.save_changes') : $t('webinars.builder.saved_label')) }}
                    </button>
                </div>
            </header>

            <div class="flex min-h-0 flex-1">
                <!-- Left sidebar -->
                <aside class="flex w-72 shrink-0 flex-col border-r border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex border-b border-gray-200 dark:border-gray-700">
                        <button
                            v-for="tab in ['blocks', 'theme', 'presets']"
                            :key="tab"
                            type="button"
                            @click="sidebarTab = tab"
                            class="flex-1 border-b-2 px-2 py-2.5 text-xs font-semibold transition"
                            :class="sidebarTab === tab
                                ? 'border-indigo-600 text-indigo-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                        >
                            {{ $t(`webinars.builder.tabs.${tab}`) }}
                        </button>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto p-3">
                        <BlockLibrary v-if="sidebarTab === 'blocks'" :catalog="catalog" :used-types="usedTypes" @add="addBlock" />
                        <ThemePanel v-else-if="sidebarTab === 'theme'" :theme="definition.theme" :options="options" />
                        <PresetPicker
                            v-else
                            :presets="presets"
                            :busy="presetBusy"
                            @apply-theme="(preset) => applyPreset(preset, false)"
                            @apply-full="(preset) => applyPreset(preset, true)"
                        />
                    </div>
                </aside>

                <!-- Canvas / preview -->
                <main class="min-h-0 flex-1 overflow-y-auto p-5">
                    <div v-if="!fullPages.includes(props.page)" class="mx-auto mb-4 max-w-4xl rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs text-amber-800 dark:border-amber-900/60 dark:bg-amber-900/20 dark:text-amber-200">
                        {{ $t('webinars.builder.sections_only_hint') }}
                    </div>

                    <BuilderCanvas
                        v-if="mode === 'canvas'"
                        :rows="definition.rows"
                        :selection="selection"
                        :layouts="options.layouts"
                        @select-block="selectBlock"
                        @select-row="selectRow"
                        @clear-selection="clearSelection"
                        @add-row="addRow"
                        @change-layout="changeLayout"
                        @move-row="moveRow"
                        @duplicate-row="duplicateRow"
                        @remove-row="removeRow"
                    />

                    <div v-else class="mx-auto" :class="device === 'mobile' ? 'max-w-[400px]' : 'max-w-5xl'">
                        <div class="mb-3 flex items-center justify-center gap-2">
                            <button
                                v-for="option in ['desktop', 'mobile']"
                                :key="option"
                                type="button"
                                @click="device = option"
                                class="rounded-md px-3 py-1 text-xs font-medium transition"
                                :class="device === option ? 'bg-gray-900 text-white dark:bg-gray-600' : 'text-gray-500 hover:bg-gray-200 dark:hover:bg-gray-700'"
                            >
                                {{ $t(`webinars.builder.${option}`) }}
                            </button>
                            <button type="button" @click="refreshPreview" class="rounded-md px-3 py-1 text-xs font-medium text-gray-500 hover:bg-gray-200 dark:hover:bg-gray-700">
                                {{ previewLoading ? $t('webinars.builder.refreshing') : $t('webinars.builder.refresh') }}
                            </button>
                        </div>

                        <iframe
                            :srcdoc="previewHtml"
                            class="h-[calc(100vh-16rem)] w-full rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700"
                            sandbox="allow-scripts"
                            title="preview"
                        ></iframe>
                    </div>
                </main>

                <!-- Inspector -->
                <aside class="w-80 shrink-0 overflow-y-auto border-l border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                    <PropertiesPanel
                        :block="selectedBlock"
                        :row="selection.type === 'row' ? selectedRow : null"
                        :options="options"
                        @remove-block="removeBlock"
                        @duplicate-block="duplicateBlock"
                        @remove-row="removeRow(selection.rowId)"
                        @duplicate-row="duplicateRow(selection.rowId)"
                    />
                </aside>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
