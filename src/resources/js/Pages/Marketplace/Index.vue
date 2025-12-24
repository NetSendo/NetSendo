<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

// Future integration categories with planned platforms
const categories = [
    {
        id: "ecommerce",
        icon: "🛒",
        color: "indigo",
        platforms: [
            { name: "WooCommerce", logo: null },
            { name: "Shopify", logo: null },
            { name: "PrestaShop", logo: null },
            { name: "Magento", logo: null },
        ],
    },
    {
        id: "crm",
        icon: "📊",
        color: "emerald",
        platforms: [
            { name: "HubSpot", logo: null },
            { name: "Pipedrive", logo: null },
            { name: "Salesforce", logo: null },
            { name: "Zoho CRM", logo: null },
        ],
    },
    {
        id: "forms",
        icon: "📝",
        color: "amber",
        platforms: [
            { name: "Typeform", logo: null },
            { name: "Google Forms", logo: null },
            { name: "JotForm", logo: null },
            { name: "Tally", logo: null },
        ],
    },
    {
        id: "automation",
        icon: "⚡",
        color: "purple",
        platforms: [
            { name: "Zapier", logo: null },
            { name: "Make (Integromat)", logo: null },
            {
                name: "n8n",
                logo: null,
                status: "available",
                route: "marketplace.n8n",
            },
            { name: "Webhooks", logo: null },
        ],
    },
    {
        id: "payments",
        icon: "💳",
        color: "rose",
        platforms: [
            { name: "Stripe", logo: null },
            { name: "PayPal", logo: null },
            { name: "Paddle", logo: null },
            { name: "LemonSqueezy", logo: null },
        ],
    },
    {
        id: "analytics",
        icon: "📈",
        color: "cyan",
        platforms: [
            { name: "Google Analytics", logo: null },
            { name: "Plausible", logo: null },
            { name: "Mixpanel", logo: null },
            { name: "Segment", logo: null },
        ],
    },
];

const getColorClasses = (color) => {
    const colors = {
        indigo: "from-indigo-500 to-indigo-600 ring-indigo-500/20",
        emerald: "from-emerald-500 to-emerald-600 ring-emerald-500/20",
        amber: "from-amber-500 to-amber-600 ring-amber-500/20",
        purple: "from-purple-500 to-purple-600 ring-purple-500/20",
        rose: "from-rose-500 to-rose-600 ring-rose-500/20",
        cyan: "from-cyan-500 to-cyan-600 ring-cyan-500/20",
    };
    return colors[color] || colors.indigo;
};

const getBgClasses = (color) => {
    const colors = {
        indigo: "bg-indigo-500/10",
        emerald: "bg-emerald-500/10",
        amber: "bg-amber-500/10",
        purple: "bg-purple-500/10",
        rose: "bg-rose-500/10",
        cyan: "bg-cyan-500/10",
    };
    return colors[color] || colors.indigo;
};
</script>

<template>
    <Head :title="$t('marketplace.title')" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-8 text-center">
                    <div
                        class="mb-4 inline-flex items-center gap-2 rounded-full bg-indigo-500/20 px-4 py-1.5"
                    >
                        <span class="text-lg">🚀</span>
                        <span class="text-sm font-semibold text-indigo-400">{{
                            $t("marketplace.coming_soon")
                        }}</span>
                    </div>
                    <h1 class="text-4xl font-bold text-white">
                        {{ $t("marketplace.title") }}
                    </h1>
                    <p class="mx-auto mt-4 max-w-2xl text-lg text-slate-400">
                        {{ $t("marketplace.subtitle") }}
                    </p>
                </div>

                <!-- Coming Soon Banner -->
                <div
                    class="mb-12 overflow-hidden rounded-2xl bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 p-0.5"
                >
                    <div class="rounded-xl bg-slate-900 px-8 py-12 text-center">
                        <div
                            class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-4xl shadow-lg shadow-indigo-500/30"
                        >
                            🔌
                        </div>
                        <h2 class="mb-3 text-2xl font-bold text-white">
                            {{ $t("marketplace.banner_title") }}
                        </h2>
                        <p class="mx-auto max-w-xl text-slate-400">
                            {{ $t("marketplace.banner_desc") }}
                        </p>
                        <div
                            class="mt-8 flex flex-wrap items-center justify-center gap-4"
                        >
                            <div
                                class="flex items-center gap-2 rounded-lg bg-white/5 px-4 py-2 text-sm text-slate-300"
                            >
                                <svg
                                    class="h-4 w-4 text-emerald-400"
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
                                {{ $t("marketplace.features.one_click") }}
                            </div>
                            <div
                                class="flex items-center gap-2 rounded-lg bg-white/5 px-4 py-2 text-sm text-slate-300"
                            >
                                <svg
                                    class="h-4 w-4 text-emerald-400"
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
                                {{ $t("marketplace.features.auto_sync") }}
                            </div>
                            <div
                                class="flex items-center gap-2 rounded-lg bg-white/5 px-4 py-2 text-sm text-slate-300"
                            >
                                <svg
                                    class="h-4 w-4 text-emerald-400"
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
                                {{ $t("marketplace.features.no_code") }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Categories Grid -->
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="category in categories"
                        :key="category.id"
                        class="group relative overflow-hidden rounded-2xl bg-slate-800/50 p-6 ring-1 ring-white/10 transition-all hover:ring-white/20"
                    >
                        <!-- Category Header -->
                        <div class="mb-6 flex items-center gap-3">
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br text-2xl ring-4"
                                :class="getColorClasses(category.color)"
                            >
                                {{ category.icon }}
                            </div>
                            <div>
                                <h3 class="font-semibold text-white">
                                    {{
                                        $t(
                                            `marketplace.categories.${category.id}.title`
                                        )
                                    }}
                                </h3>
                                <p class="text-xs text-slate-500">
                                    {{
                                        $t(
                                            `marketplace.categories.${category.id}.desc`
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- Platforms List -->
                        <div class="space-y-2">
                            <component
                                :is="platform.route ? Link : 'div'"
                                v-for="platform in category.platforms"
                                :key="platform.name"
                                :href="
                                    platform.route
                                        ? route(platform.route)
                                        : undefined
                                "
                                class="flex items-center justify-between rounded-lg px-3 py-2 transition-colors"
                                :class="[
                                    getBgClasses(category.color),
                                    platform.route
                                        ? 'hover:bg-white/10 cursor-pointer'
                                        : '',
                                ]"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/10 text-xs font-bold text-white"
                                    >
                                        {{ platform.name.charAt(0) }}
                                    </div>
                                    <span
                                        class="text-sm font-medium text-slate-300"
                                        >{{ platform.name }}</span
                                    >
                                </div>
                                <span
                                    v-if="platform.status === 'soon'"
                                    class="rounded-full bg-slate-700 px-2 py-0.5 text-[10px] font-medium text-slate-400"
                                >
                                    {{ $t("marketplace.soon") }}
                                </span>
                                <svg
                                    v-else
                                    class="h-4 w-4 text-slate-400"
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
                            </component>
                        </div>

                        <!-- Decorative gradient -->
                        <div
                            class="pointer-events-none absolute -bottom-20 -right-20 h-40 w-40 rounded-full bg-gradient-to-br opacity-10 blur-3xl transition-opacity group-hover:opacity-20"
                            :class="getColorClasses(category.color)"
                        ></div>
                    </div>
                </div>

                <!-- Request Integration -->
                <div
                    class="mt-12 rounded-2xl bg-slate-800/30 p-8 text-center ring-1 ring-white/5"
                >
                    <h3 class="mb-2 text-lg font-semibold text-white">
                        {{ $t("marketplace.request_title") }}
                    </h3>
                    <p class="mb-6 text-sm text-slate-400">
                        {{ $t("marketplace.request_desc") }}
                    </p>
                    <a
                        href="https://support.netsendo.com/integrations"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 rounded-xl bg-white/5 px-6 py-3 text-sm font-medium text-slate-300 transition-colors hover:bg-white/10"
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
                                d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                        {{ $t("marketplace.request_button") }}
                    </a>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
