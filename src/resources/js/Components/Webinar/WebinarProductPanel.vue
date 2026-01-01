<script setup>
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    webinarId: { type: Number, required: true },
    products: { type: Array, default: () => [] },
});

const localProducts = ref([...props.products]);
const showAddForm = ref(false);
const newProduct = ref({
    name: '',
    price: '',
    description: '',
    cta_text: 'Kup teraz', // Will remain as default but UI should translate placeholders
});

const addProduct = async () => {
    try {
        const response = await axios.post(route('webinars.products.store', props.webinarId), newProduct.value);
        localProducts.value.push(response.data.product);
        showAddForm.value = false;
        resetForm();
    } catch (error) {
        console.error('Failed to add product:', error);
    }
};

const pinProduct = async (product) => {
    try {
        await axios.post(route('webinars.products.pin', [props.webinarId, product.id]));
        localProducts.value = localProducts.value.map(p => ({
            ...p,
            is_pinned: p.id === product.id
        }));
    } catch (error) {
        console.error('Failed to pin product:', error);
    }
};

const unpinProduct = async (product) => {
    try {
        await axios.post(route('webinars.products.unpin', [props.webinarId, product.id]));
        localProducts.value = localProducts.value.map(p => ({
            ...p,
            is_pinned: false
        }));
    } catch (error) {
        console.error('Failed to unpin product:', error);
    }
};

const deleteProduct = async (product) => {
    if (!confirm(props.t ? props.t('webinars.products.confirm_delete') : 'Are you sure?')) return;

    try {
        await axios.delete(route('webinars.products.destroy', [props.webinarId, product.id]));
        localProducts.value = localProducts.value.filter(p => p.id !== product.id);
    } catch (error) {
        console.error('Failed to delete product:', error);
    }
};

const resetForm = () => {
    newProduct.value = {
        name: '',
        price: '',
        description: '',
        cta_text: 'Kup teraz',
    };
};

const formatPrice = (price, currency = 'PLN') => {
    return new Intl.NumberFormat('pl-PL', {
        style: 'currency',
        currency: currency,
    }).format(price);
};
</script>

<template>
    <div class="h-full flex flex-col bg-gray-800 text-white">
        <div class="p-4 border-b border-gray-700 flex items-center justify-between">
            <h3 class="font-medium">{{ $t('webinars.products.title') }}</h3>
            <button
                @click="showAddForm = !showAddForm"
                class="flex items-center gap-1 text-sm text-indigo-400 hover:text-indigo-300"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                {{ $t('webinars.products.add') }}
            </button>
        </div>

        <!-- Add Product Form -->
        <div v-if="showAddForm" class="p-4 bg-gray-700/50 border-b border-gray-700">
            <form @submit.prevent="addProduct" class="space-y-3">
                <input
                    v-model="newProduct.name"
                    type="text"
                    :placeholder="$t('webinars.products.name_placeholder')"
                    required
                    class="w-full rounded bg-gray-700 border-gray-600 text-white text-sm"
                />
                <input
                    v-model="newProduct.price"
                    type="number"
                    step="0.01"
                    :placeholder="$t('webinars.products.price_placeholder') + ' (PLN)'"
                    required
                    class="w-full rounded bg-gray-700 border-gray-600 text-white text-sm"
                />
                <textarea
                    v-model="newProduct.description"
                    :placeholder="$t('webinars.products.description_placeholder')"
                    rows="2"
                    class="w-full rounded bg-gray-700 border-gray-600 text-white text-sm"
                />
                <input
                    v-model="newProduct.cta_text"
                    type="text"
                    :placeholder="$t('webinars.products.cta_placeholder')"
                    class="w-full rounded bg-gray-700 border-gray-600 text-white text-sm"
                />
                <div class="flex gap-2">
                    <button
                        type="submit"
                        class="flex-1 py-2 bg-indigo-600 hover:bg-indigo-700 rounded text-sm font-medium"
                    >
                        {{ $t('webinars.products.submit_add') }}
                    </button>
                    <button
                        type="button"
                        @click="showAddForm = false; resetForm()"
                        class="px-4 py-2 bg-gray-600 hover:bg-gray-500 rounded text-sm"
                    >
                        {{ $t('webinars.products.cancel') }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Products List -->
        <div class="flex-1 overflow-y-auto p-4">
            <div v-if="localProducts.length === 0" class="text-center py-8 text-gray-400">
                <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                </svg>
                <p>{{ $t('webinars.products.empty_title') }}</p>
                <p class="text-sm mt-1">{{ $t('webinars.products.empty_desc') }}</p>
            </div>

            <div v-else class="space-y-3">
                <div
                    v-for="product in localProducts"
                    :key="product.id"
                    :class="[
                        'rounded-lg p-3 transition-all',
                        product.is_pinned ? 'bg-indigo-900/50 ring-2 ring-indigo-500' : 'bg-gray-700'
                    ]"
                >
                    <div class="flex items-start gap-3">
                        <div v-if="product.image_url" class="w-12 h-12 rounded bg-gray-600 overflow-hidden shrink-0">
                            <img :src="product.image_url" :alt="product.name" class="w-full h-full object-cover" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <h4 class="font-medium truncate">{{ product.name }}</h4>
                                <span v-if="product.is_pinned" class="text-xs bg-indigo-600 px-1.5 py-0.5 rounded">
                                    {{ $t('webinars.products.pinned_badge') }}
                                </span>
                            </div>
                            <p class="text-lg font-bold text-green-400">
                                {{ formatPrice(product.price, product.currency) }}
                            </p>
                            <p v-if="product.original_price" class="text-sm text-gray-400 line-through">
                                {{ formatPrice(product.original_price, product.currency) }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 mt-3">
                        <button
                            v-if="!product.is_pinned"
                            @click="pinProduct(product)"
                            class="flex-1 py-1.5 bg-indigo-600 hover:bg-indigo-700 rounded text-sm font-medium transition-colors"
                        >
                            📌 {{ $t('webinars.products.pin_to_chat') }}
                        </button>
                        <button
                            v-else
                            @click="unpinProduct(product)"
                            class="flex-1 py-1.5 bg-gray-600 hover:bg-gray-500 rounded text-sm font-medium transition-colors"
                        >
                            {{ $t('webinars.products.unpin') }}
                        </button>
                        <button
                            @click="deleteProduct(product)"
                            class="px-3 py-1.5 text-red-400 hover:text-red-300 hover:bg-red-900/30 rounded text-sm transition-colors"
                        >
                            {{ $t('webinars.products.delete') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
