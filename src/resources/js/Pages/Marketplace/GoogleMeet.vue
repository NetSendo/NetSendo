<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";

const features = [
    {
        title: "Auto-Create Meeting Links",
        description:
            "Automatically add Meet links when syncing tasks to Google Calendar.",
        icon: "🔗",
    },
    {
        title: "Guest Invitations",
        description: "Invite attendees via email directly from CRM tasks.",
        icon: "📧",
    },
    {
        title: "Response Tracking",
        description: "See who accepted, declined, or hasn't responded yet.",
        icon: "✅",
    },
    {
        title: "CRM Contact Integration",
        description: "Auto-add CRM contact email as meeting attendee.",
        icon: "👤",
    },
];

const setupSteps = [
    {
        title: "Enable Google Calendar",
        description:
            "First, set up Google Calendar integration in Marketplace.",
    },
    {
        title: "Create a Meeting Task",
        description: "In CRM Tasks, create a new task with type 'Meeting'.",
    },
    {
        title: "Enable Calendar Sync",
        description: "Turn on 'Sync to Google Calendar' toggle.",
    },
    {
        title: "Add Google Meet",
        description: "Enable 'Add Google Meet link' option.",
    },
    {
        title: "Add Attendees",
        description: "Enter email addresses of guests to invite.",
    },
];
</script>

<template>
    <Head title="Google Meet Integration - NetSendo Marketplace" />

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
                        >Google Meet</span
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
                                class="bg-gradient-to-r from-green-600/10 to-blue-600/10 dark:from-green-600/20 dark:to-blue-600/20 px-8 py-12"
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
                                                fill="#00897B"
                                                d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"
                                            />
                                            <path
                                                fill="#FFF"
                                                d="M17 12.5v-1c0-.28-.22-.5-.5-.5h-3v-3c0-.28-.22-.5-.5-.5h-1c-.28 0-.5.22-.5.5v3h-3c-.28 0-.5.22-.5.5v1c0 .28.22.5.5.5h3v3c0 .28.22.5.5.5h1c.28 0 .5-.22.5-.5v-3h3c.28 0 .5-.22.5-.5z"
                                            />
                                            <path
                                                fill="#4285F4"
                                                d="M15.5 9.5l2-2V12l-2-2.5z"
                                            />
                                            <path
                                                fill="#EA4335"
                                                d="M8.5 9.5l-2-2V12l2-2.5z"
                                            />
                                            <path
                                                fill="#FBBC04"
                                                d="M12 15l-2.5 2h5l-2.5-2z"
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
                                                Google Meet Integration
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
                                            Create video meetings directly from
                                            CRM tasks.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="p-8">
                                <p
                                    class="text-gray-600 dark:text-slate-400 leading-relaxed text-lg"
                                >
                                    Automatically create Google Meet video
                                    conference links when scheduling meetings in
                                    CRM. Invite attendees via email and track
                                    their responses. Perfect for sales calls,
                                    client meetings, and team collaborations.
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
                                ⚙️ How to Use
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
                                        <h3
                                            class="font-semibold text-gray-900 dark:text-white"
                                        >
                                            {{ step.title }}
                                        </h3>
                                        <p
                                            class="text-sm text-gray-600 dark:text-slate-400 mt-1"
                                        >
                                            {{ step.description }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Quick Actions -->
                        <div
                            class="rounded-2xl bg-white dark:bg-slate-800 p-6 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <h3
                                class="text-lg font-semibold text-gray-900 dark:text-white mb-4"
                            >
                                Quick Actions
                            </h3>
                            <div class="space-y-3">
                                <Link
                                    :href="route('crm.tasks.index')"
                                    class="flex items-center gap-3 rounded-xl bg-gradient-to-r from-green-600 to-teal-600 px-4 py-3 text-white transition-all hover:shadow-lg hover:shadow-green-500/25"
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
                                            d="M12 6v6m0 0v6m0-6h6m-6 0H6"
                                        />
                                    </svg>
                                    <span class="font-medium"
                                        >Create Meeting Task</span
                                    >
                                </Link>
                                <Link
                                    :href="route('marketplace.google-calendar')"
                                    class="flex items-center gap-3 rounded-xl bg-gray-50 dark:bg-white/5 px-4 py-3 text-gray-700 dark:text-slate-300 transition-colors hover:bg-gray-100 dark:hover:bg-white/10"
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
                                    <span class="font-medium"
                                        >Setup Google Calendar</span
                                    >
                                </Link>
                            </div>
                        </div>

                        <!-- Requirements -->
                        <div
                            class="rounded-2xl bg-white dark:bg-slate-800 p-6 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm"
                        >
                            <h3
                                class="text-lg font-semibold text-gray-900 dark:text-white mb-4"
                            >
                                Requirements
                            </h3>
                            <ul class="space-y-3">
                                <li class="flex items-start gap-3">
                                    <svg
                                        class="h-5 w-5 text-emerald-500 mt-0.5 shrink-0"
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
                                    <span
                                        class="text-sm text-gray-600 dark:text-slate-400"
                                        >Google Calendar integration
                                        enabled</span
                                    >
                                </li>
                                <li class="flex items-start gap-3">
                                    <svg
                                        class="h-5 w-5 text-emerald-500 mt-0.5 shrink-0"
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
                                    <span
                                        class="text-sm text-gray-600 dark:text-slate-400"
                                        >Google Workspace or personal Gmail
                                        account</span
                                    >
                                </li>
                                <li class="flex items-start gap-3">
                                    <svg
                                        class="h-5 w-5 text-emerald-500 mt-0.5 shrink-0"
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
                                    <span
                                        class="text-sm text-gray-600 dark:text-slate-400"
                                        >Calendar API with conferenceData
                                        scope</span
                                    >
                                </li>
                            </ul>
                        </div>

                        <!-- Info Box -->
                        <div
                            class="rounded-2xl bg-gradient-to-br from-green-50 to-teal-50 dark:from-green-500/10 dark:to-teal-500/10 p-6 ring-1 ring-green-200 dark:ring-green-500/20"
                        >
                            <div class="flex items-start gap-3">
                                <span class="text-2xl">💡</span>
                                <div>
                                    <h4
                                        class="font-semibold text-gray-900 dark:text-white"
                                    >
                                        Pro Tip
                                    </h4>
                                    <p
                                        class="mt-1 text-sm text-gray-600 dark:text-slate-400"
                                    >
                                        When you select a CRM contact for a
                                        meeting task, their email is
                                        automatically added to attendees if they
                                        have one on file.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
