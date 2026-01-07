<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PartnerLayout from '@/Layouts/PartnerLayout.vue';

const props = defineProps({
    offers: Array,
});
</script>

<template>
    <Head title="Offers" />

    <PartnerLayout>
        <div class="space-y-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Available Offers</h1>

            <div v-if="offers?.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <Link
                    v-for="offer in offers"
                    :key="offer.id"
                    :href="route('partner.offers.show', offer.id)"
                    class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6 hover:shadow-lg transition-shadow"
                >
                    <div class="flex items-start justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                            </svg>
                        </div>
                        <span class="px-3 py-1 bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400 text-sm font-medium rounded-full">
                            {{ offer.commission_type === 'percent' ? `${offer.commission_value}%` : `${offer.commission_value} PLN` }}
                        </span>
                    </div>
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-2">{{ offer.name }}</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">{{ offer.description || 'No description' }}</p>
                    <div class="mt-4 flex items-center text-sm text-indigo-600 dark:text-indigo-400">
                        View Details
                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </Link>
            </div>

            <div v-else class="bg-white dark:bg-slate-800 rounded-xl p-8 text-center">
                <p class="text-gray-500 dark:text-gray-400">No offers available at the moment.</p>
            </div>
        </div>
    </PartnerLayout>
</template>
