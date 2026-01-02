<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import { computed, ref } from "vue";

const { t } = useI18n();
const page = usePage();

// Get the current app URL for webhook endpoint
const appUrl = computed(() => {
    return page.props.appUrl || window.location.origin;
});

const webhookUrl = computed(() => {
    return `${appUrl.value}/webhooks/shopify`;
});

// Toast notification
const toast = ref(null);
const showToast = (message, success = true) => {
    toast.value = { message, success };
    setTimeout(() => {
        toast.value = null;
    }, 4000);
};

// Copy text to clipboard
const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text);
    showToast(t('common.copied') || 'Skopiowano!');
};

const features = computed(() => [
    {
        title: t('marketplace.shopify.features.auto_subscribe.title'),
        description: t('marketplace.shopify.features.auto_subscribe.description'),
        icon: "📧",
    },
    {
        title: t('marketplace.shopify.features.customer_sync.title'),
        description: t('marketplace.shopify.features.customer_sync.description'),
        icon: "👥",
    },
    {
        title: t('marketplace.shopify.features.order_tracking.title'),
        description: t('marketplace.shopify.features.order_tracking.description'),
        icon: "📦",
    },
    {
        title: t('marketplace.shopify.features.real_time.title'),
        description: t('marketplace.shopify.features.real_time.description'),
        icon: "⚡",
    },
]);

const setupSteps = computed(() => [
    {
        title: t('marketplace.shopify.setup_steps.api_key.title'),
        description: t('marketplace.shopify.setup_steps.api_key.description'),
    },
    {
        title: t('marketplace.shopify.setup_steps.shopify_admin.title'),
        description: t('marketplace.shopify.setup_steps.shopify_admin.description'),
    },
    {
        title: t('marketplace.shopify.setup_steps.create_webhook.title'),
        description: t('marketplace.shopify.setup_steps.create_webhook.description'),
    },
    {
        title: t('marketplace.shopify.setup_steps.test.title'),
        description: t('marketplace.shopify.setup_steps.test.description'),
    },
]);

const webhookEvents = [
    { event: 'orders/paid', description: 'When an order payment is completed' },
    { event: 'orders/create', description: 'When a new order is created' },
    { event: 'customers/create', description: 'When a new customer registers' },
];

const requirements = computed(() => [
    t('marketplace.shopify.requirements.store'),
    t('marketplace.shopify.requirements.account'),
    t('marketplace.shopify.requirements.api_key'),
]);
</script>

<template>
    <Head :title="`${$t('marketplace.shopify.title')} - ${$t('marketplace.title')}`" />

    <AuthenticatedLayout>
        <!-- Toast Notification -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-300"
                enter-from-class="opacity-0 translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 translate-y-2"
            >
                <div
                    v-if="toast"
                    class="fixed bottom-6 right-6 z-[200] flex items-center gap-3 rounded-xl px-5 py-4 shadow-lg"
                    :class="
                        toast.success
                            ? 'bg-emerald-600 text-white'
                            : 'bg-rose-600 text-white'
                    "
                >
                    <svg
                        v-if="toast.success"
                        class="h-5 w-5 flex-shrink-0"
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
                    <svg
                        v-else
                        class="h-5 w-5 flex-shrink-0"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <span class="font-medium">{{ toast.message }}</span>
                    <button
                        @click="toast = null"
                        class="ml-2 opacity-80 hover:opacity-100"
                    >
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
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>
            </Transition>
        </Teleport>

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
                        {{ $t('marketplace.title') }}
                    </Link>
                    <span>/</span>
                    <span class="text-gray-900 dark:text-white">{{ $t('marketplace.shopify.title') }}</span>
                </div>

                <div class="grid gap-8 lg:grid-cols-3">
                    <!-- Main Content -->
                    <div class="lg:col-span-2 space-y-8">
                        <!-- Header -->
                        <div
                            class="overflow-hidden rounded-2xl bg-white dark:bg-slate-800 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <div
                                class="bg-gradient-to-r from-green-600/10 to-emerald-600/10 dark:from-green-600/20 dark:to-emerald-600/20 px-8 py-12"
                            >
                                <div class="flex items-start gap-6">
                                    <div
                                        class="h-24 w-24 shrink-0 overflow-hidden rounded-2xl bg-white p-3 shadow-lg ring-1 ring-gray-100 dark:ring-transparent"
                                    >
                                        <!-- Shopify Logo SVG -->
                                        <svg viewBox="0 0 109.5 124.5" fill="#95BF47" class="h-full w-full">
                                            <path d="M95.9 23.9c-.1-.6-.6-1-1.1-1-.5 0-9.3-.2-9.3-.2s-7.4-7.2-8.1-7.9c-.7-.7-2.2-.5-2.7-.3 0 0-1.4.4-3.7 1.1-.4-1.3-1-2.8-1.8-4.4-2.6-5-6.5-7.7-11.1-7.7-.3 0-.6 0-1 .1-.1-.2-.3-.3-.4-.5-2-2.2-4.6-3.2-7.7-3.1-6 .2-12 4.5-16.8 12.2-3.4 5.4-6 12.2-6.7 17.5-6.9 2.1-11.7 3.6-11.8 3.7-3.5 1.1-3.6 1.2-4 4.5-.3 2.5-9.5 73.1-9.5 73.1l75.6 13.1 32.6-8.2S96 24.5 95.9 23.9zM67.2 16.8l-5.6 1.7c0-2.9-.4-7-1.8-10.5 4.4.9 6.6 5.8 7.4 8.8zm-9.4 2.9l-12 3.7c1.2-4.5 3.4-9 6.1-11.9 1-.3 2.4-1.9 4.2-1.1 1.7 3.3 1.8 7.9 1.7 9.3zM53 5.3c1.4 0 2.5.3 3.5.8-1.6.8-3.1 2.1-4.6 3.8-3.6 3.9-6.4 9.9-7.5 15.7l-9.9 3.1C36.5 19.5 43.9 5.5 53 5.3z"/>
                                            <path fill="#5E8E3E" d="M94.8 22.9c-.5 0-9.3-.2-9.3-.2s-7.4-7.2-8.1-7.9c-.3-.3-.6-.4-1-.5l-4.5 91.7 32.6-8.2S96 24.5 95.9 23.9c-.1-.6-.6-1-1.1-1z"/>
                                            <path fill="#fff" d="M58.8 39.2l-4.4 13s-3.8-2.1-8.5-2.1c-6.8 0-7.2 4.3-7.2 5.4 0 5.9 15.4 8.2 15.4 22.1 0 10.9-6.9 18-16.3 18-11.2 0-16.9-7-16.9-7l3-9.9s5.9 5.1 10.9 5.1c3.3 0 4.6-2.6 4.6-4.5 0-7.8-12.6-8.1-12.6-20.8 0-10.7 7.7-21.1 23.2-21.1 5.9-.1 8.8 1.8 8.8 1.8z"/>
                                        </svg>
                                    </div>
                                    <div class="pt-2">
                                        <div class="flex items-center gap-3 mb-2">
                                            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                                                {{ $t('marketplace.shopify.hero_title') }}
                                            </h1>
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-500/20">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400"></span>
                                                {{ $t('marketplace.active') }}
                                            </span>
                                        </div>
                                        <p class="text-lg text-gray-600 dark:text-slate-300">
                                            {{ $t('marketplace.shopify.hero_subtitle') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="p-8">
                                <p class="text-gray-600 dark:text-slate-400 leading-relaxed text-lg">
                                    {{ $t('marketplace.shopify.hero_description') }}
                                </p>
                            </div>
                        </div>

                        <!-- Features -->
                        <div
                            class="rounded-2xl bg-white dark:bg-slate-800 p-8 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">
                                {{ $t('marketplace.shopify.features_title') }}
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
                                        <h3 class="font-semibold text-gray-900 dark:text-white">
                                            {{ feature.title }}
                                        </h3>
                                        <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">
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
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">
                                {{ $t('marketplace.shopify.setup_title') }}
                            </h2>

                            <div class="space-y-4 mb-8">
                                <div
                                    v-for="(step, index) in setupSteps"
                                    :key="index"
                                    class="flex gap-4"
                                >
                                    <div
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-green-100 dark:bg-green-500/10 text-sm font-bold text-green-600 dark:text-green-400 ring-1 ring-green-500/20"
                                    >
                                        {{ index + 1 }}
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900 dark:text-white">
                                            {{ step.title }}
                                        </h3>
                                        <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">
                                            {{ step.description }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Webhook Configuration -->
                        <div
                            class="rounded-2xl bg-white dark:bg-slate-800 p-8 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">
                                {{ $t('marketplace.shopify.webhook_config_title') }}
                            </h2>

                            <div class="space-y-6">
                                <!-- Webhook URL -->
                                <div>
                                    <h3 class="font-semibold text-gray-900 dark:text-white mb-2">{{ $t('marketplace.shopify.webhook_url_label') }}</h3>
                                    <div class="flex rounded-lg overflow-hidden border border-gray-200 dark:border-slate-700">
                                        <input
                                            type="text"
                                            readonly
                                            :value="webhookUrl"
                                            class="flex-1 bg-gray-50 dark:bg-slate-900 border-none text-gray-600 dark:text-slate-300 text-sm px-4 py-2.5 font-mono"
                                        />
                                        <button
                                            type="button"
                                            @click="copyToClipboard(webhookUrl)"
                                            class="px-4 bg-gray-200 dark:bg-slate-700 hover:bg-gray-300 dark:hover:bg-slate-600 text-gray-700 dark:text-slate-300 text-sm transition-colors"
                                        >
                                            {{ $t('common.copy') }}
                                        </button>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-slate-500 mt-1">{{ $t('marketplace.shopify.webhook_url_help') }}</p>
                                </div>

                                <!-- API Key -->
                                <div>
                                    <h3 class="font-semibold text-gray-900 dark:text-white mb-2">{{ $t('marketplace.shopify.api_key_label') }}</h3>
                                    <p class="text-sm text-gray-600 dark:text-slate-400 mb-2">
                                        {{ $t('marketplace.shopify.api_key_desc') }}
                                    </p>
                                    <Link
                                        :href="route('settings.api-keys.index')"
                                        class="inline-flex items-center gap-2 rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-700 dark:hover:bg-slate-600 px-4 py-2 text-sm text-gray-700 dark:text-slate-300 transition-colors"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                        </svg>
                                        {{ $t('marketplace.shopify.manage_api_keys') }}
                                    </Link>
                                </div>

                                <!-- Supported Events -->
                                <div>
                                    <h3 class="font-semibold text-gray-900 dark:text-white mb-3">{{ $t('marketplace.shopify.supported_events') }}</h3>
                                    <div class="space-y-2">
                                        <div
                                            v-for="event in webhookEvents"
                                            :key="event.event"
                                            class="flex items-center gap-3 rounded-lg bg-gray-50 dark:bg-slate-900/50 p-3 border border-gray-100 dark:border-transparent"
                                        >
                                            <code class="text-sm text-green-600 dark:text-green-400 font-mono">{{ event.event }}</code>
                                            <span class="text-sm text-gray-600 dark:text-slate-400">{{ event.description }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- List ID Note -->
                                <div class="p-4 rounded-lg bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20">
                                    <div class="flex gap-3">
                                        <svg class="h-5 w-5 text-amber-500 dark:text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        <div>
                                            <h4 class="font-medium text-amber-700 dark:text-amber-400 text-sm">{{ $t('marketplace.shopify.list_id_note_title') }}</h4>
                                            <p class="text-sm text-amber-600 dark:text-slate-400 mt-1">{{ $t('marketplace.shopify.list_id_note_desc') }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Requirements -->
                        <div
                            class="rounded-2xl bg-white dark:bg-slate-800 p-6 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-4">
                                {{ $t('marketplace.shopify.requirements_title') }}
                            </h3>
                            <ul class="space-y-2">
                                <li v-for="req in requirements" :key="req" class="flex items-start gap-2 text-sm text-gray-600 dark:text-slate-400">
                                    <svg class="h-5 w-5 text-emerald-500 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    {{ req }}
                                </li>
                            </ul>
                        </div>

                        <!-- Resources -->
                        <div
                            class="rounded-2xl bg-white dark:bg-slate-800 p-6 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-4">
                                {{ $t('marketplace.shopify.resources_title') }}
                            </h3>
                            <div class="space-y-3">
                                <a
                                    href="https://shopify.dev/docs/api/admin-rest/current/resources/webhook"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex items-center justify-between rounded-xl bg-gray-50 dark:bg-white/5 px-4 py-3 text-gray-700 dark:text-slate-300 transition-colors hover:bg-gray-100 dark:hover:bg-white/10 hover:text-gray-900 dark:hover:text-white"
                                >
                                    <span class="flex items-center gap-2">
                                        <svg class="h-5 w-5 text-green-600 dark:text-green-400" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                                        </svg>
                                        {{ $t('marketplace.shopify.docs_link') }}
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
                                    :href="route('mailing-lists.index')"
                                    class="flex items-center justify-between rounded-xl bg-gray-50 dark:bg-white/5 px-4 py-3 text-gray-700 dark:text-slate-300 transition-colors hover:bg-gray-100 dark:hover:bg-white/10 hover:text-gray-900 dark:hover:text-white"
                                >
                                    <span class="flex items-center gap-2">
                                        <svg class="h-5 w-5 text-amber-500 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                        </svg>
                                        {{ $t('marketplace.shopify.lists_link') }}
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
                            class="rounded-2xl bg-gradient-to-br from-green-500/10 to-emerald-500/10 p-6 ring-1 ring-green-500/20"
                        >
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-2">
                                {{ $t('marketplace.shopify.help_title') }}
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-slate-400 mb-4">
                                {{ $t('marketplace.shopify.help_desc') }}
                            </p>
                            <a
                                href="https://netsendo.com/en/docs"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex w-full items-center justify-center rounded-xl bg-green-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-green-500"
                            >
                                {{ $t('marketplace.shopify.documentation_button') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
