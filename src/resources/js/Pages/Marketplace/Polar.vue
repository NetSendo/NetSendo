<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const features = [
    {
        title: "Product Management",
        description: "Create and manage digital products and subscriptions with flexible pricing.",
        icon: "📦",
    },
    {
        title: "Checkout Sessions",
        description: "Generate secure checkout links for seamless customer payments.",
        icon: "🛒",
    },
    {
        title: "Usage-Based Billing",
        description: "Flexible metering and usage-based pricing for your products.",
        icon: "📊",
    },
    {
        title: "Webhook Integration",
        description: "Automatic handling of payment events and subscription updates.",
        icon: "🔔",
    },
    {
        title: "Customer Portal",
        description: "Self-service portal for customers to manage subscriptions and benefits.",
        icon: "👤",
    },
    {
        title: "Developer First",
        description: "Built for developers with modern APIs and great documentation.",
        icon: "⚡",
    },
];

const setupSteps = [
    {
        title: "Get Access Token",
        description: "Go to your Polar Organization Settings and create an Organization Access Token.",
    },
    {
        title: "Configure Settings",
        description: "Navigate to Settings → Polar Integration and enter your Access Token.",
    },
    {
        title: "Set Webhook Secret",
        description: "Configure a webhook endpoint in Polar pointing to /webhooks/polar and save the secret.",
    },
    {
        title: "Create Products",
        description: "Start creating products and generating checkout links for your customers.",
    },
];

// Webhook URL (window is not available in Vue 3 templates)
const webhookUrl = typeof window !== 'undefined' ? `${window.location.origin}/webhooks/polar` : '/webhooks/polar';
</script>

<template>
    <Head title="Polar Integration - NetSendo Marketplace" />

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
                    <span class="text-white">Polar</span>
                </div>

                <div class="grid gap-8 lg:grid-cols-3">
                    <!-- Main Content -->
                    <div class="lg:col-span-2 space-y-8">
                        <!-- Header -->
                        <div
                            class="overflow-hidden rounded-2xl bg-slate-800 ring-1 ring-white/10"
                        >
                            <div
                                class="bg-gradient-to-r from-blue-600/20 to-cyan-600/20 px-8 py-12"
                            >
                                <div class="flex items-start gap-6">
                                    <div
                                        class="h-24 w-24 shrink-0 overflow-hidden rounded-2xl bg-gradient-to-br from-blue-500 to-cyan-500 p-4 shadow-lg flex items-center justify-center"
                                    >
                                        <svg viewBox="0 0 24 24" fill="white" class="h-14 w-14">
                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                        </svg>
                                    </div>
                                    <div class="pt-2">
                                        <div class="flex items-center gap-3 mb-2">
                                            <h1 class="text-3xl font-bold text-white">
                                                Polar
                                            </h1>
                                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-500/10 px-3 py-1 text-xs font-semibold text-blue-400 ring-1 ring-blue-500/20">
                                                <span class="h-1.5 w-1.5 rounded-full bg-blue-400"></span>
                                                Developer First
                                            </span>
                                        </div>
                                        <p class="text-lg text-slate-300">
                                            {{ $t('polar.description') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="p-8">
                                <p
                                    class="text-slate-400 leading-relaxed text-lg"
                                >
                                    {{ $t('polar.long_description') }}
                                </p>
                            </div>
                        </div>

                        <!-- Features -->
                        <div
                            class="rounded-2xl bg-slate-800 p-8 ring-1 ring-white/10"
                        >
                            <h2 class="text-xl font-bold text-white mb-6">
                                🚀 {{ $t('polar.features_title') }}
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
                                ⚙️ {{ $t('polar.setup_title') }}
                            </h2>

                            <div class="space-y-4 mb-8">
                                <div
                                    v-for="(step, index) in setupSteps"
                                    :key="index"
                                    class="flex gap-4"
                                >
                                    <div
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-500/10 text-sm font-bold text-blue-400 ring-1 ring-blue-500/20"
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
                                :href="route('settings.polar.index')"
                                class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-blue-500"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                {{ $t('polar.go_to_settings') }}
                            </Link>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <div
                            class="rounded-2xl bg-slate-800 p-6 ring-1 ring-white/10"
                        >
                            <h3 class="font-semibold text-white mb-4">
                                {{ $t('common.resources') }}
                            </h3>
                            <div class="space-y-3">
                                <a
                                    href="https://docs.polar.sh"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex items-center justify-between rounded-xl bg-white/5 px-4 py-3 text-slate-300 transition-colors hover:bg-white/10 hover:text-white"
                                >
                                    <span class="flex items-center gap-2">
                                        <svg class="h-5 w-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                        </svg>
                                        Polar Docs
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
                                    href="https://polar.sh"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex items-center justify-between rounded-xl bg-white/5 px-4 py-3 text-slate-300 transition-colors hover:bg-white/10 hover:text-white"
                                >
                                    <span class="flex items-center gap-2">
                                        <svg class="h-5 w-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                                        </svg>
                                        Polar Website
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
                                    :href="route('settings.polar-products.index')"
                                    class="flex items-center justify-between rounded-xl bg-white/5 px-4 py-3 text-slate-300 transition-colors hover:bg-white/10 hover:text-white"
                                >
                                    <span class="flex items-center gap-2">
                                        <svg class="h-5 w-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                        </svg>
                                        {{ $t('polar.manage_products') }}
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
                            class="rounded-2xl bg-gradient-to-br from-blue-500/10 to-cyan-500/10 p-6 ring-1 ring-blue-500/20"
                        >
                            <h3 class="font-semibold text-white mb-2">
                                {{ $t('common.need_help') }}
                            </h3>
                            <p class="text-sm text-slate-400 mb-4">
                                {{ $t('polar.help_description') }}
                            </p>
                            <a
                                href="https://docs.polar.sh"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-blue-500"
                            >
                                {{ $t('common.documentation') }}
                            </a>
                        </div>

                        <!-- Webhook Info -->
                        <div
                            class="rounded-2xl bg-slate-800 p-6 ring-1 ring-white/10"
                        >
                            <h3 class="font-semibold text-white mb-4">
                                🔗 Webhook Endpoint
                            </h3>
                            <div class="p-3 rounded-lg bg-slate-900 font-mono text-xs text-slate-300 break-all">
                                {{ webhookUrl }}
                            </div>
                            <p class="text-xs text-slate-500 mt-2">
                                {{ $t('polar.webhook_info') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
