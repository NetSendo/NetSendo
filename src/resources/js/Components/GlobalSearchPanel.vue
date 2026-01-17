<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from "vue";
import { router } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["close"]);

const { t } = useI18n();

// Search state
const searchQuery = ref("");
const searchResults = ref({});
const isLoading = ref(false);
const selectedIndex = ref(0);
const activeCategory = ref("all");
const searchInputRef = ref(null);
const recentSearches = ref([]);

// Categories
const categories = [
    { key: "all", label: "global_search.categories.all", icon: "search" },
    { key: "contacts", label: "global_search.categories.contacts", icon: "user" },
    { key: "companies", label: "global_search.categories.companies", icon: "building" },
    { key: "tasks", label: "global_search.categories.tasks", icon: "check-square" },
    { key: "messages", label: "global_search.categories.messages", icon: "mail" },
    { key: "media", label: "global_search.categories.media", icon: "image" },
    { key: "subscribers", label: "global_search.categories.subscribers", icon: "users" },
    { key: "lists", label: "global_search.categories.lists", icon: "list" },
    { key: "webinars", label: "global_search.categories.webinars", icon: "video" },
];

// Flattened results for keyboard navigation
const flatResults = computed(() => {
    const results = [];
    for (const [category, items] of Object.entries(searchResults.value)) {
        if (items && items.length > 0) {
            for (const item of items) {
                results.push({ ...item, category });
            }
        }
    }
    return results;
});

// Total results count
const totalCount = computed(() => flatResults.value.length);

// Debounced search
let searchTimeout = null;
const performSearch = async () => {
    if (searchQuery.value.length < 2) {
        searchResults.value = {};
        return;
    }

    isLoading.value = true;
    try {
        const response = await fetch(
            route("api.search") +
                `?query=${encodeURIComponent(searchQuery.value)}&category=${activeCategory.value}`
        );
        const data = await response.json();
        searchResults.value = data.results;
        selectedIndex.value = 0;
    } catch (error) {
        console.error("Search error:", error);
        searchResults.value = {};
    } finally {
        isLoading.value = false;
    }
};

// Watch for changes
watch(searchQuery, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(performSearch, 300);
});

watch(activeCategory, () => {
    if (searchQuery.value.length >= 2) {
        performSearch();
    }
});

// Focus input when panel opens
watch(
    () => props.show,
    (show) => {
        if (show) {
            selectedIndex.value = 0;
            loadRecentSearches();
            nextTick(() => {
                searchInputRef.value?.focus();
            });
        } else {
            searchQuery.value = "";
            searchResults.value = {};
        }
    }
);

// Keyboard navigation
const handleKeydown = (event) => {
    if (!props.show) return;

    switch (event.key) {
        case "Escape":
            emit("close");
            break;
        case "ArrowDown":
            event.preventDefault();
            if (selectedIndex.value < flatResults.value.length - 1) {
                selectedIndex.value++;
            }
            break;
        case "ArrowUp":
            event.preventDefault();
            if (selectedIndex.value > 0) {
                selectedIndex.value--;
            }
            break;
        case "Enter":
            event.preventDefault();
            const selected = flatResults.value[selectedIndex.value];
            if (selected) {
                navigateToResult(selected);
            }
            break;
    }
};

// Navigate to result
const navigateToResult = (result) => {
    // Save to recent searches
    saveRecentSearch(searchQuery.value);

    emit("close");
    router.visit(result.url);
};

// Recent searches management
const loadRecentSearches = () => {
    const stored = localStorage.getItem("netsendo_recent_searches");
    recentSearches.value = stored ? JSON.parse(stored) : [];
};

const saveRecentSearch = (query) => {
    if (!query || query.length < 2) return;

    let searches = [...recentSearches.value];
    // Remove if already exists
    searches = searches.filter((s) => s !== query);
    // Add to beginning
    searches.unshift(query);
    // Keep only last 5
    searches = searches.slice(0, 5);

    localStorage.setItem("netsendo_recent_searches", JSON.stringify(searches));
    recentSearches.value = searches;
};

const applyRecentSearch = (query) => {
    searchQuery.value = query;
};

const clearRecentSearches = () => {
    localStorage.removeItem("netsendo_recent_searches");
    recentSearches.value = [];
};

// Get icon for result type
const getIcon = (type) => {
    const icons = {
        contact: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />`,
        company: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />`,
        task: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />`,
        message: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />`,
        media: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />`,
        subscriber: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />`,
        list: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />`,
        webinar: `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />`,
    };
    return icons[type] || icons.task;
};

// Get category label
const getCategoryLabel = (key) => {
    const cat = categories.find((c) => c.key === key);
    return cat ? t(cat.label, key) : key;
};

// Mount/unmount event listeners
onMounted(() => {
    document.addEventListener("keydown", handleKeydown);
    loadRecentSearches();
});

onUnmounted(() => {
    document.removeEventListener("keydown", handleKeydown);
    clearTimeout(searchTimeout);
});
</script>

<template>
    <!-- Backdrop -->
    <Transition
        enter-active-class="transition-opacity duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="show"
            class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm"
            @click="$emit('close')"
        />
    </Transition>

    <!-- Panel -->
    <Transition
        enter-active-class="transition-transform duration-300 ease-out"
        enter-from-class="translate-x-full"
        enter-to-class="translate-x-0"
        leave-active-class="transition-transform duration-200 ease-in"
        leave-from-class="translate-x-0"
        leave-to-class="translate-x-full"
    >
        <div
            v-if="show"
            class="fixed right-0 top-0 z-50 flex h-full w-full max-w-md flex-col border-l border-slate-200 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900"
        >
            <!-- Header -->
            <div class="border-b border-slate-200 p-4 dark:border-slate-700">
                <div class="flex items-center gap-3">
                    <div class="flex flex-1 items-center gap-2 rounded-xl bg-slate-100 px-4 py-3 dark:bg-slate-800">
                        <svg
                            class="h-5 w-5 text-slate-400"
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
                            ref="searchInputRef"
                            v-model="searchQuery"
                            type="text"
                            :placeholder="t('global_search.placeholder', 'Szukaj w NetSendo...')"
                            class="flex-1 border-0 bg-transparent text-sm text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-0 dark:text-slate-200 dark:placeholder-slate-500"
                        />
                        <div v-if="isLoading" class="flex items-center">
                            <svg
                                class="h-5 w-5 animate-spin text-indigo-500"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                />
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                />
                            </svg>
                        </div>
                    </div>
                    <button
                        @click="$emit('close')"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300"
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
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>

                <!-- Category filters -->
                <div class="mt-3 flex flex-wrap gap-2">
                    <button
                        v-for="category in categories"
                        :key="category.key"
                        @click="activeCategory = category.key"
                        :class="[
                            'rounded-lg px-3 py-1.5 text-xs font-medium transition-colors',
                            activeCategory === category.key
                                ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700',
                        ]"
                    >
                        {{ t(category.label, category.key) }}
                    </button>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 overflow-y-auto p-4">
                <!-- Recent searches (when no query) -->
                <div
                    v-if="!searchQuery && recentSearches.length > 0"
                    class="mb-6"
                >
                    <div class="mb-2 flex items-center justify-between">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                            {{ t('global_search.recent_searches', 'Ostatnie wyszukiwania') }}
                        </h3>
                        <button
                            @click="clearRecentSearches"
                            class="text-xs text-slate-400 hover:text-slate-600 dark:hover:text-slate-300"
                        >
                            {{ t('global_search.clear', 'Wyczyść') }}
                        </button>
                    </div>
                    <div class="space-y-1">
                        <button
                            v-for="recent in recentSearches"
                            :key="recent"
                            @click="applyRecentSearch(recent)"
                            class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-slate-600 transition-colors hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
                        >
                            <svg
                                class="h-4 w-4 text-slate-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                            {{ recent }}
                        </button>
                    </div>
                </div>

                <!-- Empty state -->
                <div
                    v-if="!searchQuery && recentSearches.length === 0"
                    class="flex flex-col items-center justify-center py-12 text-center"
                >
                    <svg
                        class="mb-4 h-12 w-12 text-slate-300 dark:text-slate-600"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="1.5"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                        />
                    </svg>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ t('global_search.hint', 'Wpisz co najmniej 2 znaki aby wyszukać') }}
                    </p>
                </div>

                <!-- No results -->
                <div
                    v-if="searchQuery.length >= 2 && !isLoading && totalCount === 0"
                    class="flex flex-col items-center justify-center py-12 text-center"
                >
                    <svg
                        class="mb-4 h-12 w-12 text-slate-300 dark:text-slate-600"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="1.5"
                            d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ t('global_search.no_results', 'Brak wyników dla') }} "{{ searchQuery }}"
                    </p>
                </div>

                <!-- Results grouped by category -->
                <div v-if="totalCount > 0" class="space-y-6">
                    <template v-for="(items, category) in searchResults" :key="category">
                        <div v-if="items && items.length > 0">
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                                {{ getCategoryLabel(category) }} ({{ items.length }})
                            </h3>
                            <div class="space-y-1">
                                <button
                                    v-for="(item, index) in items"
                                    :key="item.id"
                                    @click="navigateToResult(item)"
                                    @mouseenter="selectedIndex = flatResults.findIndex(r => r.id === item.id && r.category === category)"
                                    :class="[
                                        'flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left transition-colors',
                                        flatResults.findIndex(r => r.id === item.id && r.category === category) === selectedIndex
                                            ? 'bg-indigo-50 dark:bg-indigo-900/30'
                                            : 'hover:bg-slate-100 dark:hover:bg-slate-800',
                                    ]"
                                >
                                    <!-- Icon -->
                                    <div
                                        :class="[
                                            'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg',
                                            flatResults.findIndex(r => r.id === item.id && r.category === category) === selectedIndex
                                                ? 'bg-indigo-100 text-indigo-600 dark:bg-indigo-800 dark:text-indigo-300'
                                                : 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400',
                                        ]"
                                    >
                                        <svg
                                            class="h-5 w-5"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                            v-html="getIcon(item.type)"
                                        />
                                    </div>

                                    <!-- Content -->
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-medium text-slate-900 dark:text-white">
                                            {{ item.title }}
                                        </div>
                                        <div
                                            v-if="item.subtitle"
                                            class="truncate text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{ item.subtitle }}
                                        </div>
                                    </div>

                                    <!-- Status badge -->
                                    <div
                                        v-if="item.status"
                                        :class="[
                                            'shrink-0 rounded-full px-2 py-0.5 text-xs font-medium',
                                            item.status === 'completed' || item.status === 'active'
                                                ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                                : item.status === 'pending' || item.status === 'lead'
                                                  ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'
                                                  : 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-400',
                                        ]"
                                    >
                                        {{ item.status }}
                                    </div>

                                    <!-- Arrow -->
                                    <svg
                                        class="h-4 w-4 shrink-0 text-slate-400"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 5l7 7-7 7"
                                        />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Footer with keyboard hints -->
            <div class="border-t border-slate-200 bg-slate-50 px-4 py-3 dark:border-slate-700 dark:bg-slate-800/50">
                <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                    <div class="flex items-center gap-4">
                        <span class="flex items-center gap-1">
                            <kbd class="rounded bg-slate-200 px-1.5 py-0.5 font-mono dark:bg-slate-700">↑</kbd>
                            <kbd class="rounded bg-slate-200 px-1.5 py-0.5 font-mono dark:bg-slate-700">↓</kbd>
                            {{ t('global_search.navigate', 'nawiguj') }}
                        </span>
                        <span class="flex items-center gap-1">
                            <kbd class="rounded bg-slate-200 px-1.5 py-0.5 font-mono dark:bg-slate-700">Enter</kbd>
                            {{ t('global_search.open', 'otwórz') }}
                        </span>
                        <span class="flex items-center gap-1">
                            <kbd class="rounded bg-slate-200 px-1.5 py-0.5 font-mono dark:bg-slate-700">Esc</kbd>
                            {{ t('global_search.close', 'zamknij') }}
                        </span>
                    </div>
                    <span v-if="totalCount > 0">
                        {{ totalCount }} {{ t('global_search.results', 'wyników') }}
                    </span>
                </div>
            </div>
        </div>
    </Transition>
</template>
