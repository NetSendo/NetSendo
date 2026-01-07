<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    affiliate: Object,
    stats: Object,
    recentConversions: Array,
});

const formatCurrency = (value) => {
    return new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(value || 0);
};

const approveAffiliate = () => {
    router.post(route('affiliate.affiliates.approve', props.affiliate.id));
};

const blockAffiliate = () => {
    if (confirm('Block this affiliate?')) {
        router.post(route('affiliate.affiliates.block', props.affiliate.id));
    }
};
</script>

<template>
    <Head :title="affiliate.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('affiliate.affiliates.index')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </Link>
                    <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ affiliate.name }}</h2>
                </div>
                <div class="flex gap-2">
                    <button
                        v-if="affiliate.status === 'pending'"
                        @click="approveAffiliate"
                        class="px-4 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700"
                    >
                        Approve
                    </button>
                    <button
                        v-if="affiliate.status === 'approved'"
                        @click="blockAffiliate"
                        class="px-4 py-2 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700"
                    >
                        Block
                    </button>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Profile Card -->
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                    <div class="flex items-start gap-6">
                        <div class="w-20 h-20 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
                            <span class="text-white font-bold text-2xl">{{ affiliate.name?.charAt(0).toUpperCase() }}</span>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white">{{ affiliate.name }}</h3>
                                <span
                                    class="px-2 py-1 text-xs rounded-full"
                                    :class="{
                                        'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400': affiliate.status === 'approved',
                                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400': affiliate.status === 'pending',
                                        'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400': affiliate.status === 'blocked',
                                    }"
                                >
                                    {{ $t(`affiliate.status_${affiliate.status}`) }}
                                </span>
                            </div>
                            <p class="text-gray-600 dark:text-gray-400">{{ affiliate.email }}</p>
                            <div class="mt-3 flex flex-wrap gap-4 text-sm text-gray-500 dark:text-gray-400">
                                <div v-if="affiliate.company_name">
                                    <span class="font-medium">Company:</span> {{ affiliate.company_name }}
                                </div>
                                <div>
                                    <span class="font-medium">Referral Code:</span>
                                    <code class="ml-1 px-2 py-0.5 bg-gray-100 dark:bg-slate-700 rounded text-indigo-600 dark:text-indigo-400">{{ affiliate.referral_code }}</code>
                                </div>
                                <div>
                                    <span class="font-medium">Joined:</span> {{ new Date(affiliate.joined_at).toLocaleDateString() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-200 dark:border-slate-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Clicks</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.clicks }}</p>
                    </div>
                    <div class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-200 dark:border-slate-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Leads</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.leads }}</p>
                    </div>
                    <div class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-200 dark:border-slate-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Purchases</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.purchases }}</p>
                    </div>
                    <div class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-200 dark:border-slate-700">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total Earned</p>
                        <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ formatCurrency(stats.total_earned) }}</p>
                    </div>
                </div>

                <!-- Earnings Breakdown -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-xl p-5 border border-yellow-200 dark:border-yellow-800">
                        <p class="text-sm text-yellow-700 dark:text-yellow-400">Pending</p>
                        <p class="text-xl font-bold text-yellow-800 dark:text-yellow-300">{{ formatCurrency(stats.pending) }}</p>
                    </div>
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-5 border border-blue-200 dark:border-blue-800">
                        <p class="text-sm text-blue-700 dark:text-blue-400">Revenue Generated</p>
                        <p class="text-xl font-bold text-blue-800 dark:text-blue-300">{{ formatCurrency(stats.total_revenue) }}</p>
                    </div>
                    <div class="bg-green-50 dark:bg-green-900/20 rounded-xl p-5 border border-green-200 dark:border-green-800">
                        <p class="text-sm text-green-700 dark:text-green-400">Paid Out</p>
                        <p class="text-xl font-bold text-green-800 dark:text-green-300">{{ formatCurrency(stats.paid) }}</p>
                    </div>
                </div>

                <!-- Recent Conversions -->
                <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Recent Conversions</h3>
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
                                <p class="font-medium text-gray-900 dark:text-white">{{ conv.type === 'purchase' ? formatCurrency(conv.amount) : '-' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ new Date(conv.created_at).toLocaleDateString() }}</p>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-center text-gray-500 dark:text-gray-400 py-6">No conversions yet</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
