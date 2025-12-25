<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { ref, computed } from "vue";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const props = defineProps({
    plan: Object,
    messageTypes: Object,
    conditionTriggers: Object,
    licensePlan: {
        type: String,
        default: "SILVER",
    },
});

const getMessageTypeIcon = (type) => {
    const icons = {
        educational: "📚",
        sales: "💰",
        reminder: "⏰",
        social_proof: "⭐",
        follow_up: "📤",
        onboarding: "👋",
        reengagement: "🔄",
        announcement: "📢",
        thank_you: "🙏",
        survey: "📊",
    };
    return icons[type] || "📧";
};

const goBack = () => {
    router.visit(route("campaign-architect.index"));
};

const goToMessages = () => {
    router.visit(route("messages.index"));
};
</script>

<template>
    <Head :title="plan.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <button
                        @click="goBack"
                        class="text-gray-500 hover:text-gray-700 dark:text-gray-400"
                    >
                        <svg
                            class="h-6 w-6"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18"
                            />
                        </svg>
                    </button>
                    <h2
                        class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
                    >
                        🎯 {{ plan.name }}
                    </h2>
                </div>
                <span
                    :class="[
                        'rounded-full px-3 py-1 text-xs font-bold',
                        plan.status === 'exported'
                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'
                            : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
                    ]"
                >
                    {{ plan.status }}
                </span>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <!-- Strategy Summary -->
                <div
                    v-if="plan.strategy?.summary"
                    class="mb-6 rounded-xl bg-gradient-to-r from-indigo-500 to-purple-600 p-6 text-white"
                >
                    <h3 class="mb-2 text-xl font-bold">
                        {{ $t("campaign_architect.strategy_summary") }}
                    </h3>
                    <p>{{ plan.strategy.summary }}</p>
                </div>

                <!-- SILVER vs GOLD Comparison -->
                <div
                    :class="[
                        'mb-8 rounded-xl border-2 p-6',
                        licensePlan === 'GOLD'
                            ? 'border-amber-400 bg-gradient-to-r from-amber-50 to-yellow-50 dark:from-amber-900/20 dark:to-yellow-900/20'
                            : 'border-indigo-300 bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20',
                    ]"
                >
                    <!-- GOLD License Message -->
                    <div
                        v-if="licensePlan === 'GOLD'"
                        class="flex items-start gap-4"
                    >
                        <div class="flex-shrink-0">
                            <span class="text-4xl">👑</span>
                        </div>
                        <div>
                            <h4
                                class="text-lg font-bold text-amber-700 dark:text-amber-400"
                            >
                                {{ $t("campaign_architect.gold_active_title") }}
                            </h4>
                            <p class="mt-2 text-gray-700 dark:text-gray-300">
                                {{ $t("campaign_architect.gold_active_desc") }}
                            </p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-amber-200 px-3 py-1 text-sm font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                                >
                                    ✅
                                    {{
                                        $t(
                                            "campaign_architect.feature_unlimited_campaigns"
                                        )
                                    }}
                                </span>
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-amber-200 px-3 py-1 text-sm font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                                >
                                    ✅
                                    {{
                                        $t(
                                            "campaign_architect.feature_advanced_ai"
                                        )
                                    }}
                                </span>
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-amber-200 px-3 py-1 text-sm font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                                >
                                    ✅
                                    {{
                                        $t(
                                            "campaign_architect.feature_ab_testing"
                                        )
                                    }}
                                </span>
                                <span
                                    class="inline-flex items-center gap-1 rounded-full bg-amber-200 px-3 py-1 text-sm font-medium text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                                >
                                    ✅
                                    {{
                                        $t(
                                            "campaign_architect.feature_priority_support"
                                        )
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- SILVER License Upgrade Message -->
                    <div
                        v-else
                        class="flex flex-col lg:flex-row lg:items-start lg:gap-6"
                    >
                        <div class="flex-1">
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0">
                                    <span class="text-4xl">🚀</span>
                                </div>
                                <div>
                                    <h4
                                        class="text-lg font-bold text-indigo-700 dark:text-indigo-400"
                                    >
                                        {{
                                            $t(
                                                "campaign_architect.upgrade_title"
                                            )
                                        }}
                                    </h4>
                                    <p
                                        class="mt-2 text-gray-700 dark:text-gray-300"
                                    >
                                        {{
                                            $t(
                                                "campaign_architect.upgrade_desc"
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>

                            <!-- Features Comparison -->
                            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                                <!-- SILVER Features -->
                                <div
                                    class="rounded-lg bg-gray-100 p-4 dark:bg-gray-800"
                                >
                                    <h5
                                        class="mb-3 flex items-center gap-2 font-semibold text-gray-700 dark:text-gray-300"
                                    >
                                        🥈 SILVER
                                        <span class="text-xs text-gray-500"
                                            >({{
                                                $t(
                                                    "campaign_architect.current_plan"
                                                )
                                            }})</span
                                        >
                                    </h5>
                                    <ul class="space-y-2 text-sm">
                                        <li
                                            class="flex items-center gap-2 text-gray-600 dark:text-gray-400"
                                        >
                                            <span class="text-green-500"
                                                >✓</span
                                            >
                                            {{
                                                $t(
                                                    "campaign_architect.silver_basic_ai"
                                                )
                                            }}
                                        </li>
                                        <li
                                            class="flex items-center gap-2 text-gray-600 dark:text-gray-400"
                                        >
                                            <span class="text-green-500"
                                                >✓</span
                                            >
                                            {{
                                                $t(
                                                    "campaign_architect.silver_3_campaigns"
                                                )
                                            }}
                                        </li>
                                        <li
                                            class="flex items-center gap-2 text-gray-600 dark:text-gray-400"
                                        >
                                            <span class="text-red-400">✗</span>
                                            {{
                                                $t(
                                                    "campaign_architect.silver_no_ab"
                                                )
                                            }}
                                        </li>
                                        <li
                                            class="flex items-center gap-2 text-gray-600 dark:text-gray-400"
                                        >
                                            <span class="text-red-400">✗</span>
                                            {{
                                                $t(
                                                    "campaign_architect.silver_no_advanced"
                                                )
                                            }}
                                        </li>
                                    </ul>
                                </div>

                                <!-- GOLD Features -->
                                <div
                                    class="rounded-lg bg-gradient-to-br from-amber-100 to-yellow-100 p-4 ring-2 ring-amber-400 dark:from-amber-900/30 dark:to-yellow-900/30"
                                >
                                    <h5
                                        class="mb-3 flex items-center gap-2 font-semibold text-amber-700 dark:text-amber-400"
                                    >
                                        👑 GOLD
                                        <span
                                            class="ml-1 rounded bg-amber-500 px-2 py-0.5 text-xs font-bold text-white"
                                            >PRO</span
                                        >
                                    </h5>
                                    <ul class="space-y-2 text-sm">
                                        <li
                                            class="flex items-center gap-2 text-gray-700 dark:text-gray-300"
                                        >
                                            <span class="text-green-500"
                                                >✓</span
                                            >
                                            {{
                                                $t(
                                                    "campaign_architect.gold_advanced_ai"
                                                )
                                            }}
                                        </li>
                                        <li
                                            class="flex items-center gap-2 text-gray-700 dark:text-gray-300"
                                        >
                                            <span class="text-green-500"
                                                >✓</span
                                            >
                                            {{
                                                $t(
                                                    "campaign_architect.gold_unlimited"
                                                )
                                            }}
                                        </li>
                                        <li
                                            class="flex items-center gap-2 text-gray-700 dark:text-gray-300"
                                        >
                                            <span class="text-green-500"
                                                >✓</span
                                            >
                                            {{
                                                $t(
                                                    "campaign_architect.gold_ab_testing"
                                                )
                                            }}
                                        </li>
                                        <li
                                            class="flex items-center gap-2 text-gray-700 dark:text-gray-300"
                                        >
                                            <span class="text-green-500"
                                                >✓</span
                                            >
                                            {{
                                                $t(
                                                    "campaign_architect.gold_priority"
                                                )
                                            }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Upgrade CTA -->
                        <div class="mt-6 flex-shrink-0 lg:mt-0">
                            <Link
                                :href="route('license.index')"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-amber-500 to-yellow-500 px-6 py-4 text-lg font-bold text-white shadow-lg transition-all hover:from-amber-600 hover:to-yellow-600 hover:shadow-xl"
                            >
                                👑
                                {{ $t("campaign_architect.upgrade_to_gold") }}
                            </Link>
                            <p
                                class="mt-2 text-center text-xs text-gray-500 dark:text-gray-400"
                            >
                                {{ $t("campaign_architect.upgrade_price") }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Plan Details -->
                <div class="mb-8 grid grid-cols-2 gap-4 md:grid-cols-4">
                    <div
                        class="rounded-lg bg-white p-4 shadow dark:bg-gray-800"
                    >
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $t("campaign_architect.industry") }}
                        </p>
                        <p
                            class="text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            {{ plan.industry }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg bg-white p-4 shadow dark:bg-gray-800"
                    >
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $t("campaign_architect.campaign_goal") }}
                        </p>
                        <p
                            class="text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            {{ plan.campaign_goal }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg bg-white p-4 shadow dark:bg-gray-800"
                    >
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $t("common.messages") }}
                        </p>
                        <p
                            class="text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            {{ plan.steps?.length || 0 }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg bg-white p-4 shadow dark:bg-gray-800"
                    >
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $t("campaign_architect.campaign_language") }}
                        </p>
                        <p
                            class="text-lg font-semibold text-gray-900 dark:text-white"
                        >
                            {{ plan.campaign_language?.toUpperCase() || "EN" }}
                        </p>
                    </div>
                </div>

                <!-- Campaign Steps -->
                <div
                    class="rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-800"
                >
                    <h4
                        class="mb-6 text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        📋 {{ $t("campaign_architect.campaign_blueprint") }}
                    </h4>

                    <div class="space-y-4">
                        <div
                            v-for="(step, index) in plan.steps"
                            :key="step.id"
                            class="relative"
                        >
                            <!-- Timeline connector -->
                            <div
                                v-if="index < plan.steps.length - 1"
                                class="absolute left-6 top-16 h-full w-0.5 bg-gray-200 dark:bg-gray-700"
                            ></div>

                            <div class="flex gap-4">
                                <!-- Step number -->
                                <div
                                    class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-gradient-to-r from-indigo-500 to-purple-600 text-lg font-bold text-white"
                                >
                                    {{ step.order }}
                                </div>

                                <!-- Step content -->
                                <div
                                    class="flex-1 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900"
                                >
                                    <div class="mb-2 flex items-center gap-3">
                                        <span class="text-2xl">{{
                                            getMessageTypeIcon(
                                                step.message_type
                                            )
                                        }}</span>
                                        <span
                                            :class="[
                                                'rounded-full px-3 py-1 text-xs font-medium',
                                                step.channel === 'email'
                                                    ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300'
                                                    : 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
                                            ]"
                                        >
                                            {{ step.channel?.toUpperCase() }}
                                        </span>
                                        <span
                                            class="rounded-full bg-gray-100 px-3 py-1 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                                        >
                                            {{
                                                messageTypes?.[
                                                    step.message_type
                                                ] || step.message_type
                                            }}
                                        </span>
                                        <span
                                            v-if="step.delay_days > 0"
                                            class="text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            ⏱️ +{{ step.delay_days }}d
                                        </span>
                                    </div>

                                    <h5
                                        class="font-semibold text-gray-900 dark:text-white"
                                    >
                                        {{ step.subject }}
                                    </h5>
                                    <p
                                        v-if="step.description"
                                        class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        {{ step.description }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-8 flex justify-between">
                    <button
                        @click="goBack"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-6 py-3 font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300"
                    >
                        ← {{ $t("common.back") }}
                    </button>

                    <button
                        v-if="plan.status === 'exported'"
                        @click="goToMessages"
                        class="inline-flex items-center gap-2 rounded-lg bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-3 font-semibold text-white shadow-lg hover:from-indigo-700 hover:to-purple-700"
                    >
                        📧 {{ $t("common.view_messages") }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
