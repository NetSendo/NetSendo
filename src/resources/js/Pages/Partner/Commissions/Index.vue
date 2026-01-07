<script setup>
import { Head } from '@inertiajs/vue3';
import PartnerLayout from '@/Layouts/PartnerLayout.vue';

const props = defineProps({
    commissions: Object,
    summary: Object,
});

const formatCurrency = (v) => new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(v || 0);

const statusColors = {
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-blue-100 text-blue-800',
    payable: 'bg-green-100 text-green-800',
    paid: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-red-100 text-red-800',
};
</script>

<template>
    <Head title="Commissions" />

    <PartnerLayout>
        <div class="space-y-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">My Commissions</h1>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-xl p-4 border border-yellow-200 dark:border-yellow-800">
                    <p class="text-sm text-yellow-700 dark:text-yellow-400">Pending</p>
                    <p class="text-xl font-bold text-yellow-800 dark:text-yellow-300">{{ formatCurrency(summary.pending) }}</p>
                </div>
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-4 border border-blue-200 dark:border-blue-800">
                    <p class="text-sm text-blue-700 dark:text-blue-400">Approved</p>
                    <p class="text-xl font-bold text-blue-800 dark:text-blue-300">{{ formatCurrency(summary.approved) }}</p>
                </div>
                <div class="bg-green-50 dark:bg-green-900/20 rounded-xl p-4 border border-green-200 dark:border-green-800">
                    <p class="text-sm text-green-700 dark:text-green-400">Ready for Payout</p>
                    <p class="text-xl font-bold text-green-800 dark:text-green-300">{{ formatCurrency(summary.payable) }}</p>
                </div>
                <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-xl p-4 border border-emerald-200 dark:border-emerald-800">
                    <p class="text-sm text-emerald-700 dark:text-emerald-400">Paid</p>
                    <p class="text-xl font-bold text-emerald-800 dark:text-emerald-300">{{ formatCurrency(summary.paid) }}</p>
                </div>
            </div>

            <div v-if="commissions.data?.length" class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                    <thead class="bg-gray-50 dark:bg-slate-700">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Offer</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                        <tr v-for="comm in commissions.data" :key="comm.id">
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ new Date(comm.created_at).toLocaleDateString() }}</td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ comm.offer?.name }}</td>
                            <td class="px-4 py-3 text-sm text-right font-medium text-gray-900 dark:text-white">{{ formatCurrency(comm.commission_amount) }}</td>
                            <td class="px-4 py-3 text-center">
                                <span :class="statusColors[comm.status]" class="px-2 py-1 text-xs rounded-full capitalize">{{ comm.status }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="bg-white dark:bg-slate-800 rounded-xl p-8 text-center text-gray-500 dark:text-gray-400">
                No commissions yet.
            </div>
        </div>
    </PartnerLayout>
</template>
