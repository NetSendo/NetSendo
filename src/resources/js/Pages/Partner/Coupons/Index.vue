<script setup>
import { Head } from '@inertiajs/vue3';
import PartnerLayout from '@/Layouts/PartnerLayout.vue';

const props = defineProps({
    coupons: Array,
});
</script>

<template>
    <Head title="Coupons" />

    <PartnerLayout>
        <div class="space-y-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">My Coupon Codes</h1>

            <div v-if="coupons?.length" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div v-for="coupon in coupons" :key="coupon.id" class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5">
                    <div class="flex items-center justify-between mb-3">
                        <code class="px-3 py-1.5 bg-indigo-100 dark:bg-indigo-900/30 text-indigo-800 dark:text-indigo-400 text-lg font-bold rounded-lg">
                            {{ coupon.code }}
                        </code>
                        <span v-if="coupon.is_active" class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Active</span>
                        <span v-else class="px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded-full">Inactive</span>
                    </div>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">{{ coupon.offer?.name }}</p>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500 dark:text-gray-400">
                            {{ coupon.discount_type === 'percent' ? `${coupon.discount_value}% off` : `${coupon.discount_value} PLN off` }}
                        </span>
                        <span class="text-gray-500 dark:text-gray-400">Used: {{ coupon.uses_count || 0 }}</span>
                    </div>
                </div>
            </div>

            <div v-else class="bg-white dark:bg-slate-800 rounded-xl p-8 text-center text-gray-500 dark:text-gray-400">
                No coupon codes assigned to you yet.
            </div>
        </div>
    </PartnerLayout>
</template>
