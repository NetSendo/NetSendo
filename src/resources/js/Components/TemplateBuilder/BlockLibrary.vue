<script setup>
import { ref, computed } from "vue";
import { useI18n } from "vue-i18n";
import draggable from "vuedraggable";

const { t } = useI18n();

const props = defineProps({
    blockTypes: Object,
    blockCategories: Object,
    savedBlocks: Array,
    aiAvailable: Boolean,
});

const emit = defineEmits(["add-block", "show-ai"]);

const searchQuery = ref("");
const activeCategory = ref("all");

// Block icons SVG paths
const blockIcons = {
    header: "M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5z",
    text: "M4 6h16M4 12h16m-7 6h7",
    image: "M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z",
    button: "M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122",
    divider: "M5 12h14",
    spacer: "M8 7h8M8 17h8",
    columns: "M9 4.5v15m6-15v15M4 4h16v16H4z",
    product: "M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z",
    product_grid:
        "M4 5a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM14 5a1 1 0 011-1h4a1 1 0 011 1v6a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h6a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4z",
    social: "M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z",
    footer: "M4 16.5v3h16v-3M4 5v2h16V5",
};

// All blocks as flat array for draggable
const allBlocks = computed(() => {
    const blocks = [];
    for (const [type, info] of Object.entries(props.blockTypes || {})) {
        blocks.push({ type, ...info, id: `library-${type}` });
    }
    return blocks;
});

// Group blocks by category
const groupedBlocks = computed(() => {
    const groups = {};
    for (const [type, info] of Object.entries(props.blockTypes || {})) {
        const category = info.category || "content";
        if (!groups[category]) {
            groups[category] = [];
        }
        groups[category].push({ type, ...info, id: `library-${type}` });
    }
    return groups;
});

// Filtered blocks by search
const filteredBlocks = computed(() => {
    if (!searchQuery.value) {
        if (activeCategory.value === "all") {
            return groupedBlocks.value;
        }
        return {
            [activeCategory.value]:
                groupedBlocks.value[activeCategory.value] || [],
        };
    }

    const query = searchQuery.value.toLowerCase();
    const result = {};

    for (const [category, blocks] of Object.entries(groupedBlocks.value)) {
        const filtered = blocks.filter(
            (b) =>
                b.label.toLowerCase().includes(query) ||
                b.type.toLowerCase().includes(query)
        );
        if (filtered.length > 0) {
            result[category] = filtered;
        }
    }

    return result;
});

// Clone function for draggable - creates new block when dragged
const cloneBlock = (block) => {
    return {
        id: `block-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`,
        type: block.type,
        content: getDefaultContent(block.type),
        settings: {},
    };
};

// Get default content for block type
const getDefaultContent = (type) => {
    const defaults = {
        header: {
            logo: null,
            logoWidth: 150,
            backgroundColor: "#6366f1",
            padding: "20px",
            alignment: "center",
        },
        text: {
            html: "<p>" + t("template_builder.default_text") + "</p>",
            alignment: "left",
        },
        image: { src: null, alt: "", href: "", alignment: "center" },
        button: {
            text: t("template_builder.default_button"),
            href: "#",
            backgroundColor: "#6366f1",
            textColor: "#ffffff",
            borderRadius: "8px",
            alignment: "center",
        },
        divider: { color: "#e2e8f0" },
        spacer: { height: "30px" },
        columns: { columns: 2, gap: "20px" },
        product: {
            title: "",
            description: "",
            price: t("template_builder.default_price"),
            oldPrice: "",
            image: null,
            buttonText: t("template_builder.buy_now"),
            buttonUrl: "#",
        },
        product_grid: { products: [{}, {}, {}, {}], columns: 2 },
        social: { icons: [] },
        footer: {
            companyName: "",
            address: "",
            unsubscribeText: t("template_builder.unsubscribe"),
            backgroundColor: "#1e293b",
            textColor: "#94a3b8",
        },
    };
    return defaults[type] || {};
};

const handleAddBlock = (type) => {
    emit("add-block", type);
};
</script>

<template>
    <div
        class="flex h-full flex-col border-r border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
    >
        <!-- Header -->
        <div
            class="shrink-0 border-b border-slate-200 p-4 dark:border-slate-800"
        >
            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">
                {{ $t("template_builder.blocks_library") }}
            </h3>

            <!-- Search -->
            <div class="relative mt-3">
                <svg
                    class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                    />
                </svg>
                <input
                    v-model="searchQuery"
                    type="text"
                    :placeholder="$t('common.search')"
                    class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2 pl-9 pr-3 text-sm text-slate-900 placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                />
            </div>

            <!-- Category tabs -->
            <div class="mt-3 flex gap-1 overflow-x-auto">
                <button
                    @click="activeCategory = 'all'"
                    :class="
                        activeCategory === 'all'
                            ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300'
                            : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                    "
                    class="shrink-0 rounded-lg px-3 py-1.5 text-xs font-medium transition-colors"
                >
                    {{ $t("common.all") }}
                </button>
                <button
                    v-for="(label, key) in blockCategories"
                    :key="key"
                    @click="activeCategory = key"
                    :class="
                        activeCategory === key
                            ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300'
                            : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                    "
                    class="shrink-0 rounded-lg px-3 py-1.5 text-xs font-medium transition-colors"
                >
                    {{ label }}
                </button>
            </div>

            <!-- Drag instruction -->
            <p class="mt-3 text-xs text-slate-400 dark:text-slate-500">
                <svg
                    class="mr-1 inline h-3 w-3"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"
                    />
                </svg>
                {{ $t("template_builder.drag_instruction") }}
            </p>
        </div>

        <!-- Blocks list with draggable -->
        <div class="flex-1 min-h-0 overflow-y-auto p-4">
            <div
                v-for="(blocks, category) in filteredBlocks"
                :key="category"
                class="mb-6 last:mb-0"
            >
                <h4
                    class="mb-2 text-xs font-medium uppercase tracking-wider text-slate-400"
                >
                    {{ blockCategories[category] || category }}
                </h4>

                <draggable
                    :list="blocks"
                    :group="{ name: 'blocks', pull: 'clone', put: false }"
                    :clone="cloneBlock"
                    :sort="false"
                    item-key="type"
                    class="grid grid-cols-2 gap-2"
                >
                    <template #item="{ element: block }">
                        <div
                            @click="handleAddBlock(block.type)"
                            class="library-block group flex cursor-grab flex-col items-center gap-2 rounded-lg border border-slate-200 bg-white p-3 text-center transition-all hover:border-indigo-300 hover:bg-indigo-50 hover:shadow-sm active:cursor-grabbing dark:border-slate-700 dark:bg-slate-800 dark:hover:border-indigo-600 dark:hover:bg-indigo-900/20"
                        >
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-500 transition-colors group-hover:bg-indigo-100 group-hover:text-indigo-600 dark:bg-slate-700 dark:text-slate-400 dark:group-hover:bg-indigo-900/50 dark:group-hover:text-indigo-400"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        :d="
                                            blockIcons[block.type] ||
                                            'M4 6h16M4 12h16m-7 6h7'
                                        "
                                    />
                                </svg>
                            </div>
                            <span
                                class="text-xs font-medium text-slate-600 group-hover:text-indigo-700 dark:text-slate-300 dark:group-hover:text-indigo-300"
                            >
                                {{ block.label }}
                            </span>
                        </div>
                    </template>
                </draggable>
            </div>

            <!-- Empty state -->
            <div
                v-if="Object.keys(filteredBlocks).length === 0"
                class="py-8 text-center text-slate-400"
            >
                <p class="text-sm">
                    {{ $t("template_builder.no_blocks_found") }}
                </p>
            </div>
        </div>

        <!-- AI Generator button -->
        <div
            v-if="aiAvailable"
            class="shrink-0 border-t border-slate-200 p-4 dark:border-slate-800"
        >
            <button
                @click="$emit('show-ai')"
                class="flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-indigo-500 to-purple-600 px-4 py-2.5 text-sm font-medium text-white shadow-lg shadow-indigo-500/30 transition-all hover:from-indigo-600 hover:to-purple-700 hover:shadow-indigo-500/40"
            >
                <svg
                    class="h-5 w-5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M13 10V3L4 14h7v7l9-11h-7z"
                    />
                </svg>
                {{ $t("template_builder.ai_generate") }}
            </button>
        </div>

        <!-- Saved blocks section -->
        <div
            v-if="savedBlocks?.length > 0"
            class="shrink-0 border-t border-slate-200 p-4 dark:border-slate-800"
        >
            <button
                class="flex w-full items-center justify-between text-sm text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
            >
                <span class="font-medium">{{
                    $t("template_builder.saved_blocks")
                }}</span>
                <span class="text-xs text-slate-400">{{
                    savedBlocks.length
                }}</span>
            </button>
        </div>
    </div>
</template>

<style scoped>
.library-block {
    user-select: none;
}
</style>
