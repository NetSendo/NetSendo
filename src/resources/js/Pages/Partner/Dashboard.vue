<script setup>
import { Head } from '@inertiajs/vue3';
import PartnerLayout from '@/Layouts/PartnerLayout.vue';

const props = defineProps({
    stats: Object,
    recentConversions: Array,
    clicksChart: Array,
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(value || 0);
};
</script>

<template>
    <Head title="Dashboard" />

    <PartnerLayout>
        <div class="space-y-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Dashboard</h1>

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-200 dark:border-slate-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Clicks (30d)</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.clicks }}</p>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-200 dark:border-slate-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Leads (30d)</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.leads }}</p>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-200 dark:border-slate-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Sales (30d)</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.sales }}</p>
                </div>
                <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl p-5 text-white">
                    <p class="text-sm text-green-100">Earnings (30d)</p>
                    <p class="text-2xl font-bold">{{ formatCurrency(stats.earnings) }}</p>
                </div>
            </div>

            <!-- Earnings Summary -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-xl p-5 border border-yellow-200 dark:border-yellow-800">
                    <p class="text-sm text-yellow-700 dark:text-yellow-400">Pending</p>
                    <p class="text-xl font-bold text-yellow-800 dark:text-yellow-300">{{ formatCurrency(stats.pending) }}</p>
                </div>
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-5 border border-blue-200 dark:border-blue-800">
                    <p class="text-sm text-blue-700 dark:text-blue-400">Ready for Payout</p>
                    <p class="text-xl font-bold text-blue-800 dark:text-blue-300">{{ formatCurrency(stats.payable) }}</p>
                </div>
                <div class="bg-green-50 dark:bg-green-900/20 rounded-xl p-5 border border-green-200 dark:border-green-800">
                    <p class="text-sm text-green-700 dark:text-green-400">Total Paid</p>
                    <p class="text-xl font-bold text-green-800 dark:text-green-300">{{ formatCurrency(stats.paid) }}</p>
                </div>
            </div>

            <!-- Recent Conversions -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Recent Conversions</h2>
                <div v-if="recentConversions?.length" class="space-y-3">
                    <div
                        v-for="conv in recentConversions"
                        :key="conv.id"
                        class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-slate-700/50"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-xs font-bold"
                                :class="conv.type === 'purchase' ? 'bg-green-500' : 'bg-blue-500'"
                            >
                                {{ conv.type === 'purchase' ? '$' : 'L' }}
                            </span>
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white capitalize">{{ conv.type }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ conv.offer?.name }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="font-medium text-gray-900 dark:text-white">
                                {{ conv.type === 'purchase' ? formatCurrency(conv.amount) : '-' }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ new Date(conv.created_at).toLocaleDateString() }}</p>
                        </div>
                    </div>
                </div>
                <p v-else class="text-center text-gray-500 dark:text-gray-400 py-6">
                    No conversions yet. Start sharing your links!
                </p>
            </div>
        </div>
    </PartnerLayout>
</template>
