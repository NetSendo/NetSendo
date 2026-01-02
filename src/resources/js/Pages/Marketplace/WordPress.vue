<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import { computed, ref } from "vue";

const { t } = useI18n();
const page = usePage();

// Get the current app URL for API endpoint
const appUrl = computed(() => {
    return page.props.appUrl || window.location.origin;
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
        title: t('marketplace.wordpress.features.forms.title'),
        description: t('marketplace.wordpress.features.forms.description'),
        icon: "📧",
    },
    {
        title: t('marketplace.wordpress.features.gating.title'),
        description: t('marketplace.wordpress.features.gating.description'),
        icon: "🔒",
    },
    {
        title: t('marketplace.wordpress.features.blocks.title'),
        description: t('marketplace.wordpress.features.blocks.description'),
        icon: "🧱",
    },
    {
        title: t('marketplace.wordpress.features.widget.title'),
        description: t('marketplace.wordpress.features.widget.description'),
        icon: "📱",
    },
    {
        title: t('marketplace.wordpress.features.gdpr.title'),
        description: t('marketplace.wordpress.features.gdpr.description'),
        icon: "✅",
    },
    {
        title: t('marketplace.wordpress.features.settings.title'),
        description: t('marketplace.wordpress.features.settings.description'),
        icon: "⚙️",
    },
]);

const setupSteps = computed(() => [
    {
        title: t('marketplace.wordpress.setup_steps.download.title'),
        description: t('marketplace.wordpress.setup_steps.download.description'),
    },
    {
        title: t('marketplace.wordpress.setup_steps.install.title'),
        description: t('marketplace.wordpress.setup_steps.install.description'),
    },
    {
        title: t('marketplace.wordpress.setup_steps.configure.title'),
        description: t('marketplace.wordpress.setup_steps.configure.description'),
    },
    {
        title: t('marketplace.wordpress.setup_steps.add_forms.title'),
        description: t('marketplace.wordpress.setup_steps.add_forms.description'),
    },
]);

const requirements = computed(() => [
    t('marketplace.wordpress.requirements.wp'),
    t('marketplace.wordpress.requirements.php'),
    t('marketplace.wordpress.requirements.account'),
]);

const shortcodes = computed(() => [
    {
        code: '[netsendo_form]',
        description: t('marketplace.wordpress.shortcodes.form_basic'),
    },
    {
        code: '[netsendo_form list_id="123" style="card"]',
        description: t('marketplace.wordpress.shortcodes.form_styled'),
    },
    {
        code: '[netsendo_gate type="percentage" percentage="30"]Treść...[/netsendo_gate]',
        description: t('marketplace.wordpress.shortcodes.gate_percentage'),
    },
    {
        code: '[netsendo_gate type="subscribers_only"]Treść...[/netsendo_gate]',
        description: t('marketplace.wordpress.shortcodes.gate_subscribers'),
    },
]);
</script>

<template>
    <Head title="WordPress Integration - NetSendo Marketplace" />

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
                    <span class="text-gray-900 dark:text-white">{{ $t('marketplace.wordpress.title') }}</span>
                </div>

                <div class="grid gap-8 lg:grid-cols-3">
                    <!-- Main Content -->
                    <div class="lg:col-span-2 space-y-8">
                        <!-- Header -->
                        <div
                            class="overflow-hidden rounded-2xl bg-white dark:bg-slate-800 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <div
                                class="bg-gradient-to-r from-blue-600/10 to-cyan-600/10 dark:from-blue-600/20 dark:to-cyan-600/20 px-8 py-12"
                            >
                                <div class="flex items-start gap-6">
                                    <div
                                        class="h-24 w-24 shrink-0 overflow-hidden rounded-2xl bg-white p-3 shadow-lg ring-1 ring-gray-100 dark:ring-transparent"
                                    >
                                        <svg viewBox="0 0 24 24" fill="#21759b" class="h-full w-full">
                                            <path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm-1.243 15.879l-3.51-9.619c.583-.139 1.108-.233 1.108-.233.523-.093.46-.833-.063-.803 0 0-1.574.124-2.59.124-.181 0-.394-.005-.621-.014C6.755 5.08 9.206 3.667 12 3.667c2.078 0 3.967.789 5.389 2.081-.034-.002-.067-.008-.103-.008-.976 0-1.669.849-1.669 1.759 0 .817.472 1.508.974 2.327.378.656.819 1.498.819 2.715 0 .842-.324 1.82-.749 3.181l-.981 3.278-3.553-10.572c.584-.14.866-.233.866-.233.522-.093.46-.834-.063-.803 0 0-1.574.124-2.591.124-.109 0-.233-.002-.36-.006l3.78 10.969zm1.946.132l2.948-8.538c.551-1.376.731-2.478.731-3.457 0-.356-.023-.686-.067-.988C17.694 6.56 18.333 9.156 18.333 12c0 2.611-.946 5.003-2.514 6.854l-.116.157zM3.667 12c0-2.083.754-3.994 2.003-5.476l3.286 9.003c.079.216.163.417.252.605-3.136-1.187-5.541-4.226-5.541-8.132z"/>
                                        </svg>
                                    </div>
                                    <div class="pt-2">
                                        <div class="flex items-center gap-3 mb-2">
                                            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                                                {{ $t('marketplace.wordpress.hero_title') }}
                                            </h1>
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-500/20">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400"></span>
                                                {{ $t('marketplace.active') }}
                                            </span>
                                        </div>
                                        <p class="text-lg text-gray-600 dark:text-slate-300">
                                            {{ $t('marketplace.wordpress.hero_subtitle') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="p-8">
                                <p class="text-gray-600 dark:text-slate-400 leading-relaxed text-lg">
                                    {{ $t('marketplace.wordpress.hero_description') }}
                                </p>
                            </div>
                        </div>

                        <!-- Features -->
                        <div
                            class="rounded-2xl bg-white dark:bg-slate-800 p-8 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">
                                {{ $t('marketplace.wordpress.features_title') }}
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
                                {{ $t('marketplace.wordpress.setup_title') }}
                            </h2>

                            <div class="space-y-4 mb-8">
                                <div
                                    v-for="(step, index) in setupSteps"
                                    :key="index"
                                    class="flex gap-4"
                                >
                                    <div
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-500/10 text-sm font-bold text-blue-600 dark:text-blue-400 ring-1 ring-blue-500/20"
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

                            <a
                                :href="route('marketplace.wordpress.download')"
                                class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-blue-500"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                {{ $t('marketplace.wordpress.download_button') }}
                            </a>
                        </div>

                        <!-- Shortcodes Reference -->
                        <div
                            class="rounded-2xl bg-white dark:bg-slate-800 p-8 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">
                                {{ $t('marketplace.wordpress.shortcodes_title') }}
                            </h2>

                            <div class="space-y-4">
                                <div
                                    v-for="shortcode in shortcodes"
                                    :key="shortcode.code"
                                    class="rounded-lg bg-gray-50 dark:bg-slate-900/50 p-4 border border-gray-100 dark:border-transparent"
                                >
                                    <code class="block text-sm text-cyan-600 dark:text-cyan-400 font-mono mb-2">
                                        {{ shortcode.code }}
                                    </code>
                                    <p class="text-sm text-gray-600 dark:text-slate-400">
                                        {{ shortcode.description }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- API Configuration -->
                        <div
                            class="rounded-2xl bg-white dark:bg-slate-800 p-8 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">
                                {{ $t('marketplace.wordpress.api_config_title') }}
                            </h2>

                            <div class="space-y-4">
                                <div>
                                    <h3 class="font-semibold text-gray-900 dark:text-white mb-2">{{ $t('marketplace.wordpress.api_url_label') }}</h3>
                                    <div class="flex rounded-lg overflow-hidden border border-gray-200 dark:border-slate-700">
                                        <input
                                            type="text"
                                            readonly
                                            :value="appUrl"
                                            class="flex-1 bg-gray-50 dark:bg-slate-900 border-none text-gray-600 dark:text-slate-300 text-sm px-4 py-2.5"
                                        />
                                        <button
                                            type="button"
                                            @click="copyToClipboard(appUrl)"
                                            class="px-4 bg-gray-200 dark:bg-slate-700 hover:bg-gray-300 dark:hover:bg-slate-600 text-gray-700 dark:text-slate-300 text-sm transition-colors"
                                        >
                                            {{ $t('common.copy') }}
                                        </button>
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-slate-500 mt-1">{{ $t('marketplace.wordpress.api_url_help') }}</p>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-gray-900 dark:text-white mb-2">{{ $t('marketplace.wordpress.api_key_label') }}</h3>
                                    <p class="text-sm text-gray-600 dark:text-slate-400 mb-2">
                                        {{ $t('marketplace.wordpress.api_key_desc') }}
                                    </p>
                                    <Link
                                        :href="route('settings.api-keys.index')"
                                        class="inline-flex items-center gap-2 rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-700 dark:hover:bg-slate-600 px-4 py-2 text-sm text-gray-700 dark:text-slate-300 transition-colors"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                        </svg>
                                        {{ $t('marketplace.wordpress.manage_api_keys') }}
                                    </Link>
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
                                {{ $t('marketplace.wordpress.requirements_title') }}
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

                        <!-- Content Gate Types -->
                        <div
                            class="rounded-2xl bg-white dark:bg-slate-800 p-6 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-4">
                                {{ $t('marketplace.wordpress.content_gate_types_title') }}
                            </h3>
                            <div class="space-y-3">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-white text-sm">Percentage</h4>
                                        <p class="text-xs text-gray-600 dark:text-slate-400">{{ $t('marketplace.wordpress.content_gate_types.percentage_desc') }}</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-500/10 text-purple-600 dark:text-purple-400">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-white text-sm">Subscribers Only</h4>
                                        <p class="text-xs text-gray-600 dark:text-slate-400">{{ $t('marketplace.wordpress.content_gate_types.subscribers_only_desc') }}</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-3">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-900 dark:text-white text-sm">Logged In</h4>
                                        <p class="text-xs text-gray-600 dark:text-slate-400">{{ $t('marketplace.wordpress.content_gate_types.logged_in_desc') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Resources -->
                        <div
                            class="rounded-2xl bg-white dark:bg-slate-800 p-6 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-4">
                                {{ $t('marketplace.wordpress.resources_title') }}
                            </h3>
                            <div class="space-y-3">
                                <a
                                    href="https://wordpress.org/documentation/"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex items-center justify-between rounded-xl bg-gray-50 dark:bg-white/5 px-4 py-3 text-gray-700 dark:text-slate-300 transition-colors hover:bg-gray-100 dark:hover:bg-white/10 hover:text-gray-900 dark:hover:text-white"
                                >
                                    <span class="flex items-center gap-2">
                                        <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2z"/>
                                        </svg>
                                        {{ $t('marketplace.wordpress.docs_link') }}
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
                                        {{ $t('marketplace.wordpress.lists_link') }}
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
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-2">
                                {{ $t('marketplace.wordpress.help_title') }}
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-slate-400 mb-4">
                                {{ $t('marketplace.wordpress.help_desc') }}
                            </p>
                            <a
                                href="https://netsendo.com/en/docs"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-blue-500"
                            >
                                {{ $t('marketplace.wordpress.documentation_button') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
