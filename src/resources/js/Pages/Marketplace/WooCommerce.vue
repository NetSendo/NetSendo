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
        title: t('marketplace.woocommerce.features.auto_subscribe.title'),
        description: t('marketplace.woocommerce.features.auto_subscribe.description'),
        icon: "📧",
    },
    {
        title: t('marketplace.woocommerce.features.cart_recovery.title'),
        description: t('marketplace.woocommerce.features.cart_recovery.description'),
        icon: "🛒",
    },
    {
        title: t('marketplace.woocommerce.features.product_settings.title'),
        description: t('marketplace.woocommerce.features.product_settings.description'),
        icon: "📦",
    },
    {
        title: t('marketplace.woocommerce.features.external_pages.title'),
        description: t('marketplace.woocommerce.features.external_pages.description'),
        icon: "🔗",
    },
]);

const setupSteps = computed(() => [
    {
        title: t('marketplace.woocommerce.setup_steps.download.title'),
        description: t('marketplace.woocommerce.setup_steps.download.description'),
    },
    {
        title: t('marketplace.woocommerce.setup_steps.install.title'),
        description: t('marketplace.woocommerce.setup_steps.install.description'),
    },
    {
        title: t('marketplace.woocommerce.setup_steps.configure.title'),
        description: t('marketplace.woocommerce.setup_steps.configure.description'),
    },
    {
        title: t('marketplace.woocommerce.setup_steps.lists.title'),
        description: t('marketplace.woocommerce.setup_steps.lists.description'),
    },
]);

const requirements = computed(() => [
    t('marketplace.woocommerce.requirements.wp'),
    t('marketplace.woocommerce.requirements.wc'),
    t('marketplace.woocommerce.requirements.php'),
    t('marketplace.woocommerce.requirements.account'),
]);
</script>

<template>
    <Head :title="`${$t('marketplace.woocommerce.title')} - ${$t('marketplace.title')}`" />

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
                    <span class="text-gray-900 dark:text-white">{{ $t('marketplace.woocommerce.title') }}</span>
                </div>

                <div class="grid gap-8 lg:grid-cols-3">
                    <!-- Main Content -->
                    <div class="lg:col-span-2 space-y-8">
                        <!-- Header -->
                        <div
                            class="overflow-hidden rounded-2xl bg-white dark:bg-slate-800 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <div
                                class="bg-gradient-to-r from-purple-600/10 to-pink-600/10 dark:from-purple-600/20 dark:to-pink-600/20 px-8 py-12"
                            >
                                <div class="flex items-start gap-6">
                                    <div
                                        class="h-24 w-24 shrink-0 overflow-hidden rounded-2xl bg-white p-3 shadow-lg ring-1 ring-gray-100 dark:ring-transparent"
                                    >
                                        <svg viewBox="0 0 24 24" fill="#96588a" class="h-full w-full">
                                            <path d="M23.594 9.25h-.587c-.22 0-.421.124-.52.32l-.872 1.746a.586.586 0 0 1-.52.32h-5.76a.586.586 0 0 0-.52.32l-.873 1.747a.586.586 0 0 1-.52.32H7.52a.586.586 0 0 0-.52.32l-.873 1.746a.586.586 0 0 1-.52.32H.406A.407.407 0 0 0 0 16.816v1.932c0 .847.686 1.533 1.533 1.533h20.934c.847 0 1.533-.686 1.533-1.533V9.657a.407.407 0 0 0-.406-.407zM1.533 5.72h20.934c.847 0 1.533.686 1.533 1.532v.47a.407.407 0 0 1-.406.406h-5.01a.586.586 0 0 0-.52.32l-.873 1.747a.586.586 0 0 1-.52.32H9.767a.586.586 0 0 0-.52.32l-.873 1.746a.586.586 0 0 1-.52.32H.406A.407.407 0 0 1 0 10.496V7.252c0-.847.686-1.533 1.533-1.533z"/>
                                        </svg>
                                    </div>
                                    <div class="pt-2">
                                        <div class="flex items-center gap-3 mb-2">
                                            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                                                {{ $t('marketplace.woocommerce.hero_title') }}
                                            </h1>
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-500/20">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400"></span>
                                                {{ $t('marketplace.active') }}
                                            </span>
                                        </div>
                                        <p class="text-lg text-gray-600 dark:text-slate-300">
                                            {{ $t('marketplace.woocommerce.hero_subtitle') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="p-8">
                                <p class="text-gray-600 dark:text-slate-400 leading-relaxed text-lg">
                                    {{ $t('marketplace.woocommerce.hero_description') }}
                                </p>
                            </div>
                        </div>

                        <!-- Features -->
                        <div
                            class="rounded-2xl bg-white dark:bg-slate-800 p-8 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">
                                {{ $t('marketplace.woocommerce.features_title') }}
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
                                {{ $t('marketplace.woocommerce.setup_title') }}
                            </h2>

                            <div class="space-y-4 mb-8">
                                <div
                                    v-for="(step, index) in setupSteps"
                                    :key="index"
                                    class="flex gap-4"
                                >
                                    <div
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-500/10 text-sm font-bold text-purple-600 dark:text-purple-400 ring-1 ring-purple-500/20"
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
                                :href="route('marketplace.woocommerce.download')"
                                class="inline-flex items-center gap-2 rounded-xl bg-purple-600 px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-purple-500"
                            >
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                </svg>
                                {{ $t('marketplace.woocommerce.download_button') }}
                            </a>
                        </div>

                        <!-- API Configuration -->
                        <div
                            class="rounded-2xl bg-white dark:bg-slate-800 p-8 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">
                                {{ $t('marketplace.woocommerce.api_config_title') }}
                            </h2>

                            <div class="space-y-4">
                                <div>
                                    <h3 class="font-semibold text-gray-900 dark:text-white mb-2">{{ $t('marketplace.woocommerce.api_url_label') }}</h3>
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
                                    <p class="text-xs text-gray-500 dark:text-slate-500 mt-1">{{ $t('marketplace.woocommerce.api_url_help') }}</p>
                                </div>

                                <div>
                                    <h3 class="font-semibold text-gray-900 dark:text-white mb-2">{{ $t('marketplace.woocommerce.api_key_label') }}</h3>
                                    <p class="text-sm text-gray-600 dark:text-slate-400 mb-2">
                                        {{ $t('marketplace.woocommerce.api_key_desc') }}
                                    </p>
                                    <Link
                                        :href="route('settings.api-keys.index')"
                                        class="inline-flex items-center gap-2 rounded-lg bg-gray-200 hover:bg-gray-300 dark:bg-slate-700 dark:hover:bg-slate-600 px-4 py-2 text-sm text-gray-700 dark:text-slate-300 transition-colors"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                        </svg>
                                        {{ $t('marketplace.woocommerce.manage_api_keys') }}
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
                                {{ $t('marketplace.woocommerce.requirements_title') }}
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
                                Zasoby
                            </h3>
                            <div class="space-y-3">
                                <a
                                    href="https://woocommerce.com/documentation/"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex items-center justify-between rounded-xl bg-gray-50 dark:bg-white/5 px-4 py-3 text-gray-700 dark:text-slate-300 transition-colors hover:bg-gray-100 dark:hover:bg-white/10 hover:text-gray-900 dark:hover:text-white"
                                >
                                    <span class="flex items-center gap-2">
                                        <svg class="h-5 w-5 text-purple-600 dark:text-purple-400" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M23.594 9.25h-.587c-.22 0-.421.124-.52.32l-.872 1.746a.586.586 0 0 1-.52.32h-5.76a.586.586 0 0 0-.52.32l-.873 1.747a.586.586 0 0 1-.52.32H7.52a.586.586 0 0 0-.52.32l-.873 1.746a.586.586 0 0 1-.52.32H.406A.407.407 0 0 0 0 16.816v1.932c0 .847.686 1.533 1.533 1.533h20.934c.847 0 1.533-.686 1.533-1.533V9.657a.407.407 0 0 0-.406-.407z"/>
                                        </svg>
                                        {{ $t('marketplace.woocommerce.docs_link') }}
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
                                        {{ $t('marketplace.woocommerce.lists_link') }}
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
                                <Link
                                    :href="route('settings.sales-funnels.index')"
                                    class="flex items-center justify-between rounded-xl bg-gray-50 dark:bg-white/5 px-4 py-3 text-gray-700 dark:text-slate-300 transition-colors hover:bg-gray-100 dark:hover:bg-white/10 hover:text-gray-900 dark:hover:text-white"
                                >
                                    <span class="flex items-center gap-2">
                                        <svg class="h-5 w-5 text-blue-500 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                        </svg>
                                        {{ $t('marketplace.woocommerce.funnels_link') }}
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
                            class="rounded-2xl bg-gradient-to-br from-purple-500/10 to-pink-500/10 p-6 ring-1 ring-purple-500/20"
                        >
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-2">
                                {{ $t('marketplace.woocommerce.help_title') }}
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-slate-400 mb-4">
                                {{ $t('marketplace.woocommerce.help_desc') }}
                            </p>
                            <a
                                href="https://netsendo.com/en/docs"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex w-full items-center justify-center rounded-xl bg-purple-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-purple-500"
                            >
                                {{ $t('marketplace.woocommerce.documentation_button') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
