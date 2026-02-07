<script setup>
import { ref, onMounted } from "vue";
import Modal from "@/Components/Modal.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import DangerButton from "@/Components/DangerButton.vue";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const props = defineProps({
    productType: {
        type: String,
        required: true,
        validator: (value) => ["stripe", "polar", "tpay"].includes(value),
    },
    products: {
        type: Array,
        default: () => [],
    },
});

// State
const salesFunnels = ref([]);
const funnelOptions = ref({
    external_pages: [],
    contact_lists: [],
    default_embed_settings: {},
});
const loading = ref(false);
const showFunnelModal = ref(false);
const showEmbedModal = ref(false);
const editingFunnel = ref(null);
const selectedProduct = ref(null);
const generatedEmbedCode = ref("");
const copySuccess = ref(false);

// Form
const funnelForm = ref({
    name: "",
    description: "",
    thank_you_page_id: "",
    thank_you_url: "",
    target_list_id: "",
    purchase_tag: "",
    embed_settings: {
        button_text: "Kup teraz",
        button_color: "#6366f1",
        button_text_color: "#ffffff",
        button_style: "rounded",
    },
});

// Fetch data
onMounted(async () => {
    await loadFunnels();
    await loadOptions();
});

async function loadFunnels() {
    loading.value = true;
    try {
        const response = await fetch(route("settings.sales-funnels.index"));
        const data = await response.json();
        salesFunnels.value = data.funnels;
    } catch (e) {
        console.error("Failed to load sales funnels", e);
    } finally {
        loading.value = false;
    }
}

async function loadOptions() {
    try {
        const response = await fetch(route("settings.sales-funnels.options"));
        const data = await response.json();
        funnelOptions.value = data;
    } catch (e) {
        console.error("Failed to load funnel options", e);
    }
}

// Modal actions
function openCreateModal() {
    editingFunnel.value = null;
    funnelForm.value = {
        name: "",
        description: "",
        thank_you_page_id: "",
        thank_you_url: "",
        target_list_id: "",
        purchase_tag: "",
        embed_settings: { ...funnelOptions.value.default_embed_settings },
    };
    showFunnelModal.value = true;
}

function openEditModal(funnel) {
    editingFunnel.value = funnel;
    funnelForm.value = {
        name: funnel.name,
        description: funnel.description || "",
        thank_you_page_id: funnel.thank_you_page_id || "",
        thank_you_url: funnel.thank_you_url || "",
        target_list_id: funnel.target_list_id || "",
        purchase_tag: funnel.purchase_tag || "",
        embed_settings: {
            ...funnelOptions.value.default_embed_settings,
            ...(funnel.embed_settings || {}),
        },
    };
    showFunnelModal.value = true;
}

function closeFunnelModal() {
    showFunnelModal.value = false;
    editingFunnel.value = null;
}

async function saveFunnel() {
    const url = editingFunnel.value
        ? route("settings.sales-funnels.update", editingFunnel.value.id)
        : route("settings.sales-funnels.store");

    const method = editingFunnel.value ? "PUT" : "POST";

    try {
        const response = await fetch(url, {
            method,
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute("content"),
            },
            body: JSON.stringify(funnelForm.value),
        });

        if (response.ok) {
            await loadFunnels();
            closeFunnelModal();
        }
    } catch (e) {
        console.error("Failed to save funnel", e);
    }
}

async function deleteFunnel(funnel) {
    if (!confirm(t("sales_funnels.confirm_delete"))) return;

    try {
        await fetch(route("settings.sales-funnels.destroy", funnel.id), {
            method: "DELETE",
            headers: {
                "X-CSRF-TOKEN": document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute("content"),
            },
        });
        await loadFunnels();
    } catch (e) {
        console.error("Failed to delete funnel", e);
    }
}

// Embed code
async function showEmbedCodeModal(product) {
    selectedProduct.value = product;

    if (!product.sales_funnel_id) {
        alert(t("sales_funnels.assign_first"));
        return;
    }

    try {
        const response = await fetch(
            route("settings.sales-funnels.embed-code"),
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute("content"),
                },
                body: JSON.stringify({
                    product_type: props.productType,
                    product_id: product.id,
                    funnel_id: product.sales_funnel_id,
                }),
            },
        );

        const data = await response.json();
        generatedEmbedCode.value = data.embed_code;
        showEmbedModal.value = true;
    } catch (e) {
        console.error("Failed to generate embed code", e);
    }
}

function closeEmbedModal() {
    showEmbedModal.value = false;
    selectedProduct.value = null;
    generatedEmbedCode.value = "";
}

async function copyEmbedCode() {
    try {
        await navigator.clipboard.writeText(generatedEmbedCode.value);
        copySuccess.value = true;
        setTimeout(() => (copySuccess.value = false), 2000);
    } catch (e) {
        console.error("Failed to copy", e);
    }
}

// Assign funnel to product
async function assignFunnel(product, funnelId) {
    let routeName;
    if (props.productType === "stripe") {
        routeName = "settings.stripe-products.update";
    } else if (props.productType === "polar") {
        routeName = "settings.polar-products.update";
    } else {
        routeName = "settings.tpay-products.update";
    }

    try {
        await fetch(route(routeName, product.id), {
            method: "PUT",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute("content"),
            },
            body: JSON.stringify({
                sales_funnel_id: funnelId || null,
            }),
        });
        // Reload page to get updated products
        window.location.reload();
    } catch (e) {
        console.error("Failed to assign funnel", e);
    }
}

// Get funnel by ID
function getFunnelName(funnelId) {
    const funnel = salesFunnels.value.find((f) => f.id === funnelId);
    return funnel?.name || "-";
}
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex justify-between items-center">
            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                    🚀 {{ t("sales_funnels.title") }}
                </h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {{ t("sales_funnels.description") }}
                </p>
            </div>
            <button
                @click="openCreateModal"
                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-500 transition-colors flex items-center gap-2"
            >
                <svg
                    class="w-5 h-5"
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
                {{ t("sales_funnels.create") }}
            </button>
        </div>

        <!-- Sales Funnels List -->
        <div
            class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden"
        >
            <div v-if="loading" class="p-8 text-center">
                <div
                    class="animate-spin w-8 h-8 border-4 border-indigo-500 border-t-transparent rounded-full mx-auto"
                ></div>
            </div>

            <div
                v-else-if="salesFunnels.length === 0"
                class="p-8 text-center text-gray-500 dark:text-gray-400"
            >
                <svg
                    class="w-12 h-12 mx-auto text-gray-400 mb-4"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                    />
                </svg>
                <p>{{ t("sales_funnels.no_funnels") }}</p>
                <button
                    @click="openCreateModal"
                    class="mt-4 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-500"
                >
                    {{ t("sales_funnels.create_first") }}
                </button>
            </div>

            <table
                v-else
                class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"
            >
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"
                        >
                            {{ t("sales_funnels.name") }}
                        </th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"
                        >
                            {{ t("sales_funnels.target_list") }}
                        </th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"
                        >
                            {{ t("sales_funnels.products_count") }}
                        </th>
                        <th
                            class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"
                        >
                            {{ t("common.actions") }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <tr
                        v-for="funnel in salesFunnels"
                        :key="funnel.id"
                        class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                    >
                        <td class="px-6 py-4">
                            <div
                                class="font-medium text-gray-900 dark:text-white"
                            >
                                {{ funnel.name }}
                            </div>
                            <div
                                v-if="funnel.description"
                                class="text-sm text-gray-500 dark:text-gray-400 truncate max-w-xs"
                            >
                                {{ funnel.description }}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-gray-600 dark:text-gray-300">
                            {{ funnel.target_list?.name || "-" }}
                        </td>
                        <td class="px-6 py-4 text-gray-600 dark:text-gray-300">
                            {{
                                (funnel.stripe_products_count || 0) +
                                (funnel.polar_products_count || 0)
                            }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button
                                    @click="openEditModal(funnel)"
                                    class="p-2 text-gray-500 hover:text-indigo-600 dark:text-gray-400"
                                    :title="t('common.edit')"
                                >
                                    <svg
                                        class="w-5 h-5"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                        />
                                    </svg>
                                </button>
                                <button
                                    @click="deleteFunnel(funnel)"
                                    class="p-2 text-gray-500 hover:text-red-600 dark:text-gray-400"
                                    :title="t('common.delete')"
                                >
                                    <svg
                                        class="w-5 h-5"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                        />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Products Assignment Section -->
        <div
            class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden"
        >
            <div
                class="px-6 py-4 border-b border-gray-200 dark:border-gray-700"
            >
                <h4 class="font-medium text-gray-900 dark:text-white">
                    {{ t("sales_funnels.assign_to_products") }}
                </h4>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    {{ t("sales_funnels.assign_description") }}
                </p>
            </div>

            <table
                class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"
            >
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"
                        >
                            {{ t("sales_funnels.product") }}
                        </th>
                        <th
                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"
                        >
                            {{ t("sales_funnels.assigned_funnel") }}
                        </th>
                        <th
                            class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase"
                        >
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
                            <div
                                class="font-medium text-gray-900 dark:text-white"
                            >
                                {{ product.name }}
                            </div>
                            <div
                                class="text-sm text-gray-500 dark:text-gray-400"
                            >
                                {{ product.formatted_price }}
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <select
                                :value="product.sales_funnel_id"
                                @change="
                                    assignFunnel(product, $event.target.value)
                                "
                                class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm"
                            >
                                <option value="">
                                    {{ t("sales_funnels.no_funnel") }}
                                </option>
                                <option
                                    v-for="funnel in salesFunnels"
                                    :key="funnel.id"
                                    :value="funnel.id"
                                >
                                    {{ funnel.name }}
                                </option>
                            </select>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <button
                                v-if="product.sales_funnel_id"
                                @click="showEmbedCodeModal(product)"
                                class="px-3 py-1.5 bg-green-600 text-white text-sm rounded-lg hover:bg-green-500 transition-colors"
                            >
                                📋 {{ t("sales_funnels.get_embed_code") }}
                            </button>
                            <span v-else class="text-sm text-gray-400">
                                {{ t("sales_funnels.select_funnel_first") }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Funnel Create/Edit Modal -->
        <Modal :show="showFunnelModal" @close="closeFunnelModal" max-width="lg">
            <div class="p-6">
                <h3
                    class="text-lg font-medium text-gray-900 dark:text-white mb-4"
                >
                    {{
                        editingFunnel
                            ? t("sales_funnels.edit")
                            : t("sales_funnels.create")
                    }}
                </h3>

                <div class="space-y-4">
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                        >
                            {{ t("sales_funnels.name") }} *
                        </label>
                        <input
                            v-model="funnelForm.name"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                            required
                        />
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                        >
                            {{ t("sales_funnels.description") }}
                        </label>
                        <textarea
                            v-model="funnelForm.description"
                            rows="2"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                        ></textarea>
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                        >
                            {{ t("sales_funnels.target_list") }}
                        </label>
                        <select
                            v-model="funnelForm.target_list_id"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                        >
                            <option value="">{{ t("common.select") }}</option>
                            <option
                                v-for="list in funnelOptions.contact_lists"
                                :key="list.id"
                                :value="list.id"
                            >
                                {{ list.name }}
                            </option>
                        </select>
                        <p
                            class="text-xs text-gray-500 dark:text-gray-400 mt-1"
                        >
                            {{ t("sales_funnels.target_list_hint") }}
                        </p>
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                        >
                            {{ t("sales_funnels.purchase_tag") }}
                        </label>
                        <input
                            v-model="funnelForm.purchase_tag"
                            type="text"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                            placeholder="purchase, customer"
                        />
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                        >
                            {{ t("sales_funnels.thank_you_page") }}
                        </label>
                        <select
                            v-model="funnelForm.thank_you_page_id"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                        >
                            <option value="">
                                {{ t("sales_funnels.default_thank_you") }}
                            </option>
                            <option
                                v-for="page in funnelOptions.external_pages"
                                :key="page.id"
                                :value="page.id"
                            >
                                {{ page.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                        >
                            {{ t("sales_funnels.thank_you_url") }}
                        </label>
                        <input
                            v-model="funnelForm.thank_you_url"
                            type="url"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
                            placeholder="https://..."
                        />
                        <p
                            class="text-xs text-gray-500 dark:text-gray-400 mt-1"
                        >
                            {{ t("sales_funnels.thank_you_url_hint") }}
                        </p>
                    </div>

                    <div
                        class="border-t border-gray-200 dark:border-gray-700 pt-4"
                    >
                        <h4
                            class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3"
                        >
                            {{ t("sales_funnels.button_settings") }}
                        </h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label
                                    class="block text-sm text-gray-600 dark:text-gray-400 mb-1"
                                >
                                    {{ t("sales_funnels.button_text") }}
                                </label>
                                <input
                                    v-model="
                                        funnelForm.embed_settings.button_text
                                    "
                                    type="text"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm"
                                />
                            </div>
                            <div>
                                <label
                                    class="block text-sm text-gray-600 dark:text-gray-400 mb-1"
                                >
                                    {{ t("sales_funnels.button_style") }}
                                </label>
                                <select
                                    v-model="
                                        funnelForm.embed_settings.button_style
                                    "
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 text-sm"
                                >
                                    <option value="rounded">
                                        {{ t("sales_funnels.style_rounded") }}
                                    </option>
                                    <option value="pill">
                                        {{ t("sales_funnels.style_pill") }}
                                    </option>
                                    <option value="square">
                                        {{ t("sales_funnels.style_square") }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="block text-sm text-gray-600 dark:text-gray-400 mb-1"
                                >
                                    {{ t("sales_funnels.button_color") }}
                                </label>
                                <input
                                    v-model="
                                        funnelForm.embed_settings.button_color
                                    "
                                    type="color"
                                    class="w-full h-10 px-1 py-1 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700"
                                />
                            </div>
                            <div>
                                <label
                                    class="block text-sm text-gray-600 dark:text-gray-400 mb-1"
                                >
                                    {{ t("sales_funnels.button_text_color") }}
                                </label>
                                <input
                                    v-model="
                                        funnelForm.embed_settings
                                            .button_text_color
                                    "
                                    type="color"
                                    class="w-full h-10 px-1 py-1 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="closeFunnelModal">
                        {{ t("common.cancel") }}
                    </SecondaryButton>
                    <button
                        @click="saveFunnel"
                        :disabled="!funnelForm.name"
                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-500 disabled:opacity-50 transition-colors"
                    >
                        {{ t("common.save") }}
                    </button>
                </div>
            </div>
        </Modal>

        <!-- Embed Code Modal -->
        <Modal :show="showEmbedModal" @close="closeEmbedModal" max-width="lg">
            <div class="p-6">
                <h3
                    class="text-lg font-medium text-gray-900 dark:text-white mb-4"
                >
                    📋 {{ t("sales_funnels.embed_code_for") }}
                    {{ selectedProduct?.name }}
                </h3>

                <div class="bg-gray-100 dark:bg-gray-900 rounded-lg p-4 mb-4">
                    <pre
                        class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap break-all"
                        >{{ generatedEmbedCode }}</pre
                    >
                </div>

                <div
                    class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-4"
                >
                    <h4
                        class="font-medium text-blue-800 dark:text-blue-200 mb-2"
                    >
                        {{ t("sales_funnels.instructions_title") }}
                    </h4>
                    <ol
                        class="text-sm text-blue-700 dark:text-blue-300 space-y-1 list-decimal list-inside"
                    >
                        <li>{{ t("sales_funnels.instruction_1") }}</li>
                        <li>{{ t("sales_funnels.instruction_2") }}</li>
                        <li>{{ t("sales_funnels.instruction_3") }}</li>
                    </ol>
                </div>

                <div class="flex justify-end gap-3">
                    <SecondaryButton @click="closeEmbedModal">
                        {{ t("common.close") }}
                    </SecondaryButton>
                    <button
                        @click="copyEmbedCode"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-500 transition-colors flex items-center gap-2"
                    >
                        <svg
                            class="w-5 h-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                            />
                        </svg>
                        {{
                            copySuccess ? t("common.copied") : t("common.copy")
                        }}
                    </button>
                </div>
            </div>
        </Modal>
    </div>
</template>
