<script setup>
import { ref, computed } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { useDateTime } from "@/Composables/useDateTime";

const { formatCurrency: formatCurrencyBase, formatNumber: formatNumberBase } =
    useDateTime();
const copied = ref(false);

const props = defineProps({
    hasProgram: Boolean,
    program: Object,
    stats: Object,
});

const formatCurrency = (value, currency = "PLN") => {
    return formatCurrencyBase(value, currency);
};

const formatNumber = (value) => {
    return formatNumberBase(value);
};

const fullRegistrationUrl = computed(() => {
    if (!props.program?.slug) return "";
    return `${window.location.origin}/partners/${props.program.slug}/join`;
});

const copyRegistrationLink = async () => {
    try {
        await navigator.clipboard.writeText(fullRegistrationUrl.value);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch (err) {
        console.error("Failed to copy:", err);
    }
};

const loginAsPartner = () => {
    if (props.program?.id) {
        router.post(
            route("affiliate.programs.login-as-partner", props.program.id),
        );
    }
};
</script>

<template>
    <Head :title="$t('affiliate.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2
                    class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
                >
                    {{ $t("affiliate.title") }}
                </h2>
                <div v-if="hasProgram" class="flex gap-2">
                    <button
                        @click="loginAsPartner"
                        class="inline-flex items-center px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700 transition-colors"
                    >
                        <svg
                            class="w-4 h-4 mr-2"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                            />
                        </svg>
                        {{ $t("affiliate.view_as_partner") }}
                    </button>
                    <Link
                        :href="route('affiliate.programs.index')"
                        class="inline-flex items-center px-4 py-2 bg-slate-600 text-white text-sm font-medium rounded-lg hover:bg-slate-700 transition-colors"
                    >
                        {{ $t("affiliate.manage_programs") }}
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- No Program Setup -->
                <div
                    v-if="!hasProgram"
                    class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl p-8 text-center"
                >
                    <div class="max-w-md mx-auto">
                        <div
                            class="w-20 h-20 mx-auto mb-6 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center"
                        >
                            <svg
                                class="w-10 h-10 text-white"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"
                                />
                            </svg>
                        </div>
                        <h3
                            class="text-2xl font-bold text-gray-900 dark:text-white mb-3"
                        >
                            {{ $t("affiliate.no_program_title") }}
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-6">
                            {{ $t("affiliate.no_program_description") }}
                        </p>
                        <Link
                            :href="route('affiliate.programs.create')"
                            class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-semibold rounded-xl hover:from-indigo-700 hover:to-purple-700 transition-all shadow-lg hover:shadow-xl"
                        >
                            <svg
                                class="w-5 h-5 mr-2"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 4v16m8-8H4"
                                />
                            </svg>
                            {{ $t("affiliate.create_first_program") }}
                        </Link>
                    </div>
                </div>

                <!-- Dashboard Content -->
                <div v-else class="space-y-6">
                    <!-- KPI Cards -->
                    <div
                        class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4"
                    >
                        <!-- Total Revenue -->
                        <div
                            class="bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl p-6 text-white shadow-lg"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <p
                                        class="text-emerald-100 text-sm font-medium"
                                    >
                                        {{ $t("affiliate.total_revenue") }}
                                    </p>
                                    <p class="text-3xl font-bold mt-1">
                                        {{
                                            formatCurrency(
                                                stats.total_revenue,
                                                program.currency,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center"
                                >
                                    <svg
                                        class="w-6 h-6"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Total Commissions -->
                        <div
                            class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl p-6 text-white shadow-lg"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <p
                                        class="text-blue-100 text-sm font-medium"
                                    >
                                        {{ $t("affiliate.total_commissions") }}
                                    </p>
                                    <p class="text-3xl font-bold mt-1">
                                        {{
                                            formatCurrency(
                                                stats.total_commissions,
                                                program.currency,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center"
                                >
                                    <svg
                                        class="w-6 h-6"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"
                                        />
                                    </svg>
                                </div>
                            </div>
                            <div class="mt-3 text-sm text-blue-100">
                                {{ $t("affiliate.pending") }}:
                                {{
                                    formatCurrency(
                                        stats.pending_commissions,
                                        program.currency,
                                    )
                                }}
                            </div>
                        </div>

                        <!-- Active Affiliates -->
                        <div
                            class="bg-gradient-to-br from-purple-500 to-pink-600 rounded-2xl p-6 text-white shadow-lg"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <p
                                        class="text-purple-100 text-sm font-medium"
                                    >
                                        {{ $t("affiliate.active_affiliates") }}
                                    </p>
                                    <p class="text-3xl font-bold mt-1">
                                        {{
                                            formatNumber(
                                                stats.active_affiliates,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center"
                                >
                                    <svg
                                        class="w-6 h-6"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"
                                        />
                                    </svg>
                                </div>
                            </div>
                            <div
                                v-if="stats.pending_affiliates > 0"
                                class="mt-3 text-sm text-purple-100"
                            >
                                {{ stats.pending_affiliates }}
                                {{ $t("affiliate.pending_approval") }}
                            </div>
                        </div>

                        <!-- Conversion Rate / EPC -->
                        <div
                            class="bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl p-6 text-white shadow-lg"
                        >
                            <div class="flex items-center justify-between">
                                <div>
                                    <p
                                        class="text-amber-100 text-sm font-medium"
                                    >
                                        {{ $t("affiliate.conversion_rate") }}
                                    </p>
                                    <p class="text-3xl font-bold mt-1">
                                        {{ stats.conversion_rate }}%
                                    </p>
                                </div>
                                <div
                                    class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center"
                                >
                                    <svg
                                        class="w-6 h-6"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
                                        />
                                    </svg>
                                </div>
                            </div>
                            <div class="mt-3 text-sm text-amber-100">
                                EPC:
                                {{
                                    formatCurrency(stats.epc, program.currency)
                                }}
                            </div>
                        </div>
                    </div>

                    <!-- Stats Row -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div
                            class="bg-white dark:bg-slate-800 rounded-xl p-5 shadow-sm border border-gray-200 dark:border-slate-700"
                        >
                            <div class="flex items-center gap-4">
                                <div
                                    class="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center"
                                >
                                    <svg
                                        class="w-5 h-5 text-blue-600 dark:text-blue-400"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"
                                        />
                                    </svg>
                                </div>
                                <div>
                                    <p
                                        class="text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        {{ $t("affiliate.total_clicks") }}
                                    </p>
                                    <p
                                        class="text-2xl font-bold text-gray-900 dark:text-white"
                                    >
                                        {{ formatNumber(stats.total_clicks) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="bg-white dark:bg-slate-800 rounded-xl p-5 shadow-sm border border-gray-200 dark:border-slate-700"
                        >
                            <div class="flex items-center gap-4">
                                <div
                                    class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center"
                                >
                                    <svg
                                        class="w-5 h-5 text-green-600 dark:text-green-400"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                </div>
                                <div>
                                    <p
                                        class="text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        {{ $t("affiliate.total_leads") }}
                                    </p>
                                    <p
                                        class="text-2xl font-bold text-gray-900 dark:text-white"
                                    >
                                        {{ formatNumber(stats.total_leads) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="bg-white dark:bg-slate-800 rounded-xl p-5 shadow-sm border border-gray-200 dark:border-slate-700"
                        >
                            <div class="flex items-center gap-4">
                                <div
                                    class="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center"
                                >
                                    <svg
                                        class="w-5 h-5 text-purple-600 dark:text-purple-400"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"
                                        />
                                    </svg>
                                </div>
                                <div>
                                    <p
                                        class="text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        {{ $t("affiliate.total_purchases") }}
                                    </p>
                                    <p
                                        class="text-2xl font-bold text-gray-900 dark:text-white"
                                    >
                                        {{
                                            formatNumber(stats.total_purchases)
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions & Top Affiliates -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Quick Actions -->
                        <div
                            class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6"
                        >
                            <h3
                                class="text-lg font-semibold text-gray-900 dark:text-white mb-4"
                            >
                                {{ $t("affiliate.quick_actions") }}
                            </h3>
                            <div class="grid grid-cols-2 gap-3">
                                <Link
                                    :href="route('affiliate.offers.index')"
                                    class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors"
                                >
                                    <div
                                        class="w-10 h-10 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center"
                                    >
                                        <svg
                                            class="w-5 h-5 text-indigo-600 dark:text-indigo-400"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"
                                            />
                                        </svg>
                                    </div>
                                    <div>
                                        <p
                                            class="font-medium text-gray-900 dark:text-white text-sm"
                                        >
                                            {{ $t("affiliate.offers") }}
                                        </p>
                                        <p
                                            class="text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            {{ $t("affiliate.manage_offers") }}
                                        </p>
                                    </div>
                                </Link>

                                <Link
                                    :href="route('affiliate.affiliates.index')"
                                    class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors"
                                >
                                    <div
                                        class="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center"
                                    >
                                        <svg
                                            class="w-5 h-5 text-purple-600 dark:text-purple-400"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
                                            />
                                        </svg>
                                    </div>
                                    <div>
                                        <p
                                            class="font-medium text-gray-900 dark:text-white text-sm"
                                        >
                                            {{ $t("affiliate.affiliates") }}
                                        </p>
                                        <p
                                            class="text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            {{
                                                $t(
                                                    "affiliate.manage_affiliates",
                                                )
                                            }}
                                        </p>
                                    </div>
                                </Link>

                                <Link
                                    :href="route('affiliate.commissions.index')"
                                    class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors"
                                >
                                    <div
                                        class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center"
                                    >
                                        <svg
                                            class="w-5 h-5 text-green-600 dark:text-green-400"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
                                            />
                                        </svg>
                                    </div>
                                    <div>
                                        <p
                                            class="font-medium text-gray-900 dark:text-white text-sm"
                                        >
                                            {{ $t("affiliate.commissions") }}
                                        </p>
                                        <p
                                            class="text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            {{
                                                $t(
                                                    "affiliate.review_commissions",
                                                )
                                            }}
                                        </p>
                                    </div>
                                </Link>

                                <Link
                                    :href="route('affiliate.payouts.index')"
                                    class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-700 transition-colors"
                                >
                                    <div
                                        class="w-10 h-10 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center"
                                    >
                                        <svg
                                            class="w-5 h-5 text-amber-600 dark:text-amber-400"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"
                                            />
                                        </svg>
                                    </div>
                                    <div>
                                        <p
                                            class="font-medium text-gray-900 dark:text-white text-sm"
                                        >
                                            {{ $t("affiliate.payouts") }}
                                        </p>
                                        <p
                                            class="text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            {{ $t("affiliate.manage_payouts") }}
                                        </p>
                                    </div>
                                </Link>
                            </div>
                        </div>

                        <!-- Top Affiliates -->
                        <div
                            class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6"
                        >
                            <h3
                                class="text-lg font-semibold text-gray-900 dark:text-white mb-4"
                            >
                                {{ $t("affiliate.top_affiliates") }}
                            </h3>
                            <div
                                v-if="stats.top_affiliates?.length > 0"
                                class="space-y-3"
                            >
                                <div
                                    v-for="(
                                        affiliate, index
                                    ) in stats.top_affiliates"
                                    :key="affiliate.id"
                                    class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-slate-700/50"
                                >
                                    <div
                                        class="w-8 h-8 rounded-full flex items-center justify-center text-white font-bold text-sm"
                                        :class="{
                                            'bg-gradient-to-br from-yellow-400 to-amber-500':
                                                index === 0,
                                            'bg-gradient-to-br from-gray-300 to-gray-400':
                                                index === 1,
                                            'bg-gradient-to-br from-amber-600 to-orange-700':
                                                index === 2,
                                            'bg-gray-400': index > 2,
                                        }"
                                    >
                                        {{ index + 1 }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p
                                            class="font-medium text-gray-900 dark:text-white text-sm truncate"
                                        >
                                            {{ affiliate.name }}
                                        </p>
                                        <p
                                            class="text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            {{ affiliate.conversions }}
                                            {{ $t("affiliate.conversions") }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p
                                            class="font-semibold text-gray-900 dark:text-white text-sm"
                                        >
                                            {{
                                                formatCurrency(
                                                    affiliate.earnings,
                                                    program.currency,
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div
                                v-else
                                class="text-center py-8 text-gray-500 dark:text-gray-400"
                            >
                                {{ $t("affiliate.no_affiliates_yet") }}
                            </div>
                        </div>
                    </div>

                    <!-- Program Info -->
                    <div
                        class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6"
                    >
                        <div class="flex items-center justify-between mb-4">
                            <h3
                                class="text-lg font-semibold text-gray-900 dark:text-white"
                            >
                                {{ $t("affiliate.program_info") }}
                            </h3>
                            <Link
                                :href="
                                    route('affiliate.programs.edit', program.id)
                                "
                                class="text-indigo-600 dark:text-indigo-400 hover:underline text-sm"
                            >
                                {{ $t("common.edit") }}
                            </Link>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <p
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{ $t("affiliate.program_name") }}
                                </p>
                                <p
                                    class="font-medium text-gray-900 dark:text-white"
                                >
                                    {{ program.name }}
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{ $t("affiliate.cookie_days") }}
                                </p>
                                <p
                                    class="font-medium text-gray-900 dark:text-white"
                                >
                                    {{ program.cookie_days }}
                                    {{ $t("affiliate.days") }}
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{ $t("affiliate.default_commission") }}
                                </p>
                                <p
                                    class="font-medium text-gray-900 dark:text-white"
                                >
                                    {{ program.default_commission_percent }}%
                                </p>
                            </div>
                            <div class="md:col-span-2">
                                <p
                                    class="text-sm text-gray-500 dark:text-gray-400 mb-2"
                                >
                                    {{ $t("affiliate.registration_link") }}
                                </p>
                                <div class="flex items-center gap-2">
                                    <div
                                        class="flex-1 min-w-0 bg-gray-100 dark:bg-slate-700 rounded-lg px-3 py-2"
                                    >
                                        <p
                                            class="font-medium text-indigo-600 dark:text-indigo-400 text-sm truncate"
                                            :title="fullRegistrationUrl"
                                        >
                                            {{ fullRegistrationUrl }}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        @click="copyRegistrationLink"
                                        class="flex items-center gap-1.5 px-3 py-2 bg-slate-600 text-white text-sm font-medium rounded-lg hover:bg-slate-700 transition-colors"
                                        :title="$t('common.copy')"
                                    >
                                        <svg
                                            v-if="!copied"
                                            class="w-4 h-4"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                                            />
                                        </svg>
                                        <svg
                                            v-else
                                            class="w-4 h-4 text-green-400"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M5 13l4 4L19 7"
                                            />
                                        </svg>
                                        <span class="hidden sm:inline">{{
                                            copied
                                                ? $t("common.copied")
                                                : $t("common.copy")
                                        }}</span>
                                    </button>
                                    <a
                                        :href="fullRegistrationUrl"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="flex items-center gap-1.5 px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors"
                                        :title="$t('common.open_in_new_tab')"
                                    >
                                        <svg
                                            class="w-4 h-4"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                                            />
                                        </svg>
                                        <span class="hidden sm:inline">{{
                                            $t("common.open")
                                        }}</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
