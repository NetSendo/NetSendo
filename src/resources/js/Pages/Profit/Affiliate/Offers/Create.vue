<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    program: Object,
    funnels: Array,
    landingPages: Array,
    stripeProducts: Array,
    polarProducts: Array,
});

const form = useForm({
    name: '',
    description: '',
    type: 'funnel',
    entity_id: null,
    external_url: '',
    commission_type: 'percent',
    commission_value: props.program.default_commission_percent || 10,
    is_public: true,
    is_active: true,
});

const submit = () => {
    form.post(route('affiliate.offers.store'));
};

const offerTypes = [
    { value: 'funnel', label: 'Sales Funnel' },
    { value: 'landing', label: 'Landing Page' },
    { value: 'stripe_product', label: 'Stripe Product' },
    { value: 'polar_product', label: 'Polar Product' },
    { value: 'external', label: 'External URL' },
];

const getEntityOptions = () => {
    switch (form.type) {
        case 'funnel': return props.funnels;
        case 'landing': return props.landingPages;
        case 'stripe_product': return props.stripeProducts;
        case 'polar_product': return props.polarProducts;
        default: return [];
    }
};
</script>

<template>
    <Head :title="$t('affiliate.create_offer')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('affiliate.offers.index')"
                    class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {{ $t('affiliate.create_offer') }}
                </h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6 space-y-6">
                    <!-- Offer Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ $t('affiliate.offer_name') }} *
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="e.g. Premium Course Offer"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Description
                        </label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Describe this offer for affiliates..."
                        ></textarea>
                    </div>

                    <!-- Offer Type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ $t('affiliate.offer_type') }}
                        </label>
                        <select
                            v-model="form.type"
                            class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option v-for="t in offerTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </div>

                    <!-- Entity Selection (for non-external) -->
                    <div v-if="form.type !== 'external'">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Select {{ form.type.replace('_', ' ') }}
                        </label>
                        <select
                            v-model="form.entity_id"
                            class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option :value="null">-- Select --</option>
                            <option v-for="entity in getEntityOptions()" :key="entity.id" :value="entity.id">
                                {{ entity.name }}
                            </option>
                        </select>
                    </div>

                    <!-- External URL (for external type) -->
                    <div v-if="form.type === 'external'">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            External URL
                        </label>
                        <input
                            v-model="form.external_url"
                            type="url"
                            class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="https://example.com/product"
                        />
                    </div>

                    <!-- Commission Settings -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ $t('affiliate.commission_type') }}
                            </label>
                            <select
                                v-model="form.commission_type"
                                class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="percent">Percent (%)</option>
                                <option value="fixed">Fixed Amount</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ $t('affiliate.commission_value') }}
                            </label>
                            <div class="relative">
                                <input
                                    v-model="form.commission_value"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 pr-12"
                                />
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400">
                                    {{ form.commission_type === 'percent' ? '%' : program.currency }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Visibility Options -->
                    <div class="flex flex-wrap gap-6">
                        <label class="flex items-center gap-2">
                            <input
                                v-model="form.is_public"
                                type="checkbox"
                                class="rounded border-gray-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $t('affiliate.is_public') }}</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="rounded border-gray-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $t('affiliate.is_active') }}</span>
                        </label>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200 dark:border-slate-700">
                        <Link
                            :href="route('affiliate.offers.index')"
                            class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-colors"
                        >
                            {{ $t('common.cancel') }}
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-6 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                        >
                            {{ $t('affiliate.create_offer') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
