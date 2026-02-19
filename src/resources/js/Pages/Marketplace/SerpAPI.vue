<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const features = [
    {
        title: () => t("marketplace.serpapi.features.google_search.title"),
        description: () =>
            t("marketplace.serpapi.features.google_search.description"),
        icon: "🔍",
    },
    {
        title: () => t("marketplace.serpapi.features.news_search.title"),
        description: () =>
            t("marketplace.serpapi.features.news_search.description"),
        icon: "📰",
    },
    {
        title: () => t("marketplace.serpapi.features.knowledge_graph.title"),
        description: () =>
            t("marketplace.serpapi.features.knowledge_graph.description"),
        icon: "🧠",
    },
    {
        title: () => t("marketplace.serpapi.features.company_lookup.title"),
        description: () =>
            t("marketplace.serpapi.features.company_lookup.description"),
        icon: "🏢",
    },
];

const setupSteps = [
    {
        title: () => t("marketplace.serpapi.setup_steps.get_key.title"),
        description: () =>
            t("marketplace.serpapi.setup_steps.get_key.description"),
    },
    {
        title: () => t("marketplace.serpapi.setup_steps.configure.title"),
        description: () =>
            t("marketplace.serpapi.setup_steps.configure.description"),
    },
    {
        title: () => t("marketplace.serpapi.setup_steps.search.title"),
        description: () =>
            t("marketplace.serpapi.setup_steps.search.description"),
    },
];

const useCases = [
    {
        title: () => t("marketplace.serpapi.use_cases.competitors.title"),
        description: () =>
            t("marketplace.serpapi.use_cases.competitors.description"),
        icon: "🎯",
    },
    {
        title: () => t("marketplace.serpapi.use_cases.trends.title"),
        description: () =>
            t("marketplace.serpapi.use_cases.trends.description"),
        icon: "📈",
    },
    {
        title: () => t("marketplace.serpapi.use_cases.crm.title"),
        description: () => t("marketplace.serpapi.use_cases.crm.description"),
        icon: "👥",
    },
];
</script>

<template>
    <Head title="SerpAPI - NetSendo Marketplace" />

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
                    <span class="text-gray-900 dark:text-white">SerpAPI</span>
                </div>

                <div class="grid gap-8 lg:grid-cols-3">
                    <!-- Main Content -->
                    <div class="lg:col-span-2 space-y-8">
                        <!-- Hero -->
                        <div
                            class="overflow-hidden rounded-2xl bg-white dark:bg-slate-800 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <div
                                class="bg-gradient-to-r from-amber-600/10 to-orange-600/10 dark:from-amber-600/20 dark:to-orange-600/20 px-8 py-12"
                            >
                                <div class="flex items-start gap-6">
                                    <div
                                        class="flex h-24 w-24 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 text-4xl shadow-lg ring-4 ring-amber-500/20"
                                    >
                                        🔍
                                    </div>
                                    <div class="pt-2">
                                        <h1
                                            class="text-3xl font-bold text-gray-900 dark:text-white mb-2"
                                        >
                                            {{
                                                $t(
                                                    "marketplace.serpapi.hero_title",
                                                )
                                            }}
                                        </h1>
                                        <p
                                            class="text-lg text-gray-600 dark:text-slate-300"
                                        >
                                            {{
                                                $t(
                                                    "marketplace.serpapi.hero_subtitle",
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
                                        $t(
                                            "marketplace.serpapi.hero_description",
                                        )
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
                                🚀
                                {{ $t("marketplace.serpapi.features_title") }}
                            </h2>
                            <div class="grid gap-6 sm:grid-cols-2">
                                <div
                                    v-for="feature in features"
                                    :key="feature.icon"
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
                                            {{ feature.title() }}
                                        </h3>
                                        <p
                                            class="text-sm text-gray-600 dark:text-slate-400 mt-1"
                                        >
                                            {{ feature.description() }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Setup Steps -->
                        <div
                            class="rounded-2xl bg-white dark:bg-slate-800 p-8 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <h2
                                class="text-xl font-bold text-gray-900 dark:text-white mb-6"
                            >
                                📦
                                {{ $t("marketplace.serpapi.setup_title") }}
                            </h2>

                            <div class="space-y-4 mb-8">
                                <div
                                    v-for="(step, index) in setupSteps"
                                    :key="index"
                                    class="flex gap-4"
                                >
                                    <div
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-500/10 text-sm font-bold text-amber-600 dark:text-amber-400 ring-1 ring-amber-500/20"
                                    >
                                        {{ index + 1 }}
                                    </div>
                                    <div>
                                        <h3
                                            class="font-semibold text-gray-900 dark:text-white"
                                        >
                                            {{ step.title() }}
                                        </h3>
                                        <p
                                            class="text-sm text-gray-600 dark:text-slate-400 mt-1"
                                        >
                                            {{ step.description() }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Search Types -->
                            <div
                                class="rounded-xl bg-gray-50 dark:bg-slate-950 p-6 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10"
                            >
                                <p
                                    class="text-sm font-medium text-gray-700 dark:text-slate-300 mb-3"
                                >
                                    {{
                                        $t(
                                            "marketplace.serpapi.search_types_title",
                                        )
                                    }}
                                </p>
                                <div class="space-y-2">
                                    <div
                                        class="flex items-center gap-3 rounded-lg bg-white dark:bg-white/5 px-4 py-2.5 border border-gray-200 dark:border-transparent"
                                    >
                                        <span class="text-lg">🌐</span>
                                        <span
                                            class="text-sm text-gray-700 dark:text-slate-300"
                                            >{{
                                                $t(
                                                    "marketplace.serpapi.search_types.general",
                                                )
                                            }}</span
                                        >
                                    </div>
                                    <div
                                        class="flex items-center gap-3 rounded-lg bg-white dark:bg-white/5 px-4 py-2.5 border border-gray-200 dark:border-transparent"
                                    >
                                        <span class="text-lg">📰</span>
                                        <span
                                            class="text-sm text-gray-700 dark:text-slate-300"
                                            >{{
                                                $t(
                                                    "marketplace.serpapi.search_types.news",
                                                )
                                            }}</span
                                        >
                                    </div>
                                    <div
                                        class="flex items-center gap-3 rounded-lg bg-white dark:bg-white/5 px-4 py-2.5 border border-gray-200 dark:border-transparent"
                                    >
                                        <span class="text-lg">🖼️</span>
                                        <span
                                            class="text-sm text-gray-700 dark:text-slate-300"
                                            >{{
                                                $t(
                                                    "marketplace.serpapi.search_types.images",
                                                )
                                            }}</span
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Use Cases -->
                        <div
                            class="rounded-2xl bg-white dark:bg-slate-800 p-8 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <h2
                                class="text-xl font-bold text-gray-900 dark:text-white mb-6"
                            >
                                💡
                                {{ $t("marketplace.serpapi.use_cases_title") }}
                            </h2>
                            <div class="grid gap-6 sm:grid-cols-3">
                                <div
                                    v-for="uc in useCases"
                                    :key="uc.icon"
                                    class="rounded-xl bg-gray-50 dark:bg-white/5 p-5 border border-gray-100 dark:border-transparent"
                                >
                                    <div class="text-2xl mb-3">
                                        {{ uc.icon }}
                                    </div>
                                    <h3
                                        class="font-semibold text-gray-900 dark:text-white mb-1"
                                    >
                                        {{ uc.title() }}
                                    </h3>
                                    <p
                                        class="text-sm text-gray-600 dark:text-slate-400"
                                    >
                                        {{ uc.description() }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Quick Setup -->
                        <div
                            class="rounded-2xl bg-white dark:bg-slate-800 p-6 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <h3
                                class="font-semibold text-gray-900 dark:text-white mb-4"
                            >
                                {{ $t("marketplace.serpapi.go_to_settings") }}
                            </h3>
                            <Link
                                :href="route('brain.settings')"
                                class="inline-flex w-full items-center justify-center rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-amber-500"
                            >
                                <svg
                                    class="h-4 w-4 mr-2"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                    />
                                </svg>
                                Brain Settings
                            </Link>
                        </div>

                        <!-- Requirements -->
                        <div
                            class="rounded-2xl bg-white dark:bg-slate-800 p-6 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <h3
                                class="font-semibold text-gray-900 dark:text-white mb-4"
                            >
                                {{
                                    $t("marketplace.serpapi.requirements_title")
                                }}
                            </h3>
                            <ul class="space-y-2">
                                <li
                                    class="flex items-center gap-2 text-sm text-gray-600 dark:text-slate-400"
                                >
                                    <svg
                                        class="h-4 w-4 text-emerald-500 shrink-0"
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
                                        $t(
                                            "marketplace.serpapi.requirements.account",
                                        )
                                    }}
                                </li>
                                <li
                                    class="flex items-center gap-2 text-sm text-gray-600 dark:text-slate-400"
                                >
                                    <svg
                                        class="h-4 w-4 text-emerald-500 shrink-0"
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
                                        $t(
                                            "marketplace.serpapi.requirements.api_key",
                                        )
                                    }}
                                </li>
                                <li
                                    class="flex items-center gap-2 text-sm text-gray-600 dark:text-slate-400"
                                >
                                    <svg
                                        class="h-4 w-4 text-emerald-500 shrink-0"
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
                                        $t(
                                            "marketplace.serpapi.requirements.brain",
                                        )
                                    }}
                                </li>
                            </ul>
                        </div>

                        <!-- Resources -->
                        <div
                            class="rounded-2xl bg-white dark:bg-slate-800 p-6 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <h3
                                class="font-semibold text-gray-900 dark:text-white mb-4"
                            >
                                {{ $t("marketplace.serpapi.resources_title") }}
                            </h3>
                            <div class="space-y-3">
                                <a
                                    href="https://serpapi.com/dashboard"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex items-center justify-between rounded-xl bg-gray-50 dark:bg-white/5 px-4 py-3 text-gray-700 dark:text-slate-300 transition-colors hover:bg-gray-100 dark:hover:bg-white/10 hover:text-gray-900 dark:hover:text-white"
                                >
                                    <span class="flex items-center gap-2">
                                        <svg
                                            class="h-5 w-5 text-amber-600 dark:text-amber-400"
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
                                        {{
                                            $t("marketplace.serpapi.docs_link")
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
                                            d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                                        />
                                    </svg>
                                </a>
                            </div>
                        </div>

                        <!-- Help -->
                        <div
                            class="rounded-2xl bg-gradient-to-br from-amber-500/10 to-orange-500/10 p-6 ring-1 ring-amber-500/20"
                        >
                            <h3
                                class="font-semibold text-gray-900 dark:text-white mb-2"
                            >
                                {{ $t("marketplace.serpapi.help_title") }}
                            </h3>
                            <p
                                class="text-sm text-gray-600 dark:text-slate-400 mb-4"
                            >
                                {{ $t("marketplace.serpapi.help_desc") }}
                            </p>
                            <a
                                href="https://netsendo.com/en/docs"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex w-full items-center justify-center rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-amber-500"
                            >
                                {{
                                    $t(
                                        "marketplace.serpapi.documentation_button",
                                    )
                                }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
