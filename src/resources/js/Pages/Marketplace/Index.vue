<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import { ref, computed } from "vue";
import axios from "axios";

const { t } = useI18n();
const page = usePage();

// Modal state
const showRequestModal = ref(false);
const submitting = ref(false);
const submitted = ref(false);
const submitError = ref(null);

const requestForm = ref({
    integration_name: '',
    description: '',
    priority: 'normal',
});

const currentUser = computed(() => page.props.auth?.user);

const openRequestModal = () => {
    showRequestModal.value = true;
    submitted.value = false;
    submitError.value = null;
};

const closeRequestModal = () => {
    showRequestModal.value = false;
    requestForm.value = {
        integration_name: '',
        description: '',
        priority: 'normal',
    };
};

const submitRequest = async () => {
    submitting.value = true;
    submitError.value = null;

    try {
        const payload = {
            integration_name: requestForm.value.integration_name,
            description: requestForm.value.description,
            priority: requestForm.value.priority,
            user: {
                id: currentUser.value?.id,
                name: currentUser.value?.name,
                email: currentUser.value?.email,
            },
            netsendo_url: window.location.origin,
            submitted_at: new Date().toISOString(),
        };

        await axios.post('https://a.gregciupek.com/webhook/559eb483-f939-40a7-9ee6-49488f4d1b5e', payload);
        submitted.value = true;
    } catch (error) {
        submitError.value = t('marketplace.request_error') || 'Failed to submit request';
    } finally {
        submitting.value = false;
    }
};

// Active/existing integrations that are already implemented
const activeIntegrations = [
    {
        id: "n8n",
        name: "n8n",
        description: "Workflow automation",
        icon: "⚡",
        color: "orange",
        route: "marketplace.n8n",
    },
    {
        id: "stripe",
        name: "Stripe",
        description: "Payment processing",
        icon: "💳",
        color: "purple",
        route: "marketplace.stripe",
    },
    {
        id: "polar",
        name: "Polar",
        description: "Developer-first payments",
        icon: "🔷",
        color: "cyan",
        route: "marketplace.polar",
    },
    {
        id: "woocommerce",
        name: "WooCommerce",
        description: "E-commerce integration",
        icon: "🛒",
        color: "purple",
        route: "marketplace.woocommerce",
    },
    {
        id: "wordpress",
        name: "WordPress",
        description: "Forms & content gating",
        icon: "📝",
        color: "blue",
        route: "marketplace.wordpress",
    },
    {
        id: "shopify",
        name: "Shopify",
        description: "E-commerce integration",
        icon: "🛍️",
        color: "green",
        route: "marketplace.shopify",
    },
    {
        id: "sendgrid",
        name: "SendGrid",
        description: "Email delivery",
        icon: "📧",
        color: "blue",
        route: null,
    },
    {
        id: "twilio",
        name: "Twilio",
        description: "SMS gateway",
        icon: "📱",
        color: "red",
        route: null,
    },
    {
        id: "smsapi",
        name: "SMSAPI",
        description: "SMS gateway",
        icon: "💬",
        color: "green",
        route: null,
    },
    {
        id: "openai",
        name: "OpenAI",
        description: "AI content generation",
        icon: "🤖",
        color: "teal",
        route: null,
    },
    {
        id: "mcp",
        name: "MCP Server",
        description: "AI assistants integration",
        icon: "🔌",
        color: "violet",
        route: "marketplace.mcp",
    },
];

// Future integration categories with planned platforms
const categories = [
    {
        id: "ecommerce",
        icon: "🛒",
        color: "indigo",
        platforms: [
            { name: "WooCommerce", logo: null, status: "available", route: "marketplace.woocommerce" },
            { name: "Shopify", logo: null, status: "available", route: "marketplace.shopify" },
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
            { name: "Stripe", logo: null, status: "available", route: "marketplace.stripe" },
            { name: "Polar", logo: null, status: "available", route: "marketplace.polar" },
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
    {
        id: "ai",
        icon: "🤖",
        color: "violet",
        platforms: [
            { name: "MCP Server", logo: null, status: "available", route: "marketplace.mcp" },
            { name: "Claude", logo: null },
            { name: "Cursor IDE", logo: null },
            { name: "VS Code Copilot", logo: null },
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
        orange: "from-orange-500 to-orange-600 ring-orange-500/20",
        blue: "from-blue-500 to-blue-600 ring-blue-500/20",
        red: "from-red-500 to-red-600 ring-red-500/20",
        green: "from-green-500 to-green-600 ring-green-500/20",
        teal: "from-teal-500 to-teal-600 ring-teal-500/20",
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
        orange: "bg-orange-500/10",
        blue: "bg-blue-500/10",
        red: "bg-red-500/10",
        green: "bg-green-500/10",
        teal: "bg-teal-500/10",
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
                    <h1 class="text-4xl font-bold text-gray-900 dark:text-white">
                        {{ $t("marketplace.title") }}
                    </h1>
                    <p class="mx-auto mt-4 max-w-2xl text-lg text-gray-600 dark:text-slate-400">
                        {{ $t("marketplace.subtitle") }}
                    </p>
                </div>

                <!-- Active Integrations -->
                <div class="mb-12">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                                {{ $t("marketplace.active_integrations") }}
                            </h2>
                        </div>
                        <span class="rounded-full bg-emerald-100 dark:bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-500/20">
                            {{ activeIntegrations.length }} {{ $t("marketplace.active") }}
                        </span>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <component
                            :is="integration.route ? Link : 'div'"
                            v-for="integration in activeIntegrations"
                            :key="integration.id"
                            :href="integration.route ? route(integration.route) : undefined"
                            class="group relative overflow-hidden rounded-xl bg-white dark:bg-slate-800/50 p-4 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-emerald-500/30 transition-all hover:ring-emerald-500/50 shadow-sm"
                            :class="integration.route ? 'cursor-pointer' : ''"
                        >
                            <div class="flex items-center gap-4">
                                <div
                                    class="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br text-xl ring-4"
                                    :class="getColorClasses(integration.color)"
                                >
                                    {{ integration.icon }}
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ integration.name }}</h3>
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-slate-400">{{ integration.description }}</p>
                                </div>
                                <svg v-if="integration.route" class="h-5 w-5 text-gray-400 dark:text-slate-400 group-hover:text-emerald-500 dark:group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                        </component>
                    </div>
                </div>

                <!-- Coming Soon Banner -->
                <div
                    class="mb-12 overflow-hidden rounded-2xl bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 p-0.5"
                >
                    <div class="rounded-xl bg-white dark:bg-slate-900 px-8 py-12 text-center">
                        <div
                            class="mb-4 inline-flex items-center gap-2 rounded-full bg-indigo-50 dark:bg-indigo-500/20 px-4 py-1.5"
                        >
                            <span class="text-lg">🚀</span>
                            <span class="text-sm font-semibold text-indigo-600 dark:text-indigo-400">{{
                                $t("marketplace.coming_soon")
                            }}</span>
                        </div>
                        <h2 class="mb-3 text-2xl font-bold text-gray-900 dark:text-white">
                            {{ $t("marketplace.banner_title") }}
                        </h2>
                        <p class="mx-auto max-w-xl text-gray-600 dark:text-slate-400">
                            {{ $t("marketplace.banner_desc") }}
                        </p>
                        <div
                            class="mt-8 flex flex-wrap items-center justify-center gap-4"
                        >
                            <div
                                class="flex items-center gap-2 rounded-lg bg-gray-50 dark:bg-white/5 px-4 py-2 text-sm text-gray-600 dark:text-slate-300 border border-gray-100 dark:border-transparent"
                            >
                                <svg
                                    class="h-4 w-4 text-emerald-500 dark:text-emerald-400"
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
                                class="flex items-center gap-2 rounded-lg bg-gray-50 dark:bg-white/5 px-4 py-2 text-sm text-gray-600 dark:text-slate-300 border border-gray-100 dark:border-transparent"
                            >
                                <svg
                                    class="h-4 w-4 text-emerald-500 dark:text-emerald-400"
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
                                class="flex items-center gap-2 rounded-lg bg-gray-50 dark:bg-white/5 px-4 py-2 text-sm text-gray-600 dark:text-slate-300 border border-gray-100 dark:border-transparent"
                            >
                                <svg
                                    class="h-4 w-4 text-emerald-500 dark:text-emerald-400"
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
                        class="group relative overflow-hidden rounded-2xl bg-white dark:bg-slate-800/50 p-6 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 transition-all hover:ring-2 hover:ring-indigo-500 dark:hover:ring-white/20 shadow-sm"
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
                                <h3 class="font-semibold text-gray-900 dark:text-white">
                                    {{
                                        $t(
                                            `marketplace.categories.${category.id}.title`
                                        )
                                    }}
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-slate-500">
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
                                        ? 'hover:bg-gray-100 dark:hover:bg-white/10 cursor-pointer'
                                        : '',
                                ]"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/50 dark:bg-white/10 text-xs font-bold text-gray-700 dark:text-white"
                                    >
                                        {{ platform.name.charAt(0) }}
                                    </div>
                                    <span
                                        class="text-sm font-medium text-gray-900 dark:text-slate-300"
                                        >{{ platform.name }}</span
                                    >
                                    <!-- Green dot for available integrations -->
                                    <span
                                        v-if="platform.status === 'available'"
                                        class="h-2 w-2 rounded-full bg-emerald-400"
                                    ></span>
                                </div>
                                <span
                                    v-if="platform.status === 'soon'"
                                    class="rounded-full bg-gray-200 dark:bg-slate-700 px-2 py-0.5 text-[10px] font-medium text-gray-500 dark:text-slate-400"
                                >
                                    {{ $t("marketplace.soon") }}
                                </span>
                                <span
                                    v-else-if="platform.status === 'available'"
                                    class="rounded-full bg-emerald-100 dark:bg-emerald-500/10 px-2 py-0.5 text-[10px] font-medium text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-500/20"
                                >
                                    {{ $t("marketplace.active") }}
                                </span>
                                <svg
                                    v-else
                                    class="h-4 w-4 text-gray-400 dark:text-slate-400"
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
                    class="mt-12 rounded-2xl bg-gray-50 dark:bg-slate-800/30 p-8 text-center ring-1 ring-gray-200 dark:ring-white/5"
                >
                    <h3 class="mb-2 text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $t("marketplace.request_title") }}
                    </h3>
                    <p class="mb-6 text-sm text-gray-600 dark:text-slate-400">
                        {{ $t("marketplace.request_desc") }}
                    </p>
                    <button
                        type="button"
                        @click="openRequestModal"
                        class="inline-flex items-center gap-2 rounded-xl bg-white dark:bg-white/5 px-6 py-3 text-sm font-medium text-gray-700 dark:text-slate-300 transition-colors hover:bg-gray-50 dark:hover:bg-white/10 shadow-sm ring-1 ring-gray-200 dark:ring-transparent"
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
                    </button>
                </div>
            </div>
        </div>

        <!-- Request Integration Modal -->
        <Teleport to="body">
            <Transition name="modal">
                <div
                    v-if="showRequestModal"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4"
                >
                    <!-- Backdrop -->
                    <div
                        class="absolute inset-0 bg-black/60 backdrop-blur-sm"
                        @click="closeRequestModal"
                    ></div>

                    <!-- Modal -->
                    <div class="relative w-full max-w-lg rounded-2xl bg-white dark:bg-slate-800 shadow-2xl ring-1 ring-gray-200 dark:ring-white/10">
                        <!-- Header -->
                        <div class="flex items-center justify-between border-b border-gray-200 dark:border-slate-700 px-6 py-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ $t("marketplace.request_modal_title") }}
                            </h3>
                            <button
                                type="button"
                                @click="closeRequestModal"
                                class="rounded-lg p-1 text-gray-400 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700 hover:text-gray-900 dark:hover:text-white"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- Content -->
                        <div class="px-6 py-4">
                            <!-- Success State -->
                            <div v-if="submitted" class="text-center py-8">
                                <div class="inline-flex items-center justify-center w-16 h-16 mb-4 bg-emerald-100 dark:bg-emerald-500/10 rounded-full">
                                    <svg class="w-8 h-8 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">{{ $t("marketplace.request_success_title") }}</h4>
                                <p class="text-sm text-gray-600 dark:text-slate-400 mb-6">{{ $t("marketplace.request_success_desc") }}</p>
                                <button
                                    type="button"
                                    @click="closeRequestModal"
                                    class="px-6 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-gray-900 dark:text-white rounded-lg text-sm font-medium transition-colors"
                                >
                                    {{ $t("common.close") }}
                                </button>
                            </div>

                            <!-- Form -->
                            <form v-else @submit.prevent="submitRequest" class="space-y-4">
                                <!-- Integration Name -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">
                                        {{ $t("marketplace.request_integration_name") }} *
                                    </label>
                                    <input
                                        v-model="requestForm.integration_name"
                                        type="text"
                                        required
                                        :placeholder="$t('marketplace.request_integration_name_placeholder')"
                                        class="w-full rounded-lg border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-4 py-2.5 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>

                                <!-- Description -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">
                                        {{ $t("marketplace.request_description") }}
                                    </label>
                                    <textarea
                                        v-model="requestForm.description"
                                        rows="3"
                                        :placeholder="$t('marketplace.request_description_placeholder')"
                                        class="w-full rounded-lg border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-4 py-2.5 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-slate-500 focus:border-indigo-500 focus:ring-indigo-500"
                                    ></textarea>
                                </div>

                                <!-- Priority -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">
                                        {{ $t("marketplace.request_priority") }}
                                    </label>
                                    <select
                                        v-model="requestForm.priority"
                                        class="w-full rounded-lg border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-4 py-2.5 text-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="low">{{ $t("marketplace.priority_low") }}</option>
                                        <option value="normal">{{ $t("marketplace.priority_normal") }}</option>
                                        <option value="high">{{ $t("marketplace.priority_high") }}</option>
                                    </select>
                                </div>

                                <!-- Error -->
                                <div v-if="submitError" class="p-3 rounded-lg bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20">
                                    <p class="text-sm text-red-600 dark:text-red-400">{{ submitError }}</p>
                                </div>

                                <!-- User Info -->
                                <div class="p-3 rounded-lg bg-gray-50 dark:bg-slate-900/50 block">
                                    <p class="text-xs text-gray-500 dark:text-slate-500">
                                        {{ $t("marketplace.request_submitted_as") }}: <span class="text-gray-700 dark:text-slate-400">{{ currentUser?.email }}</span>
                                    </p>
                                </div>

                                <!-- Actions -->
                                <div class="flex items-center justify-end gap-3 pt-2">
                                    <button
                                        type="button"
                                        @click="closeRequestModal"
                                        class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white transition-colors"
                                    >
                                        {{ $t("common.cancel") }}
                                    </button>
                                    <button
                                        type="submit"
                                        :disabled="submitting || !requestForm.integration_name"
                                        class="inline-flex items-center px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg text-sm font-medium transition-colors"
                                    >
                                        <svg v-if="submitting" class="w-4 h-4 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        {{ $t("marketplace.request_submit") }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>

