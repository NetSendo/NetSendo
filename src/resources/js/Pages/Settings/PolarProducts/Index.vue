<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, useForm, Link } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import { ref, computed } from "vue";
import axios from "axios";

const { t } = useI18n();

const props = defineProps({
    products: {
        type: Object,
        default: () => ({ data: [] }),
    },
    isConfigured: Boolean,
});

// Safe access to products data
const productsList = computed(() => props.products?.data || []);

const showCreateModal = ref(false);
const showCheckoutModal = ref(false);
const checkoutUrl = ref(null);
const generatingUrl = ref(false);
const selectedProduct = ref(null);

const createForm = useForm({
    name: "",
    description: "",
    price: "",
    currency: "USD",
    type: "one_time",
    billing_interval: "month",
});

const createProduct = () => {
    createForm.transform((data) => ({
        ...data,
        price: Math.round(parseFloat(data.price) * 100), // Convert to cents
    })).post(route("settings.polar-products.store"), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
};

const deleteProduct = (product) => {
    if (confirm(t("polar.confirm_delete"))) {
        useForm({}).delete(route("settings.polar-products.destroy", product.id), {
            preserveScroll: true,
        });
    }
};

const getCheckoutUrl = async (product) => {
    selectedProduct.value = product;
    generatingUrl.value = true;
    checkoutUrl.value = null;
    showCheckoutModal.value = true;

    try {
        const response = await axios.post(route("settings.polar-products.checkout-url", product.id));
        checkoutUrl.value = response.data.url;
    } catch (error) {
        checkoutUrl.value = null;
    } finally {
        generatingUrl.value = false;
    }
};

const copyUrl = () => {
    if (checkoutUrl.value) {
        navigator.clipboard.writeText(checkoutUrl.value);
    }
};

const formatPrice = (price, currency) => {
    return new Intl.NumberFormat("en-US", {
        style: "currency",
        currency: currency,
    }).format(price / 100);
};
</script>

<template>
    <Head :title="$t('polar.products_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-white">
                    {{ $t("polar.products_title") }}
                </h2>
                <button
                    v-if="isConfigured"
                    @click="showCreateModal = true"
                    class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ $t("polar.create_product") }}
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Not Configured Warning -->
                <div v-if="!isConfigured" class="mb-6 rounded-xl bg-amber-500/10 p-4 ring-1 ring-amber-500/20">
                    <div class="flex items-center gap-3">
                        <svg class="h-5 w-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <p class="text-amber-400">
                            {{ $t("polar.configure_first") }}
                            <Link :href="route('settings.polar.index')" class="underline hover:no-underline">
                                {{ $t("polar.go_to_settings") }}
                            </Link>
                        </p>
                    </div>
                </div>

                <!-- Products List -->
                <div class="rounded-2xl bg-slate-800 ring-1 ring-white/10 overflow-hidden">
                    <div v-if="productsList.length === 0" class="p-12 text-center">
                        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-slate-700">
                            <svg class="h-8 w-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-white mb-2">{{ $t("polar.no_products") }}</h3>
                        <p class="text-slate-400 mb-6">{{ $t("polar.no_products_desc") }}</p>
                        <button
                            v-if="isConfigured"
                            @click="showCreateModal = true"
                            class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-blue-500"
                        >
                            {{ $t("polar.create_first_product") }}
                        </button>
                    </div>

                    <table v-else class="w-full">
                        <thead class="bg-slate-900/50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase text-slate-400">{{ $t("polar.product_name") }}</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase text-slate-400">{{ $t("polar.price") }}</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase text-slate-400">{{ $t("polar.type") }}</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase text-slate-400">{{ $t("polar.transactions") }}</th>
                                <th class="px-6 py-4 text-right text-xs font-semibold uppercase text-slate-400">{{ $t("common.actions") }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700">
                            <tr v-for="product in productsList" :key="product.id" class="hover:bg-slate-700/30 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-white">{{ product.name }}</div>
                                    <div v-if="product.description" class="text-sm text-slate-400 truncate max-w-xs">{{ product.description }}</div>
                                </td>
                                <td class="px-6 py-4 text-slate-300">
                                    {{ formatPrice(product.price, product.currency) }}
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium" :class="product.type === 'recurring' ? 'bg-blue-500/10 text-blue-400' : 'bg-slate-500/10 text-slate-400'">
                                        {{ product.type === 'recurring' ? $t('polar.recurring') : $t('polar.one_time') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-slate-400">
                                    {{ product.transactions_count || 0 }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            @click="getCheckoutUrl(product)"
                                            class="p-2 rounded-lg bg-blue-500/10 text-blue-400 hover:bg-blue-500/20"
                                            :title="$t('polar.get_checkout_url')"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                            </svg>
                                        </button>
                                        <button
                                            @click="deleteProduct(product)"
                                            class="p-2 rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20"
                                            :title="$t('common.delete')"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Create Product Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showCreateModal = false"></div>
                    <div class="relative w-full max-w-lg rounded-2xl bg-slate-800 shadow-2xl ring-1 ring-white/10">
                        <div class="flex items-center justify-between border-b border-slate-700 px-6 py-4">
                            <h3 class="text-lg font-semibold text-white">{{ $t("polar.create_product") }}</h3>
                            <button @click="showCreateModal = false" class="rounded-lg p-1 text-slate-400 hover:bg-slate-700 hover:text-white">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <form @submit.prevent="createProduct" class="p-6 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-1">{{ $t("polar.product_name") }} *</label>
                                <input v-model="createForm.name" type="text" required class="w-full rounded-lg border-slate-600 bg-slate-900 px-4 py-2.5 text-white focus:border-blue-500 focus:ring-blue-500" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-1">{{ $t("polar.description") }}</label>
                                <textarea v-model="createForm.description" rows="3" class="w-full rounded-lg border-slate-600 bg-slate-900 px-4 py-2.5 text-white focus:border-blue-500 focus:ring-blue-500"></textarea>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-1">{{ $t("polar.price") }} *</label>
                                    <input v-model="createForm.price" type="number" step="0.01" min="0.01" required class="w-full rounded-lg border-slate-600 bg-slate-900 px-4 py-2.5 text-white focus:border-blue-500 focus:ring-blue-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-300 mb-1">{{ $t("polar.currency") }}</label>
                                    <select v-model="createForm.currency" class="w-full rounded-lg border-slate-600 bg-slate-900 px-4 py-2.5 text-white focus:border-blue-500 focus:ring-blue-500">
                                        <option value="USD">USD</option>
                                        <option value="EUR">EUR</option>
                                        <option value="PLN">PLN</option>
                                        <option value="GBP">GBP</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-300 mb-1">{{ $t("polar.type") }}</label>
                                <select v-model="createForm.type" class="w-full rounded-lg border-slate-600 bg-slate-900 px-4 py-2.5 text-white focus:border-blue-500 focus:ring-blue-500">
                                    <option value="one_time">{{ $t("polar.one_time") }}</option>
                                    <option value="recurring">{{ $t("polar.recurring") }}</option>
                                </select>
                            </div>
                            <div v-if="createForm.type === 'recurring'">
                                <label class="block text-sm font-medium text-slate-300 mb-1">{{ $t("polar.billing_interval") }}</label>
                                <select v-model="createForm.billing_interval" class="w-full rounded-lg border-slate-600 bg-slate-900 px-4 py-2.5 text-white focus:border-blue-500 focus:ring-blue-500">
                                    <option value="month">{{ $t("polar.monthly") }}</option>
                                    <option value="year">{{ $t("polar.yearly") }}</option>
                                </select>
                            </div>
                            <div class="flex items-center justify-end gap-3 pt-4">
                                <button type="button" @click="showCreateModal = false" class="px-4 py-2 text-sm font-medium text-slate-400 hover:text-white">
                                    {{ $t("common.cancel") }}
                                </button>
                                <button type="submit" :disabled="createForm.processing" class="inline-flex items-center px-6 py-2.5 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white rounded-lg text-sm font-medium">
                                    {{ $t("common.create") }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Checkout URL Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div v-if="showCheckoutModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showCheckoutModal = false"></div>
                    <div class="relative w-full max-w-lg rounded-2xl bg-slate-800 shadow-2xl ring-1 ring-white/10">
                        <div class="flex items-center justify-between border-b border-slate-700 px-6 py-4">
                            <h3 class="text-lg font-semibold text-white">{{ $t("polar.checkout_url") }}</h3>
                            <button @click="showCheckoutModal = false" class="rounded-lg p-1 text-slate-400 hover:bg-slate-700 hover:text-white">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="p-6">
                            <div v-if="generatingUrl" class="text-center py-8">
                                <svg class="mx-auto h-8 w-8 animate-spin text-blue-400" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <p class="mt-4 text-slate-400">{{ $t("polar.generating_url") }}</p>
                            </div>
                            <div v-else-if="checkoutUrl" class="space-y-4">
                                <p class="text-sm text-slate-400">{{ $t("polar.checkout_url_desc") }}</p>
                                <div class="flex items-center gap-2">
                                    <input :value="checkoutUrl" readonly class="flex-1 rounded-lg border-slate-600 bg-slate-900 px-4 py-2.5 text-sm text-white font-mono" />
                                    <button @click="copyUrl" class="p-2.5 rounded-lg bg-blue-600 text-white hover:bg-blue-500">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                    </button>
                                </div>
                                <a :href="checkoutUrl" target="_blank" class="inline-flex items-center gap-2 text-sm text-blue-400 hover:text-blue-300">
                                    {{ $t("polar.open_checkout") }}
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                    </svg>
                                </a>
                            </div>
                            <div v-else class="text-center py-8">
                                <p class="text-red-400">{{ $t("polar.checkout_url_error") }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
    transition: opacity 0.2s ease;
}
.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>
