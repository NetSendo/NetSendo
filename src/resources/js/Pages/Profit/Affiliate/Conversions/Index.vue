<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useDateTime } from '@/Composables/useDateTime';

const { formatCurrency: formatCurrencyBase } = useDateTime();
const props = defineProps({
    conversions: Object,
    program: Object,
    filters: Object,
});

const type = ref(props.filters?.type || '');
const dateFrom = ref(props.filters?.date_from || '');
const dateTo = ref(props.filters?.date_to || '');

const formatCurrency = (value) => {
    return formatCurrencyBase(value, props.program.currency || 'PLN');
};

const applyFilters = () => {
    router.get(route('affiliate.conversions.index'), {
        type: type.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    }, { preserveState: true });
};

const typeColors = {
    click: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    lead: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
    purchase: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    refund: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
};
</script>

<template>
    <Head :title="$t('affiliate.conversions')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('affiliate.index')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ $t('affiliate.conversions') }}</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Filters -->
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-4">
                    <div class="flex flex-wrap gap-4">
                        <select v-model="type" class="rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm">
                            <option value="">All Types</option>
                            <option value="lead">Leads</option>
                            <option value="purchase">Purchases</option>
                            <option value="refund">Refunds</option>
                        </select>
                        <input v-model="dateFrom" type="date" class="rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm" />
                        <input v-model="dateTo" type="date" class="rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm" />
                        <button @click="applyFilters" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
                            Filter
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div v-if="conversions.data?.length" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                        <thead class="bg-gray-50 dark:bg-slate-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Affiliate</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Offer</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                            <tr v-for="conv in conversions.data" :key="conv.id" class="hover:bg-gray-50 dark:hover:bg-slate-700/50">
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ new Date(conv.created_at).toLocaleString() }}
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="typeColors[conv.type]" class="px-2 py-1 text-xs rounded-full capitalize">
                                        {{ conv.type }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">{{ conv.affiliate?.name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ conv.offer?.name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ conv.customer_email || '-' }}</td>
                                <td class="px-4 py-3 text-right text-sm font-medium" :class="conv.type === 'refund' ? 'text-red-600' : 'text-gray-900 dark:text-white'">
                                    {{ conv.amount ? formatCurrency(conv.amount) : '-' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-else class="bg-white dark:bg-slate-800 rounded-xl p-8 text-center text-gray-500 dark:text-gray-400">
                    No conversions found.
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
