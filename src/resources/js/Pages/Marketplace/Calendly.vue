<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";

const features = [
    {
        title: "Automatic CRM Sync",
        description: "Booking data flows directly into your CRM contacts.",
        icon: "👤",
    },
    {
        title: "Mailing List Integration",
        description: "Add invitees to mailing lists automatically.",
        icon: "📧",
    },
    {
        title: "Smart Tagging",
        description: "Apply tags based on event type booked.",
        icon: "🏷️",
    },
    {
        title: "Task Creation",
        description: "Automatically create CRM tasks for meetings.",
        icon: "✅",
    },
    {
        title: "Webhook Automation",
        description: "Trigger custom automations on booking events.",
        icon: "⚡",
    },
    {
        title: "Cancellation Handling",
        description: "Track cancellations and no-shows in your CRM.",
        icon: "📊",
    },
];

const setupSteps = [
    {
        title: "Create Calendly Developer App",
        description:
            "Go to <a href='https://developer.calendly.com' target='_blank' class='text-blue-600 hover:underline'>developer.calendly.com</a> and create a new OAuth application.",
    },
    {
        title: "Configure Redirect URI",
        description:
            "Set your redirect URI to: <code class='bg-gray-100 dark:bg-white/10 px-2 py-0.5 rounded text-sm'>{your-domain}/settings/calendly/callback</code>",
    },
    {
        title: "Add Credentials to NetSendo",
        description:
            "Copy Client ID and Client Secret to your .env file as CALENDLY_CLIENT_ID and CALENDLY_CLIENT_SECRET.",
    },
    {
        title: "Connect Your Account",
        description:
            "Go to Settings → Calendly and click 'Connect with Calendly'.",
    },
    {
        title: "Configure Mappings",
        description:
            "Set up which mailing lists and tags to apply for each event type.",
    },
];
</script>

<template>
    <Head title="Calendly Integration - NetSendo Marketplace" />

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
                    <span class="text-gray-900 dark:text-white">Calendly</span>
                </div>

                <div class="grid gap-8 lg:grid-cols-3">
                    <!-- Main Content -->
                    <div class="lg:col-span-2 space-y-8">
                        <!-- Header -->
                        <div
                            class="overflow-hidden rounded-2xl bg-white dark:bg-slate-800 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <div
                                class="bg-gradient-to-r from-blue-600/10 to-sky-600/10 dark:from-blue-600/20 dark:to-sky-600/20 px-8 py-12"
                            >
                                <div class="flex items-start gap-6">
                                    <div
                                        class="h-24 w-24 shrink-0 overflow-hidden rounded-2xl bg-white p-3 shadow-lg ring-1 ring-gray-100 dark:ring-transparent flex items-center justify-center"
                                    >
                                        <svg
                                            viewBox="0 0 24 24"
                                            class="h-16 w-16"
                                            fill="none"
                                            stroke="#006BFF"
                                            stroke-width="1.5"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                            />
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke="#34A853"
                                                d="M9 16l2 2 4-4"
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
                                                Calendly Integration
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
                                            Sync Calendly bookings with your CRM
                                            and mailing lists automatically.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="p-8">
                                <p
                                    class="text-gray-600 dark:text-slate-400 leading-relaxed text-lg"
                                >
                                    Automatically sync your Calendly
                                    appointments with NetSendo. When someone
                                    books a meeting, their information is
                                    captured in your CRM as a contact, added to
                                    your mailing lists, and tagged based on the
                                    event type. Track cancellations and no-shows
                                    for complete visibility into your scheduling
                                    pipeline.
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
                                ⚙️ Setup Guide
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
                                :href="route('settings.calendly.index')"
                                class="inline-flex items-center gap-2 rounded-xl bg-gradient-to-r from-blue-600 to-sky-600 px-6 py-3 text-sm font-semibold text-white transition-all hover:from-blue-700 hover:to-sky-700 shadow-lg"
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
                                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                                    />
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                    />
                                </svg>
                                Configure Integration
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
                                    href="https://developer.calendly.com"
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
                                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                                            />
                                        </svg>
                                        Calendly Developer Portal
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
                                    href="https://developer.calendly.com/api-docs"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex items-center justify-between rounded-xl bg-gray-50 dark:bg-white/5 px-4 py-3 text-gray-700 dark:text-slate-300 transition-colors hover:bg-gray-100 dark:hover:bg-white/10 hover:text-gray-900 dark:hover:text-white"
                                >
                                    <span class="flex items-center gap-2">
                                        <svg
                                            class="h-5 w-5 text-emerald-500"
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
                                        API Documentation
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
                                    :href="route('crm.contacts.index')"
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
                                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                                            />
                                        </svg>
                                        CRM Contacts
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
                                    Calendly Account
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg
                                        class="h-4 w-4 text-amber-500"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                        />
                                    </svg>
                                    Premium Plan or Higher*
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
                                    Developer App Created
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
                            <p
                                class="mt-4 text-xs text-gray-500 dark:text-gray-500"
                            >
                                * Webhooks require Calendly Premium plan or
                                higher.
                            </p>
                        </div>

                        <div
                            class="rounded-2xl bg-gradient-to-br from-blue-500/10 to-sky-500/10 p-6 ring-1 ring-blue-500/20"
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
