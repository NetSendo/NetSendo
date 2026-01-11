<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';

const { t } = useI18n();

const props = defineProps({
    show: Boolean,
    multiSelect: {
        type: Boolean,
        default: false,
    },
    selectedProducts: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close', 'select']);

// State
const activeTab = ref('woocommerce');
const searchQuery = ref('');
const loading = ref(false);
const products = ref([]);
const recentlyViewedProducts = ref([]);
const connectionStatus = ref(null);
const error = ref(null);
const page = ref(1);

// Pagination metadata
const totalProducts = ref(0);
const totalPages = ref(0);
const hasMore = ref(false);

// Categories
const categories = ref([]);
const selectedCategory = ref(null);
const loadingCategories = ref(false);

// Multi-store support
const stores = ref([]);
const selectedStore = ref(null);

// Selected products for multi-select
const selected = ref([...props.selectedProducts]);

// Check connection status on mount
onMounted(async () => {
    await checkConnection();
    if (connectionStatus.value?.connected) {
        // Set default store
        if (stores.value.length > 0) {
            selectedStore.value = stores.value.find(s => s.is_default)?.id || stores.value[0].id;
        }
        await Promise.all([loadProducts(), loadCategories()]);
    }
    await loadRecentlyViewed();
});

// Check WooCommerce connection
const checkConnection = async () => {
    try {
        const response = await axios.get(route('api.templates.products.connection-status'));
        connectionStatus.value = response.data;
        stores.value = response.data.stores || [];
        // Set default store if available
        if (stores.value.length > 0) {
            selectedStore.value = stores.value.find(s => s.is_default)?.id || stores.value[0].id;
        }
    } catch (e) {
        connectionStatus.value = { connected: false };
        stores.value = [];
    }
};

// Load WooCommerce categories
const loadCategories = async () => {
    if (!connectionStatus.value?.connected) return;

    loadingCategories.value = true;
    try {
        const response = await axios.get(route('api.templates.products.woocommerce.categories'), {
            params: {
                store_id: selectedStore.value || undefined,
            },
        });
        if (response.data.success) {
            categories.value = response.data.categories;
        }
    } catch (e) {
        console.error('Failed to load categories', e);
    } finally {
        loadingCategories.value = false;
    }
};

// Load WooCommerce products
const loadProducts = async () => {
    if (!connectionStatus.value?.connected) return;

    loading.value = true;
    error.value = null;

    try {
        const response = await axios.get(route('api.templates.products.woocommerce'), {
            params: {
                page: page.value,
                per_page: 20,
                search: searchQuery.value || undefined,
                category: selectedCategory.value || undefined,
                store_id: selectedStore.value || undefined,
            },
        });

        if (response.data.success) {
            products.value = response.data.products;
            totalProducts.value = response.data.total || 0;
            totalPages.value = response.data.total_pages || 0;
            hasMore.value = response.data.has_more || false;
        } else {
            error.value = response.data.message;
        }
    } catch (e) {
        error.value = e.response?.data?.message || e.message;
    } finally {
        loading.value = false;
    }
};

// Load recently viewed products from Pixel data
const loadRecentlyViewed = async () => {
    try {
        const response = await axios.get(route('api.templates.products.recently-viewed'), {
            params: { limit: 20 },
        });

        if (response.data.success) {
            recentlyViewedProducts.value = response.data.products;
        }
    } catch (e) {
        console.error('Failed to load recently viewed products', e);
    }
};

// Search debounce
let searchTimeout;
watch(searchQuery, (val) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        page.value = 1;
        loadProducts();
    }, 300);
});

// Category change
watch(selectedCategory, () => {
    page.value = 1;
    loadProducts();
});

// Store change - reload products and categories
watch(selectedStore, () => {
    page.value = 1;
    selectedCategory.value = null;
    loadProducts();
    loadCategories();
});

// Tab change
watch(activeTab, () => {
    searchQuery.value = '';
    selectedCategory.value = null;
    page.value = 1;
});

// Pagination
const goToPage = (newPage) => {
    if (newPage >= 1 && newPage <= totalPages.value) {
        page.value = newPage;
        loadProducts();
    }
};

const previousPage = () => {
    if (page.value > 1) {
        goToPage(page.value - 1);
    }
};

const nextPage = () => {
    if (hasMore.value) {
        goToPage(page.value + 1);
    }
};

// Current list based on tab
const currentProducts = computed(() => {
    return activeTab.value === 'woocommerce' ? products.value : recentlyViewedProducts.value;
});

// Is product selected
const isSelected = (product) => {
    return selected.value.some(p => p.id === product.id);
};

// Toggle product selection
const toggleProduct = (product) => {
    // Enrich product with store info for multi-store tracking
    const getEnrichedProduct = (prod) => ({
        ...prod,
        store_id: selectedStore.value || null,
        store_name: stores.value.find(s => s.id === selectedStore.value)?.display_name || null,
    });

    if (props.multiSelect) {
        const index = selected.value.findIndex(p => p.id === product.id && p.store_id === selectedStore.value);
        if (index >= 0) {
            selected.value.splice(index, 1);
        } else {
            selected.value.push(getEnrichedProduct(product));
        }
    } else {
        emit('select', [getEnrichedProduct(product)]);
        emit('close');
    }
};

// Confirm multi-select
const confirmSelection = () => {
    emit('select', selected.value);
    emit('close');
};

// Format price
const formatPrice = (price, currency = 'PLN') => {
    if (!price) return '';
    return new Intl.NumberFormat('pl-PL', {
        style: 'currency',
        currency: currency,
    }).format(price);
};
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4">
                <div class="relative w-full max-w-4xl max-h-[90vh] overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-800">
                    <!-- Header -->
                    <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-slate-700">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                                {{ $t('template_builder.select_product') }}
                            </h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400">
                                {{ multiSelect ? $t('template_builder.select_multiple_products') : $t('template_builder.select_one_product') }}
                            </p>
                        </div>
                        <button
                            @click="$emit('close')"
                            class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Tabs -->
                    <div class="border-b border-slate-200 px-6 dark:border-slate-700">
                        <nav class="flex gap-6">
                            <button
                                @click="activeTab = 'woocommerce'"
                                :class="[
                                    'relative py-3 text-sm font-medium transition-colors',
                                    activeTab === 'woocommerce'
                                        ? 'text-purple-600 dark:text-purple-400'
                                        : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'
                                ]"
                            >
                                {{ $t('template_builder.woocommerce_products') }}
                                <span
                                    v-if="activeTab === 'woocommerce'"
                                    class="absolute bottom-0 left-0 right-0 h-0.5 bg-purple-600 dark:bg-purple-400"
                                ></span>
                            </button>
                            <button
                                @click="activeTab = 'recently_viewed'"
                                :class="[
                                    'relative py-3 text-sm font-medium transition-colors',
                                    activeTab === 'recently_viewed'
                                        ? 'text-purple-600 dark:text-purple-400'
                                        : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'
                                ]"
                            >
                                {{ $t('template_builder.recently_viewed') }}
                                <span v-if="recentlyViewedProducts.length" class="ml-1 text-xs text-slate-400">({{ recentlyViewedProducts.length }})</span>
                                <span
                                    v-if="activeTab === 'recently_viewed'"
                                    class="absolute bottom-0 left-0 right-0 h-0.5 bg-purple-600 dark:bg-purple-400"
                                ></span>
                            </button>
                        </nav>
                    </div>

                    <!-- Content -->
                    <div class="p-6" style="max-height: calc(90vh - 200px); overflow-y: auto;">
                        <!-- WooCommerce Tab -->
                        <div v-if="activeTab === 'woocommerce'">
                            <!-- Not connected message -->
                            <div v-if="!connectionStatus?.connected" class="text-center py-12">
                                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30">
                                    <svg class="h-8 w-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <h4 class="text-lg font-semibold text-slate-900 dark:text-white">
                                    {{ $t('template_builder.woocommerce_not_connected') }}
                                </h4>
                                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                    {{ $t('template_builder.connect_woocommerce_hint') }}
                                </p>
                                <a
                                    :href="route('settings.woocommerce.index')"
                                    class="mt-4 inline-flex items-center gap-2 rounded-xl bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-500"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                    </svg>
                                    {{ $t('template_builder.connect_woocommerce') }}
                                </a>
                            </div>

                            <!-- Connected - show products -->
                            <template v-else>
                                <!-- Store Selector (when multiple stores) -->
                                <div v-if="stores.length > 1" class="mb-4">
                                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                                        {{ $t('template_builder.select_store') }}
                                    </label>
                                    <select
                                        v-model="selectedStore"
                                        class="w-full sm:w-64 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                                    >
                                        <option v-for="store in stores" :key="store.id" :value="store.id">
                                            {{ store.display_name || store.name }}
                                            <span v-if="store.is_default"> ({{ $t('settings.woocommerce.default_store') }})</span>
                                        </option>
                                    </select>
                                </div>

                                <!-- Search and Filters Row -->
                                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                                    <!-- Search -->
                                    <div class="relative flex-1">
                                        <svg class="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                        <input
                                            v-model="searchQuery"
                                            type="text"
                                            :placeholder="$t('template_builder.search_products')"
                                            class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-10 pr-4 text-sm text-slate-900 placeholder-slate-400 focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                                        />
                                    </div>

                                    <!-- Category Filter -->
                                    <div class="sm:w-48">
                                        <select
                                            v-model="selectedCategory"
                                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 focus:border-purple-500 focus:outline-none focus:ring-1 focus:ring-purple-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                                        >
                                            <option :value="null">{{ $t('template_builder.all_categories') }}</option>
                                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                                                {{ cat.name }} ({{ cat.count }})
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Product Count -->
                                <div v-if="!loading && totalProducts > 0" class="mb-4 text-sm text-slate-500 dark:text-slate-400">
                                    {{ $t('template_builder.products_found', { count: totalProducts }) }}
                                </div>

                                <!-- Loading -->
                                <div v-if="loading" class="flex items-center justify-center py-12">
                                    <svg class="h-8 w-8 animate-spin text-purple-600" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                    </svg>
                                </div>

                                <!-- Error -->
                                <div v-else-if="error" class="rounded-xl bg-red-50 p-4 text-center text-red-700 dark:bg-red-900/20 dark:text-red-400">
                                    {{ error }}
                                </div>

                                <!-- Products grid -->
                                <div v-else-if="currentProducts.length > 0" class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                                    <div
                                        v-for="product in currentProducts"
                                        :key="product.id"
                                        @click="toggleProduct(product)"
                                        :class="[
                                            'group cursor-pointer rounded-xl border-2 p-3 transition-all',
                                            isSelected(product)
                                                ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/20'
                                                : 'border-slate-200 bg-white hover:border-purple-300 hover:shadow-md dark:border-slate-700 dark:bg-slate-800'
                                        ]"
                                    >
                                        <!-- Image -->
                                        <div class="relative mb-3 aspect-square overflow-hidden rounded-lg bg-slate-100 dark:bg-slate-700">
                                            <img
                                                v-if="product.image"
                                                :src="product.image"
                                                :alt="product.name"
                                                class="h-full w-full object-cover"
                                            />
                                            <div v-else class="flex h-full items-center justify-center text-slate-400">
                                                <svg class="h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            </div>

                                            <!-- Selection indicator -->
                                            <div
                                                v-if="isSelected(product)"
                                                class="absolute right-2 top-2 flex h-6 w-6 items-center justify-center rounded-full bg-purple-600 text-white"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                            </div>
                                        </div>

                                        <!-- Info -->
                                        <h4 class="text-sm font-medium text-slate-900 dark:text-white line-clamp-2">
                                            {{ product.name }}
                                        </h4>
                                        <div class="mt-1 flex items-baseline gap-2">
                                            <span class="text-sm font-semibold text-purple-600 dark:text-purple-400">
                                                {{ formatPrice(product.price, product.currency) }}
                                            </span>
                                            <span v-if="product.sale_price && product.regular_price > product.sale_price" class="text-xs text-slate-400 line-through">
                                                {{ formatPrice(product.regular_price, product.currency) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- No products -->
                                <div v-else class="py-12 text-center text-slate-500 dark:text-slate-400">
                                    {{ $t('template_builder.no_products_found') }}
                                </div>

                                <!-- Pagination -->
                                <div v-if="!loading && totalPages > 1" class="mt-6 flex items-center justify-center gap-4">
                                    <button
                                        @click="previousPage"
                                        :disabled="page <= 1"
                                        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                        </svg>
                                        {{ $t('template_builder.previous_page') }}
                                    </button>

                                    <span class="text-sm text-slate-500 dark:text-slate-400">
                                        {{ $t('template_builder.page_of', { current: page, total: totalPages }) }}
                                    </span>

                                    <button
                                        @click="nextPage"
                                        :disabled="!hasMore"
                                        class="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:bg-slate-700"
                                    >
                                        {{ $t('template_builder.next_page') }}
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </button>
                                </div>
                            </template>
                        </div>

                        <!-- Recently Viewed Tab -->
                        <div v-else-if="activeTab === 'recently_viewed'">
                            <div v-if="recentlyViewedProducts.length === 0" class="py-12 text-center">
                                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-700">
                                    <svg class="h-8 w-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </div>
                                <h4 class="text-lg font-semibold text-slate-900 dark:text-white">
                                    {{ $t('template_builder.no_recently_viewed') }}
                                </h4>
                                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                    {{ $t('template_builder.recently_viewed_hint') }}
                                </p>
                            </div>

                            <!-- Products grid -->
                            <div v-else class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                                <div
                                    v-for="product in recentlyViewedProducts"
                                    :key="product.id"
                                    @click="toggleProduct(product)"
                                    :class="[
                                        'group cursor-pointer rounded-xl border-2 p-3 transition-all',
                                        isSelected(product)
                                            ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/20'
                                            : 'border-slate-200 bg-white hover:border-purple-300 hover:shadow-md dark:border-slate-700 dark:bg-slate-800'
                                    ]"
                                >
                                    <!-- Image -->
                                    <div class="relative mb-3 aspect-square overflow-hidden rounded-lg bg-slate-100 dark:bg-slate-700">
                                        <img
                                            v-if="product.image"
                                            :src="product.image"
                                            :alt="product.name"
                                            class="h-full w-full object-cover"
                                        />
                                        <div v-else class="flex h-full items-center justify-center text-slate-400">
                                            <svg class="h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                        </div>

                                        <!-- Pixel badge -->
                                        <div v-if="product.from_pixel" class="absolute left-2 top-2 rounded-full bg-amber-500 px-2 py-0.5 text-xs font-medium text-white">
                                            Pixel
                                        </div>

                                        <!-- Selection indicator -->
                                        <div
                                            v-if="isSelected(product)"
                                            class="absolute right-2 top-2 flex h-6 w-6 items-center justify-center rounded-full bg-purple-600 text-white"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </div>
                                    </div>

                                    <!-- Info -->
                                    <h4 class="text-sm font-medium text-slate-900 dark:text-white line-clamp-2">
                                        {{ product.name }}
                                    </h4>
                                    <div class="mt-1 flex items-center justify-between">
                                        <span v-if="product.price" class="text-sm font-semibold text-purple-600 dark:text-purple-400">
                                            {{ formatPrice(product.price, product.currency) }}
                                        </span>
                                        <span v-if="product.view_count" class="text-xs text-slate-400">
                                            {{ product.view_count }} {{ $t('template_builder.views') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div v-if="multiSelect && selected.length > 0" class="border-t border-slate-200 px-6 py-4 dark:border-slate-700">
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-slate-600 dark:text-slate-400">
                                {{ $t('template_builder.selected_count', { count: selected.length }) }}
                            </span>
                            <button
                                @click="confirmSelection"
                                class="rounded-xl bg-purple-600 px-5 py-2 text-sm font-medium text-white hover:bg-purple-500"
                            >
                                {{ $t('template_builder.confirm_selection') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
