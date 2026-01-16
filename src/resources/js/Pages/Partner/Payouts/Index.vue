<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import PartnerLayout from '@/Layouts/PartnerLayout.vue';
import { useDateTime } from '@/Composables/useDateTime';

const { formatCurrency: formatCurrencyBase } = useDateTime();
const props = defineProps({
    affiliate: Object,
    payouts: Array,
});

const form = useForm({
    payout_method: props.affiliate.payout_method || 'bank_transfer',
    payout_details: props.affiliate.payout_details || '',
});

const updateSettings = () => {
    form.post(route('partner.payouts.settings'));
};

const formatCurrency = (v) => formatCurrencyBase(v, 'PLN');
</script>

<template>
    <Head title="Payouts" />

    <PartnerLayout>
        <div class="space-y-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Payouts</h1>

            <!-- Payout Settings -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Payout Settings</h2>
                <form @submit.prevent="updateSettings" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payout Method</label>
                        <select v-model="form.payout_method" class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white">
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="paypal">PayPal</option>
                            <option value="wise">Wise</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Details</label>
                        <textarea
                            v-model="form.payout_details"
                            rows="3"
                            class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            placeholder="Bank account number, PayPal email, etc."
                        ></textarea>
                    </div>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                    >
                        Save Settings
                    </button>
                </form>
            </div>

            <!-- Payout History -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700">
                <div class="p-4 border-b border-gray-200 dark:border-slate-700">
                    <h2 class="font-semibold text-gray-900 dark:text-white">Payout History</h2>
                </div>
                <div v-if="payouts?.length" class="divide-y divide-gray-200 dark:divide-slate-700">
                    <div v-for="payout in payouts" :key="payout.id" class="p-4 flex items-center justify-between">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-white">{{ formatCurrency(payout.total_amount) }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ new Date(payout.paid_at || payout.created_at).toLocaleDateString() }}</p>
                        </div>
                        <span
                            class="px-2 py-1 text-xs rounded-full"
                            :class="{
                                'bg-yellow-100 text-yellow-800': payout.status === 'pending',
                                'bg-green-100 text-green-800': payout.status === 'completed',
                            }"
                        >
                            {{ payout.status }}
                        </span>
                    </div>
                </div>
                <div v-else class="p-8 text-center text-gray-500 dark:text-gray-400">
                    No payouts yet.
                </div>
            </div>
        </div>
    </PartnerLayout>
</template>
