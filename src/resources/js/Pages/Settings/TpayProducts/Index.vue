<script setup>
import { ref, computed } from "vue";
import { Head, useForm, router, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import Modal from "@/Components/Modal.vue";
import { useI18n } from "vue-i18n";

const props = defineProps({
    products: Array,
    isConfigured: Boolean,
    salesFunnels: {
        type: Array,
        default: () => [],
    },
});

const { t } = useI18n();
const page = usePage();

const showCreateModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const showTransactionsModal = ref(false);
const selectedProduct = ref(null);
const transactions = ref([]);
const loadingTransactions = ref(false);
const activeTab = ref("products");
const copiedUrl = ref(null);

const form = useForm({
    name: "",
    description: "",
    price: "",
    currency: "PLN",
    is_active: true,
});

const currencies = [
    { code: "PLN", symbol: "zł" },
    { code: "EUR", symbol: "€" },
    { code: "USD", symbol: "$" },
    { code: "GBP", symbol: "£" },
];

const formatPrice = (amount, currency) => {
    const num = parseFloat(amount);
    if (isNaN(num)) return "—";
    const symbol =
        currencies.find((c) => c.code === currency)?.symbol || currency;
    return `${num.toFixed(2)} ${symbol}`;
};

const formatDateTime = (dateString) => {
    if (!dateString) return "—";
    return new Date(dateString).toLocaleString();
};

/* Modal Actions */
const openCreateModal = () => {
    form.reset();
    form.clearErrors();
    showCreateModal.value = true;
};

const closeCreateModal = () => {
    showCreateModal.value = false;
};

const createProduct = () => {
    router.post(
        route("settings.tpay-products.store"),
        {
            name: form.name,
            description: form.description,
            price: form.price,
            currency: form.currency,
            is_active: form.is_active,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                closeCreateModal();
                form.reset();
            },
        },
    );
};

const openEditModal = (product) => {
    selectedProduct.value = product;
    form.name = product.name;
    form.description = product.description || "";
    form.price = product.price;
    form.currency = product.currency;
    form.is_active = product.is_active;
    showEditModal.value = true;
};

const closeEditModal = () => {
    showEditModal.value = false;
    selectedProduct.value = null;
};

const updateProduct = () => {
    router.put(
        route("settings.tpay-products.update", selectedProduct.value.id),
        {
            name: form.name,
            description: form.description,
            price: form.price,
            currency: form.currency,
            is_active: form.is_active,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                closeEditModal();
            },
        },
    );
};

const confirmDelete = (product) => {
    selectedProduct.value = product;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    selectedProduct.value = null;
};

const deleteProduct = () => {
    router.delete(
        route("settings.tpay-products.destroy", selectedProduct.value.id),
        {
            onSuccess: () => {
                closeDeleteModal();
            },
        },
    );
};

const showTransactions = async (product) => {
    selectedProduct.value = product;
    loadingTransactions.value = true;
    showTransactionsModal.value = true;

    try {
        const response = await fetch(
            route("settings.tpay-products.transactions", product.id),
        );
        const data = await response.json();
        transactions.value = data.transactions || [];
    } catch (error) {
        console.error("Failed to load transactions:", error);
        transactions.value = [];
    } finally {
        loadingTransactions.value = false;
    }
};

const closeTransactionsModal = () => {
    showTransactionsModal.value = false;
    selectedProduct.value = null;
    transactions.value = [];
};

const getCheckoutUrl = async (product) => {
    try {
        const response = await fetch(
            route("settings.tpay-products.checkout-url", product.id),
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN":
                        page.props.csrf_token ||
                        document.querySelector('meta[name="csrf-token"]')
                            ?.content,
                },
            },
        );
        const data = await response.json();
        if (data.checkout_url) {
            navigator.clipboard.writeText(data.checkout_url);
            copiedUrl.value = product.id;
            setTimeout(() => {
                copiedUrl.value = null;
            }, 2000);
        }
    } catch (error) {
        console.error("Failed to get checkout URL:", error);
    }
};
</script>

<template>
    <Head :title="t('tpay.products_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2
                    class="text-xl font-semibold text-gray-800 dark:text-gray-100"
                >
                    💳 {{ t("tpay.products_title") }}
                </h2>
                <button
                    @click="openCreateModal"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    <svg
                        class="mr-2 h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 4v16m8-8H4"
                        />
                    </svg>
                    {{ t("tpay.add_product") }}
                </button>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Configuration Warning -->
                <div
                    v-if="!isConfigured"
                    class="rounded-md bg-yellow-50 dark:bg-yellow-900/20 p-4"
                >
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg
                                class="h-5 w-5 text-yellow-400"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p
                                class="text-sm text-yellow-700 dark:text-yellow-300"
                            >
                                {{ t("tpay.configure_hint") }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Products List -->
                <div
                    class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden"
                >
                    <div
                        class="px-6 py-4 border-b border-gray-200 dark:border-gray-700"
                    >
                        <h3
                            class="text-lg font-medium text-gray-900 dark:text-gray-100"
                        >
                            {{ t("tpay.products_list") }}
                        </h3>
                        <p
                            class="text-sm text-gray-500 dark:text-gray-400 mt-1"
                        >
                            {{ t("tpay.products_description") }}
                        </p>
                    </div>

                    <div
                        v-if="products.length === 0"
                        class="px-6 py-12 text-center"
                    >
                        <svg
                            class="w-12 h-12 mx-auto text-gray-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                            />
                        </svg>
                        <h3
                            class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100"
                        >
                            {{ t("tpay.no_products") }}
                        </h3>
                        <p
                            class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                        >
                            {{ t("tpay.no_products_hint") }}
                        </p>
                        <button
                            @click="openCreateModal"
                            class="mt-4 inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700"
                        >
                            {{ t("tpay.add_first_product") }}
                        </button>
                    </div>

                    <table
                        v-else
                        class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"
                    >
                        <thead class="bg-gray-50 dark:bg-gray-700/50">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                                >
                                    {{ t("tpay.col_product") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                                >
                                    {{ t("tpay.col_price") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                                >
                                    {{ t("tpay.col_status") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                                >
                                    {{ t("tpay.col_sales") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                                >
                                    {{ t("tpay.col_actions") }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700"
                        >
                            <tr
                                v-for="product in products"
                                :key="product.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <div
                                            class="text-sm font-medium text-gray-900 dark:text-gray-100"
                                        >
                                            {{ product.name }}
                                        </div>
                                        <div
                                            v-if="product.description"
                                            class="text-sm text-gray-500 dark:text-gray-400 truncate max-w-xs"
                                        >
                                            {{ product.description }}
                                        </div>
                                    </div>
                                </td>
                                <td
                                    class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100"
                                >
                                    {{
                                        formatPrice(
                                            product.price,
                                            product.currency,
                                        )
                                    }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        :class="[
                                            'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                            product.is_active
                                                ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
                                                : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                        ]"
                                    >
                                        {{
                                            product.is_active
                                                ? t("tpay.active")
                                                : t("tpay.inactive")
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{ product.sales_count || 0 }}
                                </td>
                                <td
                                    class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2"
                                >
                                    <button
                                        @click="getCheckoutUrl(product)"
                                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                        :title="t('tpay.copy_checkout_url')"
                                    >
                                        <svg
                                            v-if="copiedUrl === product.id"
                                            class="h-4 w-4 inline text-green-500"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M5 13l4 4L19 7"
                                            />
                                        </svg>
                                        <svg
                                            v-else
                                            class="h-4 w-4 inline"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"
                                            />
                                        </svg>
                                    </button>
                                    <button
                                        @click="showTransactions(product)"
                                        class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-300"
                                    >
                                        {{ t("tpay.transactions") }}
                                    </button>
                                    <button
                                        @click="openEditModal(product)"
                                        class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                    >
                                        {{ t("common.edit") }}
                                    </button>
                                    <button
                                        @click="confirmDelete(product)"
                                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                    >
                                        {{ t("common.delete") }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Create Product Modal -->
        <Modal :show="showCreateModal" @close="closeCreateModal">
            <div class="p-6">
                <h2
                    class="text-lg font-medium text-gray-900 dark:text-gray-100"
                >
                    {{ t("tpay.create_product") }}
                </h2>
                <form @submit.prevent="createProduct" class="mt-6 space-y-4">
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >{{ t("tpay.product_name") }}</label
                        >
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >{{ t("tpay.product_description") }}</label
                        >
                        <textarea
                            v-model="form.description"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        ></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >{{ t("tpay.product_price") }}</label
                            >
                            <input
                                v-model="form.price"
                                type="number"
                                step="0.01"
                                min="0.01"
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                        </div>
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >{{ t("tpay.product_currency") }}</label
                            >
                            <select
                                v-model="form.currency"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            >
                                <option
                                    v-for="c in currencies"
                                    :key="c.code"
                                    :value="c.code"
                                >
                                    {{ c.code }} ({{ c.symbol }})
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded dark:border-gray-600 dark:bg-gray-700"
                        />
                        <label
                            class="ml-2 block text-sm text-gray-900 dark:text-gray-100"
                            >{{ t("tpay.product_active") }}</label
                        >
                    </div>
                    <div class="flex justify-end space-x-3 pt-4">
                        <button
                            type="button"
                            @click="closeCreateModal"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"
                        >
                            {{ t("common.cancel") }}
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50"
                        >
                            {{ t("common.create") }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Edit Product Modal -->
        <Modal :show="showEditModal" @close="closeEditModal">
            <div class="p-6">
                <h2
                    class="text-lg font-medium text-gray-900 dark:text-gray-100"
                >
                    {{ t("tpay.edit_product") }}
                </h2>
                <form @submit.prevent="updateProduct" class="mt-6 space-y-4">
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >{{ t("tpay.product_name") }}</label
                        >
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >{{ t("tpay.product_description") }}</label
                        >
                        <textarea
                            v-model="form.description"
                            rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        ></textarea>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >{{ t("tpay.product_price") }}</label
                            >
                            <input
                                v-model="form.price"
                                type="number"
                                step="0.01"
                                min="0.01"
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            />
                        </div>
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300"
                                >{{ t("tpay.product_currency") }}</label
                            >
                            <select
                                v-model="form.currency"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            >
                                <option
                                    v-for="c in currencies"
                                    :key="c.code"
                                    :value="c.code"
                                >
                                    {{ c.code }} ({{ c.symbol }})
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="flex items-center">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded dark:border-gray-600 dark:bg-gray-700"
                        />
                        <label
                            class="ml-2 block text-sm text-gray-900 dark:text-gray-100"
                            >{{ t("tpay.product_active") }}</label
                        >
                    </div>
                    <div class="flex justify-end space-x-3 pt-4">
                        <button
                            type="button"
                            @click="closeEditModal"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"
                        >
                            {{ t("common.cancel") }}
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50"
                        >
                            {{ t("common.save") }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Delete Confirmation Modal -->
        <Modal :show="showDeleteModal" @close="closeDeleteModal" max-width="md">
            <div class="p-6">
                <h2
                    class="text-lg font-medium text-gray-900 dark:text-gray-100"
                >
                    {{ t("tpay.delete_product") }}
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    {{
                        t("tpay.delete_product_confirm", {
                            name: selectedProduct?.name,
                        })
                    }}
                </p>
                <div class="mt-6 flex justify-end space-x-3">
                    <button
                        type="button"
                        @click="closeDeleteModal"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"
                    >
                        {{ t("common.cancel") }}
                    </button>
                    <button
                        type="button"
                        @click="deleteProduct"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700"
                    >
                        {{ t("common.delete") }}
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Transactions Modal -->
        <Modal
            :show="showTransactionsModal"
            @close="closeTransactionsModal"
            max-width="3xl"
        >
            <div class="p-6">
                <h2
                    class="text-lg font-medium text-gray-900 dark:text-gray-100"
                >
                    {{
                        t("tpay.transactions_for", {
                            name: selectedProduct?.name,
                        })
                    }}
                </h2>

                <div v-if="loadingTransactions" class="py-8 text-center">
                    <svg
                        class="w-8 h-8 mx-auto animate-spin text-indigo-500"
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
                        ></circle>
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        ></path>
                    </svg>
                </div>

                <div
                    v-else-if="transactions.length === 0"
                    class="py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                >
                    {{ t("tpay.no_transactions") }}
                </div>

                <table
                    v-else
                    class="mt-4 min-w-full divide-y divide-gray-200 dark:divide-gray-700"
                >
                    <thead>
                        <tr>
                            <th
                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"
                            >
                                {{ t("tpay.col_email") }}
                            </th>
                            <th
                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"
                            >
                                {{ t("tpay.col_amount") }}
                            </th>
                            <th
                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"
                            >
                                {{ t("tpay.col_status") }}
                            </th>
                            <th
                                class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"
                            >
                                {{ t("tpay.col_date") }}
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-200 dark:divide-gray-700"
                    >
                        <tr v-for="tx in transactions" :key="tx.id">
                            <td
                                class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100"
                            >
                                {{ tx.customer_email || "—" }}
                            </td>
                            <td
                                class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100"
                            >
                                {{ formatPrice(tx.amount, tx.currency) }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    :class="[
                                        'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium',
                                        tx.status === 'completed'
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300'
                                            : tx.status === 'pending'
                                              ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300'
                                              : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
                                    ]"
                                >
                                    {{ tx.status }}
                                </span>
                            </td>
                            <td
                                class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400"
                            >
                                {{ formatDateTime(tx.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="mt-6 flex justify-end">
                    <button
                        type="button"
                        @click="closeTransactionsModal"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600"
                    >
                        {{ t("common.close") }}
                    </button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
