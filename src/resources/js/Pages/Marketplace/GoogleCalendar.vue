<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";

const features = [
    {
        title: "Two-Way Sync",
        description: "Changes sync automatically in both directions.",
        icon: "🔄",
    },
    {
        title: "Task Synchronization",
        description: "CRM tasks appear as calendar events.",
        icon: "✅",
    },
    {
        title: "Reminders",
        description: "Get notified about upcoming tasks.",
        icon: "🔔",
    },
    {
        title: "Real-Time Updates",
        description: "Instant sync via Google webhooks.",
        icon: "⚡",
    },
];

const setupSteps = [
    {
        title: "Create Google Cloud Project",
        description: "Go to Google Cloud Console and create a new project.",
    },
    {
        title: "Enable Calendar API",
        description: "Enable Google Calendar API from the API Library.",
    },
    {
        title: "Configure OAuth Consent",
        description: "Set up OAuth consent screen with calendar scopes.",
    },
    {
        title: "Add Credentials to NetSendo",
        description:
            "Enter Client ID and Client Secret in Settings → Integrations.",
    },
    {
        title: "Connect Calendar",
        description: "Authorize your Google Calendar in Settings → Calendar.",
    },
];
</script>

<template>
    <Head title="Google Calendar Integration - NetSendo Marketplace" />

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
                    <span class="text-gray-900 dark:text-white"
                        >Google Calendar</span
                    >
                </div>

                <div class="grid gap-8 lg:grid-cols-3">
                    <!-- Main Content -->
                    <div class="lg:col-span-2 space-y-8">
                        <!-- Header -->
                        <div
                            class="overflow-hidden rounded-2xl bg-white dark:bg-slate-800 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <div
                                class="bg-gradient-to-r from-blue-600/10 to-green-600/10 dark:from-blue-600/20 dark:to-green-600/20 px-8 py-12"
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
                                                fill="#4285F4"
                                                d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20a2 2 0 002 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11z"
                                            />
                                            <path
                                                fill="#34A853"
                                                d="M7 11h2v2H7zm4 0h2v2h-2zm4 0h2v2h-2zM7 15h2v2H7zm4 0h2v2h-2zm4 0h2v2h-2z"
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
                                                Google Calendar Integration
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
                                            Sync CRM tasks with Google Calendar
                                            for seamless scheduling.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="p-8">
                                <p
                                    class="text-gray-600 dark:text-slate-400 leading-relaxed text-lg"
                                >
                                    Two-way synchronization between NetSendo CRM
                                    tasks and Google Calendar. Create, update,
                                    and track tasks across both platforms
                                    automatically. Never miss a follow-up with
                                    real-time sync and reminders.
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
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-500/10 text-sm font-bold text-blue-600 dark:text-blue-400 ring-1 ring-blue-500/20"
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
                                :href="route('settings.calendar.index')"
                                class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-3 text-sm font-semibold text-white transition-colors hover:bg-blue-500"
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
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                    />
                                </svg>
                                Go to Calendar Settings
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
                                    href="https://console.cloud.google.com/"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex items-center justify-between rounded-xl bg-gray-50 dark:bg-white/5 px-4 py-3 text-gray-700 dark:text-slate-300 transition-colors hover:bg-gray-100 dark:hover:bg-white/10 hover:text-gray-900 dark:hover:text-white"
                                >
                                    <span class="flex items-center gap-2">
                                        <svg
                                            class="h-5 w-5 text-blue-500"
                                            fill="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                d="M12.48 10.92v3.28h7.84c-.24 1.84-.853 3.187-1.787 4.133-1.147 1.147-2.933 2.4-6.053 2.4-4.827 0-8.6-3.893-8.6-8.72s3.773-8.72 8.6-8.72c2.6 0 4.507 1.027 5.907 2.347l2.307-2.307C18.747 1.44 16.133 0 12.48 0 5.867 0 .307 5.387.307 12s5.56 12 12.173 12c3.573 0 6.267-1.173 8.373-3.36 2.16-2.16 2.84-5.213 2.84-7.667 0-.76-.053-1.467-.173-2.053H12.48z"
                                            />
                                        </svg>
                                        Google Cloud Console
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
                                    href="https://developers.google.com/calendar/api/guides/overview"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex items-center justify-between rounded-xl bg-gray-50 dark:bg-white/5 px-4 py-3 text-gray-700 dark:text-slate-300 transition-colors hover:bg-gray-100 dark:hover:bg-white/10 hover:text-gray-900 dark:hover:text-white"
                                >
                                    <span class="flex items-center gap-2">
                                        <svg
                                            class="h-5 w-5 text-green-500"
                                            fill="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20a2 2 0 002 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11zM9 11H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zm-8 4H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"
                                            />
                                        </svg>
                                        Calendar API Documentation
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
                                    :href="route('crm.tasks.index')"
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
                                                d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
                                            />
                                        </svg>
                                        Manage CRM Tasks
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
                                    Google Account
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
                                    Google Cloud Project
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
                                    Calendar API Enabled
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
                            class="rounded-2xl bg-gradient-to-br from-blue-500/10 to-green-500/10 p-6 ring-1 ring-blue-500/20"
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
                                class="inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-blue-500"
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
