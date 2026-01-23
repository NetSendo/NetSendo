<script setup>
import { Head } from "@inertiajs/vue3";
import PartnerLayout from "@/Layouts/PartnerLayout.vue";
import { useDateTime } from "@/Composables/useDateTime";
import { ref } from "vue";

const { formatCurrency: formatCurrencyBase } = useDateTime();
const props = defineProps({
    stats: Object,
    recentConversions: Array,
    clicksChart: Array,
    referralUrl: String,
    referralCode: String,
    referredUsersCount: Number,
});

const formatCurrency = (value) => {
    return formatCurrencyBase(value, "PLN");
};

// Copy functionality
const copiedLink = ref(false);
const copiedCode = ref(false);

const copyToClipboard = async (text, type) => {
    try {
        await navigator.clipboard.writeText(text);
        if (type === "link") {
            copiedLink.value = true;
            setTimeout(() => (copiedLink.value = false), 2000);
        } else {
            copiedCode.value = true;
            setTimeout(() => (copiedCode.value = false), 2000);
        }
    } catch (err) {
        console.error("Failed to copy:", err);
    }
};
</script>

<template>
    <Head title="Dashboard" />

    <PartnerLayout>
        <div class="space-y-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                Dashboard
            </h1>

            <!-- Referral Tools Card -->
            <div
                class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl p-6 text-white shadow-lg"
            >
                <h2 class="text-lg font-semibold mb-4 flex items-center gap-2">
                    <svg
                        class="w-5 h-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"
                        />
                    </svg>
                    {{
                        $t("affiliate.your_referral_tools") ||
                        "Your Referral Tools"
                    }}
                </h2>

                <div class="grid md:grid-cols-2 gap-4">
                    <!-- Referral Link -->
                    <div class="bg-white/10 backdrop-blur rounded-lg p-4">
                        <label class="block text-sm text-white/70 mb-2">
                            {{
                                $t("affiliate.your_referral_link") ||
                                "Your Referral Link"
                            }}
                        </label>
                        <div class="flex items-center gap-2">
                            <input
                                type="text"
                                :value="referralUrl"
                                readonly
                                class="flex-1 px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white text-sm"
                            />
                            <button
                                @click="copyToClipboard(referralUrl, 'link')"
                                class="px-4 py-2 bg-white/20 hover:bg-white/30 rounded-lg text-sm font-medium transition-colors flex items-center gap-2"
                            >
                                <svg
                                    v-if="!copiedLink"
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
                                    class="w-4 h-4 text-green-300"
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
                                {{
                                    copiedLink
                                        ? $t("affiliate.link_copied") ||
                                          "Copied!"
                                        : $t("affiliate.copy_link") || "Copy"
                                }}
                            </button>
                        </div>
                    </div>

                    <!-- Referral Code -->
                    <div class="bg-white/10 backdrop-blur rounded-lg p-4">
                        <label class="block text-sm text-white/70 mb-2">
                            {{
                                $t("affiliate.your_referral_code") ||
                                "Your Referral Code"
                            }}
                        </label>
                        <div class="flex items-center gap-2">
                            <div
                                class="flex-1 px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white text-lg font-mono tracking-wider"
                            >
                                {{ referralCode }}
                            </div>
                            <button
                                @click="copyToClipboard(referralCode, 'code')"
                                class="px-4 py-2 bg-white/20 hover:bg-white/30 rounded-lg text-sm font-medium transition-colors flex items-center gap-2"
                            >
                                <svg
                                    v-if="!copiedCode"
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
                                    class="w-4 h-4 text-green-300"
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
                                {{
                                    copiedCode
                                        ? $t("affiliate.link_copied") ||
                                          "Copied!"
                                        : $t("affiliate.copy_code") || "Copy"
                                }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Referred Users Count -->
                <div
                    v-if="referredUsersCount > 0"
                    class="mt-4 pt-4 border-t border-white/20"
                >
                    <div class="flex items-center gap-2 text-white/80">
                        <svg
                            class="w-5 h-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                            />
                        </svg>
                        <span>
                            {{
                                $t("affiliate.referred_signups") ||
                                "Referred Signups"
                            }}:
                            <strong class="text-white">{{
                                referredUsersCount
                            }}</strong>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div
                    class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-200 dark:border-slate-700"
                >
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Clicks (30d)
                    </p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ stats.clicks }}
                    </p>
                </div>
                <div
                    class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-200 dark:border-slate-700"
                >
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Leads (30d)
                    </p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ stats.leads }}
                    </p>
                </div>
                <div
                    class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-200 dark:border-slate-700"
                >
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Sales (30d)
                    </p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ stats.sales }}
                    </p>
                </div>
                <div
                    class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl p-5 text-white"
                >
                    <p class="text-sm text-green-100">Earnings (30d)</p>
                    <p class="text-2xl font-bold">
                        {{ formatCurrency(stats.earnings) }}
                    </p>
                </div>
            </div>

            <!-- Earnings Summary -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div
                    class="bg-yellow-50 dark:bg-yellow-900/20 rounded-xl p-5 border border-yellow-200 dark:border-yellow-800"
                >
                    <p class="text-sm text-yellow-700 dark:text-yellow-400">
                        Pending
                    </p>
                    <p
                        class="text-xl font-bold text-yellow-800 dark:text-yellow-300"
                    >
                        {{ formatCurrency(stats.pending) }}
                    </p>
                </div>
                <div
                    class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-5 border border-blue-200 dark:border-blue-800"
                >
                    <p class="text-sm text-blue-700 dark:text-blue-400">
                        Ready for Payout
                    </p>
                    <p
                        class="text-xl font-bold text-blue-800 dark:text-blue-300"
                    >
                        {{ formatCurrency(stats.payable) }}
                    </p>
                </div>
                <div
                    class="bg-green-50 dark:bg-green-900/20 rounded-xl p-5 border border-green-200 dark:border-green-800"
                >
                    <p class="text-sm text-green-700 dark:text-green-400">
                        Total Paid
                    </p>
                    <p
                        class="text-xl font-bold text-green-800 dark:text-green-300"
                    >
                        {{ formatCurrency(stats.paid) }}
                    </p>
                </div>
            </div>

            <!-- Recent Conversions -->
            <div
                class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white mb-4"
                >
                    Recent Conversions
                </h2>
                <div v-if="recentConversions?.length" class="space-y-3">
                    <div
                        v-for="conv in recentConversions"
                        :key="conv.id"
                        class="flex items-center justify-between p-3 rounded-lg bg-gray-50 dark:bg-slate-700/50"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-xs font-bold"
                                :class="
                                    conv.type === 'purchase'
                                        ? 'bg-green-500'
                                        : 'bg-blue-500'
                                "
                            >
                                {{ conv.type === "purchase" ? "$" : "L" }}
                            </span>
                            <div>
                                <p
                                    class="font-medium text-gray-900 dark:text-white capitalize"
                                >
                                    {{ conv.type }}
                                </p>
                                <p
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ conv.offer?.name }}
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p
                                class="font-medium text-gray-900 dark:text-white"
                            >
                                {{
                                    conv.type === "purchase"
                                        ? formatCurrency(conv.amount)
                                        : "-"
                                }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{
                                    new Date(
                                        conv.created_at,
                                    ).toLocaleDateString()
                                }}
                            </p>
                        </div>
                    </div>
                </div>
                <p
                    v-else
                    class="text-center text-gray-500 dark:text-gray-400 py-6"
                >
                    No conversions yet. Start sharing your links!
                </p>
            </div>
        </div>
    </PartnerLayout>
</template>
