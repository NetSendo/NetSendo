<script setup>
import { Head, Link } from "@inertiajs/vue3";
import PartnerLayout from "@/Layouts/PartnerLayout.vue";
import { useDateTime } from "@/Composables/useDateTime";
import { ref } from "vue";

const { formatCurrency: formatCurrencyBase } = useDateTime();
const props = defineProps({
    affiliate: Object,
    program: Object,
    directPartners: Array,
    teamStats: Object,
    referralUrl: String,
});

const formatCurrency = (value) => {
    return formatCurrencyBase(value, props.program?.currency || "PLN");
};

const copied = ref(false);
const copyReferralLink = async () => {
    try {
        await navigator.clipboard.writeText(props.referralUrl);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch (err) {
        console.error("Failed to copy:", err);
    }
};

// Toggle expanded state for nested partners
const expandedPartners = ref({});
const toggleExpand = (partnerId) => {
    expandedPartners.value[partnerId] = !expandedPartners.value[partnerId];
};
</script>

<template>
    <Head title="My Team" />

    <PartnerLayout>
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                    {{ $t("affiliate.my_team") || "My Team" }}
                </h1>
            </div>

            <!-- Invite Partners Card -->
            <div
                class="bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl p-6 text-white shadow-lg"
            >
                <div class="flex items-center gap-3 mb-4">
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
                                d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"
                            />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold">
                            {{
                                $t("affiliate.invite_partners") ||
                                "Invite Partners"
                            }}
                        </h2>
                        <p class="text-sm text-white/70">
                            {{
                                $t("affiliate.invite_partners_desc") ||
                                "Grow your team and earn commissions from their sales"
                            }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <input
                        type="text"
                        :value="referralUrl"
                        readonly
                        class="flex-1 px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-white text-sm"
                    />
                    <button
                        @click="copyReferralLink"
                        class="px-4 py-2 bg-white/20 hover:bg-white/30 rounded-lg text-sm font-medium transition-colors flex items-center gap-2"
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
                            copied
                                ? $t("affiliate.link_copied") || "Copied!"
                                : $t("affiliate.copy_link") || "Copy"
                        }}
                    </button>
                </div>
            </div>

            <!-- Team Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                <div
                    class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-200 dark:border-slate-700"
                >
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $t("affiliate.total_partners") || "Total Partners" }}
                    </p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ teamStats.total_partners }}
                    </p>
                </div>
                <div
                    class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-200 dark:border-slate-700"
                >
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{
                            $t("affiliate.direct_partners") || "Direct Partners"
                        }}
                    </p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ teamStats.direct_partners }}
                    </p>
                </div>
                <div
                    class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-200 dark:border-slate-700"
                >
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $t("affiliate.team_clicks") || "Team Clicks" }}
                    </p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ teamStats.total_clicks }}
                    </p>
                </div>
                <div
                    class="bg-white dark:bg-slate-800 rounded-xl p-5 border border-gray-200 dark:border-slate-700"
                >
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{
                            $t("affiliate.team_conversions") ||
                            "Team Conversions"
                        }}
                    </p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ teamStats.total_conversions }}
                    </p>
                </div>
                <div
                    class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl p-5 text-white"
                >
                    <p class="text-sm text-green-100">
                        {{ $t("affiliate.team_earnings") || "Team Earnings" }}
                    </p>
                    <p class="text-2xl font-bold">
                        {{ formatCurrency(teamStats.total_earnings) }}
                    </p>
                </div>
            </div>

            <!-- Partner Tree -->
            <div
                class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white mb-4"
                >
                    {{ $t("affiliate.partner_tree") || "Partner Tree" }}
                </h2>

                <div
                    v-if="directPartners.length === 0"
                    class="text-center py-12"
                >
                    <div
                        class="w-16 h-16 mx-auto mb-4 bg-gray-100 dark:bg-slate-700 rounded-full flex items-center justify-center"
                    >
                        <svg
                            class="w-8 h-8 text-gray-400"
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
                    </div>
                    <p class="text-gray-500 dark:text-gray-400">
                        {{
                            $t("affiliate.no_partners_yet") || "No partners yet"
                        }}
                    </p>
                    <p class="text-sm text-gray-400 dark:text-gray-500 mt-1">
                        {{
                            $t("affiliate.share_link_to_grow") ||
                            "Share your referral link to grow your team"
                        }}
                    </p>
                </div>

                <div v-else class="space-y-2">
                    <div
                        v-for="partner in directPartners"
                        :key="partner.id"
                        class="border border-gray-200 dark:border-slate-600 rounded-lg overflow-hidden"
                    >
                        <!-- Partner Row -->
                        <div
                            class="flex items-center justify-between p-4 cursor-pointer hover:bg-gray-50 dark:hover:bg-slate-700/50 transition-colors"
                            @click="toggleExpand(partner.id)"
                        >
                            <div class="flex items-center gap-3">
                                <button
                                    v-if="partner.children?.length > 0"
                                    class="w-6 h-6 flex items-center justify-center text-gray-400"
                                >
                                    <svg
                                        class="w-4 h-4 transition-transform"
                                        :class="{
                                            'rotate-90':
                                                expandedPartners[partner.id],
                                        }"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 5l7 7-7 7"
                                        />
                                    </svg>
                                </button>
                                <div v-else class="w-6"></div>
                                <div
                                    class="w-10 h-10 bg-indigo-100 dark:bg-indigo-900/30 rounded-full flex items-center justify-center text-indigo-600 dark:text-indigo-400 font-bold"
                                >
                                    {{ partner.name.charAt(0).toUpperCase() }}
                                </div>
                                <div>
                                    <p
                                        class="font-medium text-gray-900 dark:text-white"
                                    >
                                        {{ partner.name }}
                                    </p>
                                    <p
                                        class="text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{ partner.email }}
                                    </p>
                                </div>
                                <span
                                    class="ml-2 px-2 py-0.5 text-xs font-medium rounded-full"
                                    :class="
                                        partner.status === 'approved'
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                            : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'
                                    "
                                >
                                    {{ partner.status }}
                                </span>
                                <span
                                    v-if="partner.children?.length > 0"
                                    class="text-xs text-gray-400"
                                >
                                    ({{ partner.children.length }}
                                    {{
                                        $t("affiliate.sub_partners") ||
                                        "sub-partners"
                                    }})
                                </span>
                            </div>
                            <div class="flex items-center gap-6 text-sm">
                                <div class="text-center">
                                    <p class="text-gray-400 text-xs">Clicks</p>
                                    <p
                                        class="font-medium text-gray-900 dark:text-white"
                                    >
                                        {{ partner.clicks }}
                                    </p>
                                </div>
                                <div class="text-center">
                                    <p class="text-gray-400 text-xs">Conv.</p>
                                    <p
                                        class="font-medium text-gray-900 dark:text-white"
                                    >
                                        {{ partner.conversions }}
                                    </p>
                                </div>
                                <div class="text-center">
                                    <p class="text-gray-400 text-xs">Earned</p>
                                    <p
                                        class="font-medium text-green-600 dark:text-green-400"
                                    >
                                        {{ formatCurrency(partner.earnings) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Nested Partners (Level 2) -->
                        <div
                            v-if="
                                expandedPartners[partner.id] &&
                                partner.children?.length > 0
                            "
                            class="bg-gray-50 dark:bg-slate-700/30 border-t border-gray-200 dark:border-slate-600"
                        >
                            <div
                                v-for="child in partner.children"
                                :key="child.id"
                                class="flex items-center justify-between p-3 pl-16 border-b last:border-b-0 border-gray-100 dark:border-slate-600"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="w-8 h-8 bg-purple-100 dark:bg-purple-900/30 rounded-full flex items-center justify-center text-purple-600 dark:text-purple-400 font-bold text-sm"
                                    >
                                        {{ child.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <div>
                                        <p
                                            class="font-medium text-gray-900 dark:text-white text-sm"
                                        >
                                            {{ child.name }}
                                        </p>
                                        <p
                                            class="text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            {{ child.email }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4 text-xs">
                                    <span>{{ child.clicks }} clicks</span>
                                    <span>{{ child.conversions }} conv.</span>
                                    <span
                                        class="text-green-600 dark:text-green-400 font-medium"
                                        >{{
                                            formatCurrency(child.earnings)
                                        }}</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </PartnerLayout>
</template>
