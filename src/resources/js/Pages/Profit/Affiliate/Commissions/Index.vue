<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    commissions: Object,
    summary: Object,
    program: Object,
    filters: Object,
});

const status = ref(props.filters?.status || '');
const selectedIds = ref([]);

const formatCurrency = (value) => {
    return new Intl.NumberFormat('pl-PL', { style: 'currency', currency: props.program.currency || 'PLN' }).format(value || 0);
};

const applyFilters = () => {
    router.get(route('affiliate.commissions.index'), { status: status.value || undefined }, { preserveState: true });
};

const approveCommission = (commission) => {
    router.post(route('affiliate.commissions.approve', commission.id));
};

const rejectCommission = (commission) => {
    const reason = prompt('Rejection reason:');
    if (reason) {
        router.post(route('affiliate.commissions.reject', commission.id), { reason });
    }
};

const bulkApprove = () => {
    if (selectedIds.value.length === 0) return;
    router.post(route('affiliate.commissions.bulk-approve'), { ids: selectedIds.value });
    selectedIds.value = [];
};

const makePayable = () => {
    router.post(route('affiliate.commissions.make-payable'));
};

const toggleSelect = (id) => {
    const idx = selectedIds.value.indexOf(id);
    if (idx > -1) selectedIds.value.splice(idx, 1);
    else selectedIds.value.push(id);
};

const statusColors = {
    pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    approved: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    payable: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    paid: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
    rejected: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
};
</script>

<template>
    <Head :title="$t('affiliate.commissions')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('affiliate.index')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ $t('affiliate.commissions') }}</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Summary Cards -->
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
                        <p class="text-sm text-green-700 dark:text-green-400">Payable</p>
                        <p class="text-xl font-bold text-green-800 dark:text-green-300">{{ formatCurrency(summary.payable) }}</p>
                    </div>
                    <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-xl p-4 border border-emerald-200 dark:border-emerald-800">
                        <p class="text-sm text-emerald-700 dark:text-emerald-400">Paid</p>
                        <p class="text-xl font-bold text-emerald-800 dark:text-emerald-300">{{ formatCurrency(summary.paid) }}</p>
                    </div>
                </div>

                <!-- Filters & Actions -->
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-4 flex flex-wrap gap-4 justify-between">
                    <div class="flex gap-4">
                        <select v-model="status" @change="applyFilters" class="rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="payable">Payable</option>
                            <option value="paid">Paid</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button
                            v-if="selectedIds.length > 0"
                            @click="bulkApprove"
                            class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700"
                        >
                            Approve Selected ({{ selectedIds.length }})
                        </button>
                        <button
                            @click="makePayable"
                            class="px-4 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700"
                        >
                            Make Approved Payable
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div v-if="commissions.data?.length" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                        <thead class="bg-gray-50 dark:bg-slate-700">
                            <tr>
                                <th class="w-10 px-4 py-3"></th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Affiliate</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Offer</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Level</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                            <tr v-for="comm in commissions.data" :key="comm.id" class="hover:bg-gray-50 dark:hover:bg-slate-700/50">
                                <td class="px-4 py-3">
                                    <input
                                        v-if="comm.status === 'pending'"
                                        type="checkbox"
                                        :checked="selectedIds.includes(comm.id)"
                                        @change="toggleSelect(comm.id)"
                                        class="rounded border-gray-300 text-indigo-600"
                                    />
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ comm.affiliate?.name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ comm.offer?.name }}</td>
                                <td class="px-4 py-3 text-center text-sm text-gray-900 dark:text-white">L{{ comm.level }}</td>
                                <td class="px-4 py-3 text-right text-sm font-medium text-gray-900 dark:text-white">{{ formatCurrency(comm.commission_amount) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span :class="statusColors[comm.status]" class="px-2 py-1 text-xs rounded-full capitalize">{{ comm.status }}</span>
                                </td>
                                <td class="px-4 py-3 text-right space-x-2">
                                    <button v-if="comm.status === 'pending'" @click="approveCommission(comm)" class="text-green-600 text-sm hover:underline">Approve</button>
                                    <button v-if="['pending', 'approved'].includes(comm.status)" @click="rejectCommission(comm)" class="text-red-600 text-sm hover:underline">Reject</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="bg-white dark:bg-slate-800 rounded-xl p-8 text-center text-gray-500 dark:text-gray-400">
                    No commissions found.
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
