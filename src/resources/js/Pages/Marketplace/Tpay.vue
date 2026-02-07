<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const features = [
    {
        title: "Bank Transfers & BLIK",
        description:
            "Accept payments via Polish bank transfers, BLIK, and more.",
        icon: "🏦",
    },
    {
        title: "Card Payments",
        description:
            "Visa, Mastercard, and other card payments supported out of the box.",
        icon: "💳",
    },
    {
        title: "Google Pay & Apple Pay",
        description:
            "Mobile wallet payments for a seamless checkout experience.",
        icon: "📱",
    },
    {
        title: "Webhook Notifications",
        description:
            "Real-time JWS-signed notifications for transaction status updates.",
        icon: "🔔",
    },
    {
        title: "Sales Funnel Integration",
        description:
            "Automatically add buyers to mailing lists and tag them after purchase.",
        icon: "🎯",
    },
    {
        title: "Sandbox Testing",
        description:
            "Full sandbox environment to test payments before going live.",
        icon: "🧪",
    },
];

const setupSteps = [
    {
        title: "Create Tpay Account",
        description:
            'Register at <a href="https://panel.tpay.com" target="_blank" rel="noopener" class="text-rose-600 dark:text-rose-400 underline">panel.tpay.com</a> or use the <a href="https://panel.sandbox.tpay.com" target="_blank" rel="noopener" class="text-rose-600 dark:text-rose-400 underline">sandbox</a> for testing.',
    },
    {
        title: "Get API Credentials",
        description:
            "Go to your Tpay panel → Settings → API and copy the Client ID, Client Secret, and Security Code.",
    },
    {
        title: "Configure Settings",
        description:
            "Navigate to Settings → Tpay Integration and enter your API credentials.",
    },
    {
        title: "Create Products",
        description:
            "Start creating products and generating checkout links for your customers.",
    },
];

const webhookUrl =
    typeof window !== "undefined"
        ? `${window.location.origin}/webhooks/tpay`
        : "/webhooks/tpay";
</script>

<template>
    <Head title="Tpay Integration - NetSendo Marketplace" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Breadcrumb -->
                <div
                    class="mb-8 flex items-center gap-2 text-sm text-gray-500 dark:text-slate-400"
                >
                    <Link
                        :href="route('marketplace.index')"
                        class="hover:text-gray-900 dark:hover:text-white transition-colors"
                    >
                        Marketplace
                    </Link>
                    <span>/</span>
                    <span class="text-gray-900 dark:text-white">Tpay</span>
                </div>

                <div class="grid gap-8 lg:grid-cols-3">
                    <!-- Main Content -->
                    <div class="lg:col-span-2 space-y-8">
                        <!-- Header -->
                        <div
                            class="overflow-hidden rounded-2xl bg-white dark:bg-slate-800 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <div
                                class="bg-gradient-to-r from-rose-600/10 to-pink-600/10 dark:from-rose-600/20 dark:to-pink-600/20 px-8 py-12"
                            >
                                <div class="flex items-start gap-6">
                                    <div
                                        class="h-24 w-24 shrink-0 overflow-hidden rounded-2xl bg-gradient-to-br from-rose-500 to-pink-500 p-4 shadow-lg flex items-center justify-center"
                                    >
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="white"
                                            class="h-14 w-14"
                                        >
                                            <path
                                                d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"
                                            />
                                        </svg>
                                    </div>
                                    <div class="pt-2">
                                        <div
                                            class="flex items-center gap-3 mb-2"
                                        >
                                            <h1
                                                class="text-3xl font-bold text-gray-900 dark:text-white"
                                            >
                                                Tpay
                                            </h1>
                                            <span
                                                class="inline-flex items-center gap-1 rounded-full bg-rose-100 dark:bg-rose-500/10 px-3 py-1 text-xs font-semibold text-rose-600 dark:text-rose-400 ring-1 ring-rose-500/20"
                                            >
                                                <span
                                                    class="h-1.5 w-1.5 rounded-full bg-rose-500 dark:bg-rose-400"
                                                ></span>
                                                Polish Payments
                                            </span>
                                        </div>
                                        <p
                                            class="text-lg text-gray-600 dark:text-slate-300"
                                        >
                                            {{
                                                $t(
                                                    "tpay.marketplace_description",
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="p-8">
                                <p
                                    class="text-gray-600 dark:text-slate-400 leading-relaxed text-lg"
                                >
                                    {{
                                        $t("tpay.marketplace_long_description")
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- Features -->
                        <div
                            class="rounded-2xl bg-white dark:bg-slate-800 p-8 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <h2
                                class="text-xl font-bold text-gray-900 dark:text-white mb-6"
                            >
                                🚀 {{ $t("tpay.marketplace_features_title") }}
                            </h2>
                            <div class="grid gap-6 sm:grid-cols-2">
                                <div
                                    v-for="feature in features"
                                    :key="feature.title"
                                    class="flex gap-4"
                                >
                                    <div
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-50 dark:bg-white/5 text-xl text-gray-600 dark:text-white"
                                    >
                                        {{ feature.icon }}
                                    </div>
                                    <div>
                                        <h3
                                            class="font-semibold text-gray-900 dark:text-white"
                                        >
                                            {{ feature.title }}
                                        </h3>
                                        <p
                                            class="text-sm text-gray-600 dark:text-slate-400 mt-1"
                                        >
                                            {{ feature.description }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Setup -->
                        <div
                            class="rounded-2xl bg-white dark:bg-slate-800 p-8 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <h2
                                class="text-xl font-bold text-gray-900 dark:text-white mb-6"
                            >
                                ⚙️ {{ $t("tpay.marketplace_setup_title") }}
                            </h2>

                            <div class="space-y-4 mb-8">
                                <div
                                    v-for="(step, index) in setupSteps"
                                    :key="index"
                                    class="flex gap-4"
                                >
                                    <div
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-rose-100 dark:bg-rose-500/10 text-sm font-bold text-rose-600 dark:text-rose-400 ring-1 ring-rose-500/20"
                                    >
                                        {{ index + 1 }}
                                    </div>
                                    <div>
                                        <h3
                                            class="font-semibold text-gray-900 dark:text-white"
                                        >
                                            {{ step.title }}
                                        </h3>
                                        <p
                                            class="text-sm text-gray-600 dark:text-slate-400 mt-1"
                                            v-html="step.description"
                                        ></p>
                                    </div>
                                </div>
                            </div>

                            <Link
                                :href="route('settings.tpay.index')"
                                class="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-rose-500"
                            >
                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                    />
                                </svg>
                                {{ $t("tpay.marketplace_go_to_settings") }}
                            </Link>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <div
                            class="rounded-2xl bg-white dark:bg-slate-800 p-6 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <h3
                                class="font-semibold text-gray-900 dark:text-white mb-4"
                            >
                                {{ $t("common.resources") }}
                            </h3>
                            <div class="space-y-3">
                                <a
                                    href="https://docs.tpay.com"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex items-center justify-between rounded-xl bg-gray-50 dark:bg-white/5 px-4 py-3 text-gray-700 dark:text-slate-300 transition-colors hover:bg-gray-100 dark:hover:bg-white/10 hover:text-gray-900 dark:hover:text-white"
                                >
                                    <span class="flex items-center gap-2">
                                        <svg
                                            class="h-5 w-5 text-rose-600 dark:text-rose-400"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
                                            />
                                        </svg>
                                        Tpay Docs
                                    </span>
                                    <svg
                                        class="h-4 w-4"
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
                                </a>
                                <a
                                    href="https://tpay.com"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex items-center justify-between rounded-xl bg-gray-50 dark:bg-white/5 px-4 py-3 text-gray-700 dark:text-slate-300 transition-colors hover:bg-gray-100 dark:hover:bg-white/10 hover:text-gray-900 dark:hover:text-white"
                                >
                                    <span class="flex items-center gap-2">
                                        <svg
                                            class="h-5 w-5 text-pink-600 dark:text-pink-400"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"
                                            />
                                        </svg>
                                        Tpay Website
                                    </span>
                                    <svg
                                        class="h-4 w-4"
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
                                </a>
                                <Link
                                    :href="
                                        route('settings.tpay-products.index')
                                    "
                                    class="flex items-center justify-between rounded-xl bg-gray-50 dark:bg-white/5 px-4 py-3 text-gray-700 dark:text-slate-300 transition-colors hover:bg-gray-100 dark:hover:bg-white/10 hover:text-gray-900 dark:hover:text-white"
                                >
                                    <span class="flex items-center gap-2">
                                        <svg
                                            class="h-5 w-5 text-amber-500 dark:text-amber-400"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                                            />
                                        </svg>
                                        {{
                                            $t(
                                                "tpay.marketplace_manage_products",
                                            )
                                        }}
                                    </span>
                                    <svg
                                        class="h-4 w-4"
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
                                </Link>
                            </div>
                        </div>

                        <div
                            class="rounded-2xl bg-gradient-to-br from-rose-500/10 to-pink-500/10 p-6 ring-1 ring-rose-500/20"
                        >
                            <h3
                                class="font-semibold text-gray-900 dark:text-white mb-2"
                            >
                                {{ $t("common.need_help") }}
                            </h3>
                            <p
                                class="text-sm text-gray-600 dark:text-slate-400 mb-4"
                            >
                                {{ $t("tpay.marketplace_help_description") }}
                            </p>
                            <a
                                href="https://docs.tpay.com"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex w-full items-center justify-center rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-rose-500"
                            >
                                {{ $t("common.documentation") }}
                            </a>
                        </div>

                        <!-- Webhook Info -->
                        <div
                            class="rounded-2xl bg-white dark:bg-slate-800 p-6 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <h3
                                class="font-semibold text-gray-900 dark:text-white mb-4"
                            >
                                🔗 Webhook Endpoint
                            </h3>
                            <div
                                class="p-3 rounded-lg bg-gray-50 dark:bg-slate-900 font-mono text-xs text-gray-600 dark:text-slate-300 break-all border border-gray-100 dark:border-slate-800"
                            >
                                {{ webhookUrl }}
                            </div>
                            <p
                                class="text-xs text-gray-500 dark:text-slate-500 mt-2"
                            >
                                {{ $t("tpay.marketplace_webhook_info") }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
