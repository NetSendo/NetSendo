<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useDateTime } from '@/Composables/useDateTime';

const { formatCurrency: formatCurrencyBase } = useDateTime();
const props = defineProps({
    payouts: Object,
    summary: Object,
    payableByAffiliate: Array,
    program: Object,
});

const formatCurrency = (value) => {
    return formatCurrencyBase(value, props.program.currency || 'PLN');
};

const completePayout = (payout) => {
    const reference = prompt('Payment reference (optional):');
    router.post(route('affiliate.payouts.complete', payout.id), { payment_reference: reference });
};

const exportPayout = (payout) => {
    window.location.href = route('affiliate.payouts.export', payout.id);
};

const exportAllPayable = () => {
    window.location.href = route('affiliate.payouts.export-payable');
};
</script>

<template>
    <Head :title="$t('affiliate.payouts')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('affiliate.index')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </Link>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ $t('affiliate.payouts') }}</h2>
                </div>
                <div class="flex gap-2">
                    <button @click="exportAllPayable" class="px-4 py-2 bg-slate-600 text-white text-sm rounded-lg hover:bg-slate-700">
                        Export Payable CSV
                    </button>
                    <Link :href="route('affiliate.payouts.create')" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
                        {{ $t('affiliate.create_payout') }}
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Summary -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-xl p-4 border border-emerald-200 dark:border-emerald-800">
                        <p class="text-sm text-emerald-700 dark:text-emerald-400">Total Paid</p>
                        <p class="text-xl font-bold text-emerald-800 dark:text-emerald-300">{{ formatCurrency(summary.total_paid) }}</p>
                    </div>
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-xl p-4 border border-yellow-200 dark:border-yellow-800">
                        <p class="text-sm text-yellow-700 dark:text-yellow-400">Pending Payouts</p>
                        <p class="text-xl font-bold text-yellow-800 dark:text-yellow-300">{{ summary.pending_payouts }}</p>
                    </div>
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-4 border border-blue-200 dark:border-blue-800">
                        <p class="text-sm text-blue-700 dark:text-blue-400">Unpaid Commissions</p>
                        <p class="text-xl font-bold text-blue-800 dark:text-blue-300">{{ summary.unpaid_commissions_count }}</p>
                    </div>
                    <div class="bg-green-50 dark:bg-green-900/20 rounded-xl p-4 border border-green-200 dark:border-green-800">
                        <p class="text-sm text-green-700 dark:text-green-400">Unpaid Amount</p>
                        <p class="text-xl font-bold text-green-800 dark:text-green-300">{{ formatCurrency(summary.unpaid_commissions_amount) }}</p>
                    </div>
                </div>

                <!-- Payable by Affiliate -->
                <div v-if="payableByAffiliate?.length" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Ready for Payout</h3>
                    <div class="space-y-3">
                        <div v-for="item in payableByAffiliate" :key="item.affiliate_id" class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-slate-700/50">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">{{ item.affiliate_name }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ item.commissions_count }} commissions • {{ item.payout_method }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-green-600 dark:text-green-400">{{ formatCurrency(item.total_amount) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payout History -->
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700">
                    <div class="p-4 border-b border-gray-200 dark:border-slate-700">
                        <h3 class="font-semibold text-gray-900 dark:text-white">Payout History</h3>
                    </div>
                    <div v-if="payouts.data?.length" class="divide-y divide-gray-200 dark:divide-slate-700">
                        <div v-for="payout in payouts.data" :key="payout.id" class="p-4 flex items-center justify-between">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">
                                    {{ payout.affiliate?.name || 'All Affiliates' }}
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ new Date(payout.period_start).toLocaleDateString() }} - {{ new Date(payout.period_end).toLocaleDateString() }}
                                </p>
                            </div>
                            <div class="flex items-center gap-4">
                                <span
                                    class="px-2 py-1 text-xs rounded-full"
                                    :class="{
                                        'bg-yellow-100 text-yellow-800': payout.status === 'pending',
                                        'bg-blue-100 text-blue-800': payout.status === 'processing',
                                        'bg-green-100 text-green-800': payout.status === 'completed',
                                    }"
                                >
                                    {{ payout.status }}
                                </span>
                                <span class="font-bold text-gray-900 dark:text-white">{{ formatCurrency(payout.total_amount) }}</span>
                                <div class="flex gap-2">
                                    <button v-if="payout.status === 'pending'" @click="completePayout(payout)" class="text-green-600 text-sm hover:underline">Complete</button>
                                    <button @click="exportPayout(payout)" class="text-indigo-600 text-sm hover:underline">Export</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="p-8 text-center text-gray-500 dark:text-gray-400">
                        No payouts yet.
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
