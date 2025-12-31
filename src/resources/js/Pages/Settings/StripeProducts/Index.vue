<script setup>
import { ref, computed } from "vue";
import { Head, useForm, router, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";
import DangerButton from "@/Components/DangerButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import SalesFunnelTab from "@/Components/SalesFunnelTab.vue";
import { useI18n } from "vue-i18n";

const { t, locale } = useI18n();
const page = usePage();

const props = defineProps({
    products: Array,
    isConfigured: Boolean,
});

// Modal states
const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const showTransactionsModal = ref(false);
const selectedProduct = ref(null);
const transactions = ref([]);
const transactionsLoading = ref(false);
const checkoutUrl = ref(null);
const copySuccess = ref(false);
const activeTab = ref('products');

// Forms
const createForm = useForm({
    name: "",
    description: "",
    price: "",
    currency: "PLN",
    type: "one_time",
});

const editForm = useForm({
    name: "",
    description: "",
    price: "",
    currency: "PLN",
    is_active: true,
});

// Currency options
const currencies = [
    { code: "PLN", symbol: "zł" },
    { code: "EUR", symbol: "€" },
    { code: "USD", symbol: "$" },
    { code: "GBP", symbol: "£" },
];

// Helpers
function formatPrice(amount, currency) {
    const value = amount / 100;
    return new Intl.NumberFormat(locale.value, {
        style: "currency",
        currency: currency,
    }).format(value);
}

function formatDateTime(dateString) {
    if (!dateString) return "-";
    return new Date(dateString).toLocaleString(locale.value);
}

// Modal Actions
function openCreateModal() {
    createForm.reset();
    createForm.currency = "PLN";
    createForm.type = "one_time";
    showCreateModal.value = true;
}

function closeCreateModal() {
    showCreateModal.value = false;
}

function createProduct() {
    // Convert price to cents
    const priceInCents = Math.round(parseFloat(createForm.price) * 100);

    router.post(route("settings.stripe-products.store"), {
        name: createForm.name,
        description: createForm.description,
        price: priceInCents,
        currency: createForm.currency,
        type: createForm.type,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            closeCreateModal();
        },
    });
}

function openEditModal(product) {
    selectedProduct.value = product;
    editForm.name = product.name;
    editForm.description = product.description || "";
    editForm.price = (product.price / 100).toFixed(2);
    editForm.currency = product.currency;
    editForm.is_active = product.is_active;
    showEditModal.value = true;
}

function closeEditModal() {
    showEditModal.value = false;
    selectedProduct.value = null;
}

function updateProduct() {
    const priceInCents = Math.round(parseFloat(editForm.price) * 100);

    router.put(route("settings.stripe-products.update", selectedProduct.value.id), {
        name: editForm.name,
        description: editForm.description,
        price: priceInCents,
        currency: editForm.currency,
        is_active: editForm.is_active,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            closeEditModal();
        },
    });
}

function confirmDelete(product) {
    selectedProduct.value = product;
    showDeleteModal.value = true;
}

function closeDeleteModal() {
    showDeleteModal.value = false;
    selectedProduct.value = null;
}

function deleteProduct() {
    router.delete(route("settings.stripe-products.destroy", selectedProduct.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeDeleteModal();
        },
    });
}

async function showTransactions(product) {
    selectedProduct.value = product;
    transactionsLoading.value = true;
    showTransactionsModal.value = true;

    try {
        const response = await fetch(route("settings.stripe-products.transactions", product.id));
        const data = await response.json();
        transactions.value = data.transactions;
    } catch (e) {
        console.error("Failed to load transactions", e);
    } finally {
        transactionsLoading.value = false;
    }
}

function closeTransactionsModal() {
    showTransactionsModal.value = false;
    selectedProduct.value = null;
    transactions.value = [];
}

async function getCheckoutUrl(product) {
    try {
        const response = await fetch(route("settings.stripe-products.checkout-url", product.id), {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.getAttribute("content"),
                "Content-Type": "application/json",
            },
        });
        const data = await response.json();
        if (data.url) {
            await navigator.clipboard.writeText(data.url);
            copySuccess.value = true;
            setTimeout(() => copySuccess.value = false, 2000);
        }
    } catch (e) {
        console.error("Failed to get checkout URL", e);
    }
}
</script>

<template>
    <Head :title="t('stripe.products')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">
                    💳 {{ t("stripe.products") }}
                </h2>
                <button
                    v-if="isConfigured"
                    @click="openCreateModal"
                    class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-500 transition-colors flex items-center gap-2"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ t("stripe.add_product") }}
                </button>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Configuration Warning -->
                <div
                    v-if="!isConfigured"
                    class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4"
                >
                    <div class="flex items-start gap-3">
                        <svg class="h-5 w-5 text-amber-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-amber-800 dark:text-amber-200">
                                {{ t("stripe.not_configured") }}
                            </p>
                            <p class="text-sm text-amber-600 dark:text-amber-300 mt-1">
                                {{ t("stripe.configure_hint") }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <div class="border-b border-gray-200 dark:border-gray-700">
                    <nav class="-mb-px flex space-x-8">
                        <button
                            @click="activeTab = 'products'"
                            :class="[
                                'py-3 px-1 border-b-2 font-medium text-sm transition-colors',
                                activeTab === 'products'
                                    ? 'border-purple-500 text-purple-600 dark:text-purple-400'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
                            ]"
                        >
                            📦 {{ t('sales_funnels.tabs.products') }}
                        </button>
                        <button
                            @click="activeTab = 'funnels'"
                            :class="[
                                'py-3 px-1 border-b-2 font-medium text-sm transition-colors',
                                activeTab === 'funnels'
                                    ? 'border-purple-500 text-purple-600 dark:text-purple-400'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
                            ]"
                        >
                            🚀 {{ t('sales_funnels.tabs.funnels') }}
                        </button>
                    </nav>
                </div>

                <!-- Products Tab Content -->
                <div v-if="activeTab === 'products'">
                    <!-- Products List -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                            {{ t("stripe.products_list") }}
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            {{ t("stripe.products_description") }}
                        </p>
                    </div>

                    <div v-if="products.length === 0" class="px-6 py-12 text-center">
                        <svg class="w-12 h-12 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                        <p class="mt-4 text-gray-500 dark:text-gray-400">
                            {{ t("stripe.no_products") }}
                        </p>
                        <button
                            v-if="isConfigured"
                            @click="openCreateModal"
                            class="mt-4 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-500 transition-colors"
                        >
                            {{ t("stripe.add_first_product") }}
                        </button>
                    </div>

                    <table v-else class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ t("stripe.product_name") }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ t("stripe.price") }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ t("stripe.type") }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ t("stripe.sales") }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ t("stripe.revenue") }}
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ t("common.actions") }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr
                                v-for="product in products"
                                :key="product.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                            >
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <span class="font-medium text-gray-900 dark:text-white">{{ product.name }}</span>
                                        <span
                                            v-if="!product.is_active"
                                            class="ml-2 px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400"
                                        >
                                            {{ t("stripe.inactive") }}
                                        </span>
                                    </div>
                                    <p v-if="product.description" class="text-sm text-gray-500 dark:text-gray-400 mt-1 truncate max-w-xs">
                                        {{ product.description }}
                                    </p>
                                </td>
                                <td class="px-6 py-4 text-gray-900 dark:text-white font-medium">
                                    {{ product.formatted_price }}
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        :class="[
                                            'px-2 py-0.5 text-xs rounded-full',
                                            product.type === 'subscription'
                                                ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                                                : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                        ]"
                                    >
                                        {{ product.type === 'subscription' ? t('stripe.subscription') : t('stripe.one_time') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-gray-900 dark:text-white">
                                    {{ product.sales_count }}
                                </td>
                                <td class="px-6 py-4 text-gray-900 dark:text-white font-medium">
                                    {{ formatPrice(product.total_revenue, product.currency) }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- Copy Checkout URL -->
                                        <button
                                            @click="getCheckoutUrl(product)"
                                            class="p-2 text-gray-500 hover:text-purple-600 dark:text-gray-400 dark:hover:text-purple-400"
                                            :title="t('stripe.copy_checkout_url')"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                            </svg>
                                        </button>
                                        <!-- View Transactions -->
                                        <button
                                            @click="showTransactions(product)"
                                            class="p-2 text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-400"
                                            :title="t('stripe.view_transactions')"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                            </svg>
                                        </button>
                                        <!-- Edit -->
                                        <button
                                            @click="openEditModal(product)"
                                            class="p-2 text-gray-500 hover:text-green-600 dark:text-gray-400 dark:hover:text-green-400"
                                            :title="t('common.edit')"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <!-- Delete -->
                                        <button
                                            @click="confirmDelete(product)"
                                            class="p-2 text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400"
                                            :title="t('common.delete')"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Copy Success Toast -->
                <div
                    v-if="copySuccess"
                    class="fixed bottom-4 right-4 bg-green-600 text-white px-4 py-2 rounded-lg shadow-lg flex items-center gap-2"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ t("stripe.url_copied") }}
                </div>
                </div>

                <!-- Sales Funnels Tab Content -->
                <div v-if="activeTab === 'funnels'">
                    <SalesFunnelTab
                        product-type="stripe"
                        :products="products"
                    />
                </div>
            </div>
        </div>

        <!-- Create Product Modal -->
        <Modal :show="showCreateModal" @close="closeCreateModal">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    💳 {{ t("stripe.add_product") }}
                </h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ t("stripe.product_name") }} *
                        </label>
                        <input
                            v-model="createForm.name"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-white"
                            required
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ t("stripe.description") }}
                        </label>
                        <textarea
                            v-model="createForm.description"
                            rows="2"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-white"
                        ></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t("stripe.price") }} *
                            </label>
                            <input
                                v-model="createForm.price"
                                type="number"
                                step="0.01"
                                min="0.01"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-white"
                                required
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t("stripe.currency") }}
                            </label>
                            <select
                                v-model="createForm.currency"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-white"
                            >
                                <option v-for="c in currencies" :key="c.code" :value="c.code">
                                    {{ c.code }} ({{ c.symbol }})
                                </option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ t("stripe.type") }}
                        </label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    v-model="createForm.type"
                                    type="radio"
                                    value="one_time"
                                    class="w-4 h-4 text-purple-600"
                                />
                                <span class="text-gray-700 dark:text-gray-300">{{ t("stripe.one_time") }}</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    v-model="createForm.type"
                                    type="radio"
                                    value="subscription"
                                    class="w-4 h-4 text-purple-600"
                                />
                                <span class="text-gray-700 dark:text-gray-300">{{ t("stripe.subscription") }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeCreateModal">
                        {{ t("common.cancel") }}
                    </SecondaryButton>
                    <button
                        @click="createProduct"
                        :disabled="!createForm.name || !createForm.price"
                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-500 disabled:opacity-50 transition-colors"
                    >
                        {{ t("stripe.create") }}
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Edit Product Modal -->
        <Modal :show="showEditModal" @close="closeEditModal">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    ✏️ {{ t("stripe.edit_product") }}
                </h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ t("stripe.product_name") }} *
                        </label>
                        <input
                            v-model="editForm.name"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-white"
                            required
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ t("stripe.description") }}
                        </label>
                        <textarea
                            v-model="editForm.description"
                            rows="2"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-white"
                        ></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t("stripe.price") }} *
                            </label>
                            <input
                                v-model="editForm.price"
                                type="number"
                                step="0.01"
                                min="0.01"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-white"
                                required
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t("stripe.currency") }}
                            </label>
                            <select
                                v-model="editForm.currency"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-purple-500 focus:border-purple-500 dark:bg-gray-700 dark:text-white"
                            >
                                <option v-for="c in currencies" :key="c.code" :value="c.code">
                                    {{ c.code }} ({{ c.symbol }})
                                </option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="editForm.is_active"
                                type="checkbox"
                                class="w-4 h-4 text-purple-600 rounded"
                            />
                            <span class="text-gray-700 dark:text-gray-300">{{ t("stripe.is_active") }}</span>
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeEditModal">
                        {{ t("common.cancel") }}
                    </SecondaryButton>
                    <button
                        @click="updateProduct"
                        :disabled="!editForm.name || !editForm.price"
                        class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-500 disabled:opacity-50 transition-colors"
                    >
                        {{ t("common.save") }}
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="closeDeleteModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ t("stripe.delete_confirm_title") }}
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ t("stripe.delete_confirm_text") }}
                </p>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeDeleteModal">
                        {{ t("common.cancel") }}
                    </SecondaryButton>
                    <DangerButton class="ml-3" @click="deleteProduct">
                        {{ t("common.delete") }}
                    </DangerButton>
                </div>
            </div>
        </Modal>

        <!-- Transactions Modal -->
        <Modal :show="showTransactionsModal" @close="closeTransactionsModal" max-width="2xl">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                    📋 {{ t("stripe.transactions") }} - {{ selectedProduct?.name }}
                </h3>

                <div v-if="transactionsLoading" class="py-8 text-center">
                    <div class="animate-spin w-8 h-8 border-4 border-purple-500 border-t-transparent rounded-full mx-auto"></div>
                    <p class="mt-2 text-gray-500 dark:text-gray-400">{{ t("common.loading") }}...</p>
                </div>

                <div v-else-if="transactions.length === 0" class="py-8 text-center text-gray-500 dark:text-gray-400">
                    {{ t("stripe.no_transactions") }}
                </div>

                <table v-else class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ t("stripe.customer") }}</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ t("stripe.amount") }}</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ t("stripe.status") }}</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ t("stripe.date") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-for="tx in transactions" :key="tx.id">
                            <td class="px-4 py-3">
                                <div class="text-sm text-gray-900 dark:text-white">{{ tx.customer_email }}</div>
                                <div v-if="tx.customer_name" class="text-xs text-gray-500 dark:text-gray-400">{{ tx.customer_name }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white font-medium">
                                {{ formatPrice(tx.amount, tx.currency) }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    :class="[
                                        'px-2 py-0.5 text-xs rounded-full',
                                        tx.status === 'completed' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' :
                                        tx.status === 'refunded' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' :
                                        'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                    ]"
                                >
                                    {{ t(`stripe.status_${tx.status}`) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                {{ formatDateTime(tx.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="closeTransactionsModal">
                        {{ t("common.close") }}
                    </SecondaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
