<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";

const features = [
    {
        title: "Telegram Bot API",
        description:
            "Full bot integration with webhook processing and inline keyboards.",
        icon: "🤖",
    },
    {
        title: "NetSendo Brain Chat",
        description:
            "Chat with your AI marketing assistant directly from Telegram.",
        icon: "🧠",
    },
    {
        title: "Account Linking",
        description:
            "Secure 8-character code linking between NetSendo and Telegram accounts.",
        icon: "🔗",
    },
    {
        title: "Action Plan Approvals",
        description:
            "Approve or reject AI action plans via inline keyboard buttons.",
        icon: "✅",
    },
    {
        title: "Bot Commands",
        description:
            "/start, /connect, /disconnect, /mode, /status, /help, /knowledge.",
        icon: "⌨️",
    },
    {
        title: "Multi-Channel Support",
        description:
            "Conversations sync between web panel and Telegram seamlessly.",
        icon: "🔄",
    },
];

const setupSteps = [
    {
        title: "Create a Bot via @BotFather",
        description:
            'Open Telegram, search for <a href="https://t.me/BotFather" target="_blank" class="text-sky-500 hover:text-sky-400 underline">@BotFather</a>, send <code class="rounded bg-gray-100 dark:bg-white/10 px-1.5 py-0.5 text-xs font-mono">/newbot</code> and follow the prompts to create your bot.',
    },
    {
        title: "Copy the Bot Token",
        description:
            "BotFather will give you an API token. Copy it — you'll need it in the next step.",
    },
    {
        title: "Paste Token in Brain Settings",
        description:
            "Go to <strong>NetSendo Brain → Settings → Telegram Integration</strong> and paste your bot token.",
    },
    {
        title: "Generate Link Code",
        description:
            'Click "Generate link code" in Brain Settings. You\'ll receive an 8-character code.',
    },
    {
        title: "Send Code to Your Bot",
        description:
            'Open your Telegram bot, send <code class="rounded bg-gray-100 dark:bg-white/10 px-1.5 py-0.5 text-xs font-mono">/connect YOUR_CODE</code> to link your account.',
    },
    {
        title: "Test the Connection",
        description:
            'Click "Test connection" in Brain Settings to verify everything works. You should receive a test message in Telegram.',
    },
];
</script>

<template>
    <Head title="Telegram Integration - NetSendo Marketplace" />

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
                    <span class="text-gray-900 dark:text-white">Telegram</span>
                </div>

                <div class="grid gap-8 lg:grid-cols-3">
                    <!-- Main Content -->
                    <div class="lg:col-span-2 space-y-8">
                        <!-- Header -->
                        <div
                            class="overflow-hidden rounded-2xl bg-white dark:bg-slate-800 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <div
                                class="bg-gradient-to-r from-sky-600/10 to-blue-600/10 dark:from-sky-600/20 dark:to-blue-600/20 px-8 py-12"
                            >
                                <div class="flex items-start gap-6">
                                    <div
                                        class="h-24 w-24 shrink-0 overflow-hidden rounded-2xl bg-white p-3 shadow-lg ring-1 ring-gray-100 dark:ring-transparent flex items-center justify-center"
                                    >
                                        <svg
                                            viewBox="0 0 24 24"
                                            class="h-16 w-16"
                                        >
                                            <path
                                                fill="#26A5E4"
                                                d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"
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
                                                Telegram Integration
                                            </h1>
                                            <span
                                                class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-500/20"
                                            >
                                                <span
                                                    class="h-1.5 w-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400"
                                                ></span>
                                                Active
                                            </span>
                                        </div>
                                        <p
                                            class="text-lg text-gray-600 dark:text-slate-300"
                                        >
                                            Chat with NetSendo Brain AI
                                            assistant directly from Telegram.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="p-8">
                                <p
                                    class="text-gray-600 dark:text-slate-400 leading-relaxed text-lg"
                                >
                                    Connect your Telegram account to NetSendo
                                    Brain and manage your email marketing
                                    campaigns, subscribers, and analytics
                                    through natural AI conversations — all from
                                    the convenience of your Telegram app.
                                    Approve action plans with inline buttons,
                                    check campaign stats, and manage your
                                    knowledge base on the go.
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
                                🚀 Features
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

                        <!-- Bot Commands -->
                        <div
                            class="rounded-2xl bg-white dark:bg-slate-800 p-8 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <h2
                                class="text-xl font-bold text-gray-900 dark:text-white mb-6"
                            >
                                ⌨️ Bot Commands
                            </h2>
                            <div class="space-y-3">
                                <div
                                    class="flex items-start gap-3 rounded-lg bg-gray-50 dark:bg-white/5 px-4 py-3"
                                >
                                    <code
                                        class="shrink-0 rounded bg-sky-100 dark:bg-sky-500/10 px-2 py-0.5 text-xs font-mono font-bold text-sky-600 dark:text-sky-400"
                                        >/start</code
                                    >
                                    <span
                                        class="text-sm text-gray-600 dark:text-slate-400"
                                        >Welcome message and instructions</span
                                    >
                                </div>
                                <div
                                    class="flex items-start gap-3 rounded-lg bg-gray-50 dark:bg-white/5 px-4 py-3"
                                >
                                    <code
                                        class="shrink-0 rounded bg-sky-100 dark:bg-sky-500/10 px-2 py-0.5 text-xs font-mono font-bold text-sky-600 dark:text-sky-400"
                                        >/connect</code
                                    >
                                    <span
                                        class="text-sm text-gray-600 dark:text-slate-400"
                                        >Link your NetSendo account with a
                                        code</span
                                    >
                                </div>
                                <div
                                    class="flex items-start gap-3 rounded-lg bg-gray-50 dark:bg-white/5 px-4 py-3"
                                >
                                    <code
                                        class="shrink-0 rounded bg-sky-100 dark:bg-sky-500/10 px-2 py-0.5 text-xs font-mono font-bold text-sky-600 dark:text-sky-400"
                                        >/mode</code
                                    >
                                    <span
                                        class="text-sm text-gray-600 dark:text-slate-400"
                                        >Switch AI work mode
                                        (autonomous/semi-auto/manual)</span
                                    >
                                </div>
                                <div
                                    class="flex items-start gap-3 rounded-lg bg-gray-50 dark:bg-white/5 px-4 py-3"
                                >
                                    <code
                                        class="shrink-0 rounded bg-sky-100 dark:bg-sky-500/10 px-2 py-0.5 text-xs font-mono font-bold text-sky-600 dark:text-sky-400"
                                        >/status</code
                                    >
                                    <span
                                        class="text-sm text-gray-600 dark:text-slate-400"
                                        >Check current Brain status and
                                        settings</span
                                    >
                                </div>
                                <div
                                    class="flex items-start gap-3 rounded-lg bg-gray-50 dark:bg-white/5 px-4 py-3"
                                >
                                    <code
                                        class="shrink-0 rounded bg-sky-100 dark:bg-sky-500/10 px-2 py-0.5 text-xs font-mono font-bold text-sky-600 dark:text-sky-400"
                                        >/knowledge</code
                                    >
                                    <span
                                        class="text-sm text-gray-600 dark:text-slate-400"
                                        >Manage your knowledge base
                                        entries</span
                                    >
                                </div>
                                <div
                                    class="flex items-start gap-3 rounded-lg bg-gray-50 dark:bg-white/5 px-4 py-3"
                                >
                                    <code
                                        class="shrink-0 rounded bg-sky-100 dark:bg-sky-500/10 px-2 py-0.5 text-xs font-mono font-bold text-sky-600 dark:text-sky-400"
                                        >/disconnect</code
                                    >
                                    <span
                                        class="text-sm text-gray-600 dark:text-slate-400"
                                        >Unlink your Telegram account</span
                                    >
                                </div>
                                <div
                                    class="flex items-start gap-3 rounded-lg bg-gray-50 dark:bg-white/5 px-4 py-3"
                                >
                                    <code
                                        class="shrink-0 rounded bg-sky-100 dark:bg-sky-500/10 px-2 py-0.5 text-xs font-mono font-bold text-sky-600 dark:text-sky-400"
                                        >/help</code
                                    >
                                    <span
                                        class="text-sm text-gray-600 dark:text-slate-400"
                                        >Show available commands and help
                                        info</span
                                    >
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
                                ⚙️ Setup
                            </h2>

                            <div class="space-y-4 mb-8">
                                <div
                                    v-for="(step, index) in setupSteps"
                                    :key="index"
                                    class="flex gap-4"
                                >
                                    <div
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-sky-100 dark:bg-sky-500/10 text-sm font-bold text-sky-600 dark:text-sky-400 ring-1 ring-sky-500/20"
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
                                :href="route('brain.settings')"
                                class="inline-flex items-center gap-2 rounded-xl bg-sky-600 px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-sky-500"
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
                                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                    />
                                </svg>
                                Go to Brain Settings
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
                                Resources
                            </h3>
                            <div class="space-y-3">
                                <a
                                    href="https://t.me/BotFather"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex items-center justify-between rounded-xl bg-gray-50 dark:bg-white/5 px-4 py-3 text-gray-700 dark:text-slate-300 transition-colors hover:bg-gray-100 dark:hover:bg-white/10 hover:text-gray-900 dark:hover:text-white"
                                >
                                    <span class="flex items-center gap-2">
                                        <svg
                                            class="h-5 w-5 text-sky-500"
                                            fill="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"
                                            />
                                        </svg>
                                        @BotFather
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
                                    href="https://core.telegram.org/bots/api"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex items-center justify-between rounded-xl bg-gray-50 dark:bg-white/5 px-4 py-3 text-gray-700 dark:text-slate-300 transition-colors hover:bg-gray-100 dark:hover:bg-white/10 hover:text-gray-900 dark:hover:text-white"
                                >
                                    <span class="flex items-center gap-2">
                                        <svg
                                            class="h-5 w-5 text-blue-500"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                            />
                                        </svg>
                                        Telegram Bot API Docs
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
                                    :href="route('brain.settings')"
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

                        <!-- Requirements -->
                        <div
                            class="rounded-2xl bg-white dark:bg-slate-800 p-6 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <h3
                                class="font-semibold text-gray-900 dark:text-white mb-4"
                            >
                                Requirements
                            </h3>
                            <ul
                                class="space-y-2 text-sm text-gray-600 dark:text-slate-400"
                            >
                                <li class="flex items-center gap-2">
                                    <svg
                                        class="h-4 w-4 text-emerald-500"
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
                                    Telegram Account
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg
                                        class="h-4 w-4 text-emerald-500"
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
                                    Telegram Bot (via @BotFather)
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg
                                        class="h-4 w-4 text-emerald-500"
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
                                    NetSendo Brain (active)
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg
                                        class="h-4 w-4 text-emerald-500"
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
                                    NetSendo Account
                                </li>
                            </ul>
                        </div>

                        <div
                            class="rounded-2xl bg-gradient-to-br from-sky-500/10 to-blue-500/10 p-6 ring-1 ring-sky-500/20"
                        >
                            <h3
                                class="font-semibold text-gray-900 dark:text-white mb-2"
                            >
                                Need help?
                            </h3>
                            <p
                                class="text-sm text-gray-600 dark:text-slate-400 mb-4"
                            >
                                Check our documentation for detailed setup
                                instructions.
                            </p>
                            <a
                                href="https://netsendo.com/en/docs"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex w-full items-center justify-center rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-sky-500"
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
