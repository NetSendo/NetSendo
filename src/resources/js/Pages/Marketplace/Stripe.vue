<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const features = [
    {
        title: "Product Management",
        description: "Create, update, and manage your digital products and subscriptions.",
        icon: "📦",
    },
    {
        title: "Checkout Sessions",
        description: "Generate secure checkout links for seamless customer payments.",
        icon: "🛒",
    },
    {
        title: "OAuth Connect",
        description: "Quick one-click connection with your Stripe account via OAuth.",
        icon: "⚡",
    },
    {
        title: "Webhook Integration",
        description: "Automatic handling of payment events and subscription updates.",
        icon: "🔔",
    },
];

const setupSteps = [
    {
        title: "Navigate to Settings",
        description: "Go to Settings → Stripe Integration in your NetSendo dashboard.",
    },
    {
        title: "Choose Connection Method",
        description: "Select OAuth (recommended) for quick setup or API Keys for manual configuration.",
    },
    {
        title: "Connect Your Account",
        description: "Follow the on-screen instructions to authorize Stripe access.",
    },
    {
        title: "Create Products",
        description: "Start creating products and generating checkout links.",
    },
];
</script>

<template>
    <Head title="Stripe Integration - NetSendo Marketplace" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Breadcrumb -->
                <div
                    class="mb-8 flex items-center gap-2 text-sm text-slate-400"
                >
                    <Link
                        :href="route('marketplace.index')"
                        class="hover:text-white transition-colors"
                    >
                        Marketplace
                    </Link>
                    <span>/</span>
                    <span class="text-white">Stripe</span>
                </div>

                <div class="grid gap-8 lg:grid-cols-3">
                    <!-- Main Content -->
                    <div class="lg:col-span-2 space-y-8">
                        <!-- Header -->
                        <div
                            class="overflow-hidden rounded-2xl bg-slate-800 ring-1 ring-white/10"
                        >
                            <div
                                class="bg-gradient-to-r from-purple-600/20 to-indigo-600/20 px-8 py-12"
                            >
                                <div class="flex items-start gap-6">
                                    <div
                                        class="h-24 w-24 shrink-0 overflow-hidden rounded-2xl bg-white p-3 shadow-lg"
                                    >
                                        <svg viewBox="0 0 24 24" fill="#635BFF" class="h-full w-full">
                                            <path d="M13.976 9.15c-2.172-.806-3.356-1.426-3.356-2.409 0-.831.683-1.305 1.901-1.305 2.227 0 4.515.858 6.09 1.631l.89-5.494C18.252.975 15.697 0 12.165 0 9.667 0 7.589.654 6.104 1.872 4.56 3.147 3.757 4.992 3.757 7.218c0 4.039 2.467 5.76 6.476 7.219 2.585.92 3.445 1.574 3.445 2.583 0 .98-.84 1.545-2.354 1.545-1.875 0-4.965-.921-6.99-2.109l-.9 5.555C5.175 22.99 8.385 24 11.714 24c2.641 0 4.843-.624 6.328-1.813 1.664-1.305 2.525-3.236 2.525-5.732 0-4.128-2.524-5.851-6.591-7.305z"/>
                                        </svg>
                                    </div>
                                    <div class="pt-2">
                                        <div class="flex items-center gap-3 mb-2">
                                            <h1 class="text-3xl font-bold text-white">
                                                Stripe
                                            </h1>
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-400 ring-1 ring-emerald-500/20">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                                Active
                                            </span>
                                        </div>
                                        <p class="text-lg text-slate-300">
                                            Accept payments, manage subscriptions, and sell digital products with the world's leading payment platform.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="p-8">
                                <p
                                    class="text-slate-400 leading-relaxed text-lg"
                                >
                                    Stripe integration allows you to monetize your email marketing platform by selling digital products, subscriptions, and one-time purchases directly to your subscribers.
                                </p>
                            </div>
                        </div>

                        <!-- Features -->
                        <div
                            class="rounded-2xl bg-slate-800 p-8 ring-1 ring-white/10"
                        >
                            <h2 class="text-xl font-bold text-white mb-6">
                                🚀 Features
                            </h2>
                            <div class="grid gap-6 sm:grid-cols-2">
                                <div
                                    v-for="feature in features"
                                    :key="feature.title"
                                    class="flex gap-4"
                                >
                                    <div
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/5 text-xl"
                                    >
                                        {{ feature.icon }}
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-white">
                                            {{ feature.title }}
                                        </h3>
                                        <p class="text-sm text-slate-400 mt-1">
                                            {{ feature.description }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Setup -->
                        <div
                            class="rounded-2xl bg-slate-800 p-8 ring-1 ring-white/10"
                        >
                            <h2 class="text-xl font-bold text-white mb-6">
                                ⚙️ Setup
                            </h2>

                            <div class="space-y-4 mb-8">
                                <div
                                    v-for="(step, index) in setupSteps"
                                    :key="index"
                                    class="flex gap-4"
                                >
                                    <div
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-purple-500/10 text-sm font-bold text-purple-400 ring-1 ring-purple-500/20"
                                    >
                                        {{ index + 1 }}
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-white">
                                            {{ step.title }}
                                        </h3>
                                        <p
                                            class="text-sm text-slate-400 mt-1"
                                            v-html="step.description"
                                        ></p>
                                    </div>
                                </div>
                            </div>

                            <Link
                                :href="route('settings.stripe.index')"
                                class="inline-flex items-center gap-2 rounded-xl bg-purple-600 px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-purple-500"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Go to Stripe Settings
                            </Link>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <div
                            class="rounded-2xl bg-slate-800 p-6 ring-1 ring-white/10"
                        >
                            <h3 class="font-semibold text-white mb-4">
                                Resources
                            </h3>
                            <div class="space-y-3">
                                <a
                                    href="https://stripe.com/docs"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex items-center justify-between rounded-xl bg-white/5 px-4 py-3 text-slate-300 transition-colors hover:bg-white/10 hover:text-white"
                                >
                                    <span class="flex items-center gap-2">
                                        <svg class="h-5 w-5 text-purple-400" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M13.976 9.15c-2.172-.806-3.356-1.426-3.356-2.409 0-.831.683-1.305 1.901-1.305 2.227 0 4.515.858 6.09 1.631l.89-5.494C18.252.975 15.697 0 12.165 0 9.667 0 7.589.654 6.104 1.872 4.56 3.147 3.757 4.992 3.757 7.218c0 4.039 2.467 5.76 6.476 7.219 2.585.92 3.445 1.574 3.445 2.583 0 .98-.84 1.545-2.354 1.545-1.875 0-4.965-.921-6.99-2.109l-.9 5.555C5.175 22.99 8.385 24 11.714 24c2.641 0 4.843-.624 6.328-1.813 1.664-1.305 2.525-3.236 2.525-5.732 0-4.128-2.524-5.851-6.591-7.305z"/>
                                        </svg>
                                        Stripe Docs
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
                                    :href="route('settings.stripe-products.index')"
                                    class="flex items-center justify-between rounded-xl bg-white/5 px-4 py-3 text-slate-300 transition-colors hover:bg-white/10 hover:text-white"
                                >
                                    <span class="flex items-center gap-2">
                                        <svg class="h-5 w-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                        </svg>
                                        Manage Products
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
                            class="rounded-2xl bg-gradient-to-br from-purple-500/10 to-indigo-500/10 p-6 ring-1 ring-purple-500/20"
                        >
                            <h3 class="font-semibold text-white mb-2">
                                Need help?
                            </h3>
                            <p class="text-sm text-slate-400 mb-4">
                                Check out our documentation for more details on
                                how to use this integration.
                            </p>
                            <a
                                href="https://netsendo.com/en/docs"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex w-full items-center justify-center rounded-xl bg-purple-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-purple-500"
                            >
                                Documentation
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
