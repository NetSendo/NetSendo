<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    offers: Object,
    program: Object,
});

const formatCommission = (offer) => {
    if (offer.commission_type === 'percent') {
        return `${offer.commission_value}%`;
    }
    return `${offer.commission_value} ${props.program.currency}`;
};

const deleteOffer = (offer) => {
    if (confirm(`Delete offer "${offer.name}"?`)) {
        router.delete(route('affiliate.offers.destroy', offer.id));
    }
};
</script>

<template>
    <Head :title="$t('affiliate.offers')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link
                        :href="route('affiliate.index')"
                        class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </Link>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ $t('affiliate.offers') }}
                    </h2>
                </div>
                <Link
                    :href="route('affiliate.offers.create')"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors"
                >
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ $t('affiliate.create_offer') }}
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div v-if="offers.data?.length > 0" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                        <thead class="bg-gray-50 dark:bg-slate-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                    {{ $t('affiliate.offer_name') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                    {{ $t('affiliate.offer_type') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                    {{ $t('affiliate.commission_value') }}
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                    {{ $t('affiliate.total_clicks') }}
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                    {{ $t('affiliate.conversions') }}
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                    {{ $t('common.status') }}
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                    {{ $t('common.actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                            <tr v-for="offer in offers.data" :key="offer.id" class="hover:bg-gray-50 dark:hover:bg-slate-700/50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 flex-shrink-0 rounded-lg bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center">
                                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                            </svg>
                                        </div>
                                        <div class="ml-4">
                                            <div class="font-medium text-gray-900 dark:text-white">{{ offer.name }}</div>
                                            <div v-if="offer.is_public" class="text-xs text-green-600 dark:text-green-400">Public</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300 capitalize">
                                    {{ offer.type.replace('_', ' ') }}
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400">
                                        {{ formatCommission(offer) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center text-sm text-gray-900 dark:text-white">
                                    {{ offer.clicks_count || 0 }}
                                </td>
                                <td class="px-6 py-4 text-center text-sm text-gray-900 dark:text-white">
                                    {{ offer.conversions_count || 0 }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span
                                        class="px-2 py-1 text-xs rounded-full"
                                        :class="offer.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-600 dark:bg-slate-600 dark:text-gray-400'"
                                    >
                                        {{ offer.is_active ? $t('affiliate.status_active') : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <Link
                                        :href="route('affiliate.offers.edit', offer.id)"
                                        class="text-indigo-600 dark:text-indigo-400 hover:underline mr-3 text-sm"
                                    >
                                        {{ $t('common.edit') }}
                                    </Link>
                                    <button
                                        @click="deleteOffer(offer)"
                                        class="text-red-600 dark:text-red-400 hover:underline text-sm"
                                    >
                                        {{ $t('common.delete') }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-8 text-center">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                        <svg class="w-8 h-8 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Offers Yet</h3>
                    <p class="text-gray-500 dark:text-gray-400 mb-4">Create your first offer to start tracking affiliate conversions.</p>
                    <Link
                        :href="route('affiliate.offers.create')"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700"
                    >
                        {{ $t('affiliate.create_offer') }}
                    </Link>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
