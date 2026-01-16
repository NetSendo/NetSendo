<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useDateTime } from '@/Composables/useDateTime';

const { formatCurrency: formatCurrencyBase } = useDateTime();
const props = defineProps({
    affiliates: Object,
    program: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const status = ref(props.filters?.status || '');

const applyFilters = () => {
    router.get(route('affiliate.affiliates.index'), {
        search: search.value || undefined,
        status: status.value || undefined,
    }, { preserveState: true });
};

const formatCurrency = (value) => {
    return formatCurrencyBase(value, props.program.currency || 'PLN');
};

const approveAffiliate = (affiliate) => {
    router.post(route('affiliate.affiliates.approve', affiliate.id));
};

const blockAffiliate = (affiliate) => {
    if (confirm(`Block affiliate "${affiliate.name}"?`)) {
        router.post(route('affiliate.affiliates.block', affiliate.id));
    }
};

const statusColors = {
    pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    approved: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    blocked: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
};
</script>

<template>
    <Head :title="$t('affiliate.affiliates')" />

    <AuthenticatedLayout>
        <template #header>
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
                    {{ $t('affiliate.affiliates') }}
                </h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Filters -->
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-4">
                    <div class="flex flex-wrap gap-4">
                        <div class="flex-1 min-w-[200px]">
                            <input
                                v-model="search"
                                type="text"
                                :placeholder="$t('common.search') + '...'"
                                class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm"
                                @keyup.enter="applyFilters"
                            />
                        </div>
                        <select
                            v-model="status"
                            @change="applyFilters"
                            class="rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm"
                        >
                            <option value="">{{ $t('common.all') }}</option>
                            <option value="pending">{{ $t('affiliate.status_pending') }}</option>
                            <option value="approved">{{ $t('affiliate.status_approved') }}</option>
                            <option value="blocked">{{ $t('affiliate.status_blocked') }}</option>
                        </select>
                        <button
                            @click="applyFilters"
                            class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700"
                        >
                            {{ $t('common.search') }}
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div v-if="affiliates.data?.length > 0" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                        <thead class="bg-gray-50 dark:bg-slate-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                    Affiliate
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                    {{ $t('common.status') }}
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                    {{ $t('affiliate.total_clicks') }}
                                </th>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                    {{ $t('affiliate.conversions') }}
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                    Earnings
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                    {{ $t('common.actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                            <tr v-for="affiliate in affiliates.data" :key="affiliate.id" class="hover:bg-gray-50 dark:hover:bg-slate-700/50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center flex-shrink-0">
                                            <span class="text-white font-bold text-sm">{{ affiliate.name?.charAt(0).toUpperCase() }}</span>
                                        </div>
                                        <div class="ml-3">
                                            <Link
                                                :href="route('affiliate.affiliates.show', affiliate.id)"
                                                class="font-medium text-gray-900 dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400"
                                            >
                                                {{ affiliate.name }}
                                            </Link>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ affiliate.email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span :class="statusColors[affiliate.status]" class="px-2 py-1 text-xs font-medium rounded-full">
                                        {{ $t(`affiliate.status_${affiliate.status}`) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center text-sm text-gray-900 dark:text-white">
                                    {{ affiliate.clicks_count || 0 }}
                                </td>
                                <td class="px-6 py-4 text-center text-sm text-gray-900 dark:text-white">
                                    {{ affiliate.conversions_count || 0 }}
                                </td>
                                <td class="px-6 py-4 text-right text-sm font-medium text-gray-900 dark:text-white">
                                    {{ formatCurrency(affiliate.commissions_sum_commission_amount) }}
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    <Link
                                        :href="route('affiliate.affiliates.show', affiliate.id)"
                                        class="text-indigo-600 dark:text-indigo-400 hover:underline text-sm"
                                    >
                                        View
                                    </Link>
                                    <button
                                        v-if="affiliate.status === 'pending'"
                                        @click="approveAffiliate(affiliate)"
                                        class="text-green-600 dark:text-green-400 hover:underline text-sm"
                                    >
                                        Approve
                                    </button>
                                    <button
                                        v-if="affiliate.status === 'approved'"
                                        @click="blockAffiliate(affiliate)"
                                        class="text-red-600 dark:text-red-400 hover:underline text-sm"
                                    >
                                        Block
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Empty State -->
                <div v-else class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-8 text-center">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                        <svg class="w-8 h-8 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">{{ $t('affiliate.no_affiliates_yet') }}</h3>
                    <p class="text-gray-500 dark:text-gray-400">Share your registration link to attract affiliates.</p>
                    <p class="mt-3 text-sm text-indigo-600 dark:text-indigo-400">/partners/{{ program.slug }}/join</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
