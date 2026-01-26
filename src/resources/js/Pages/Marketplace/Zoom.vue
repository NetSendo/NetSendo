<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";

const features = [
    {
        title: "Auto-Create Meeting Links",
        description: "Automatically generate Zoom meeting links for CRM tasks.",
        icon: "🔗",
    },
    {
        title: "Guest Invitations",
        description: "Invite attendees via email directly from CRM tasks.",
        icon: "📧",
    },
    {
        title: "HD Video & Audio",
        description: "High-quality video conferencing for professional meetings.",
        icon: "🎥",
    },
    {
        title: "CRM Contact Integration",
        description: "Auto-add CRM contact email as meeting attendee.",
        icon: "👤",
    },
];

const setupSteps = [
    {
        title: "Create Zoom OAuth App",
        description: "Register an OAuth app in Zoom Marketplace.",
    },
    {
        title: "Configure Credentials",
        description: "Add Client ID and Secret in Settings > Zoom.",
    },
    {
        title: "Connect Account",
        description: "Click 'Connect' to authorize NetSendo.",
    },
    {
        title: "Create Meeting Tasks",
        description: "Enable 'Add Zoom Meeting' when creating tasks.",
    },
];
</script>

<template>
    <Head title="Zoom Integration - NetSendo Marketplace" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Breadcrumb -->
                <div class="mb-8 flex items-center gap-2 text-sm text-gray-500 dark:text-slate-400">
                    <Link
                        :href="route('marketplace.index')"
                        class="hover:text-gray-900 dark:hover:text-white transition-colors"
                    >
                        Marketplace
                    </Link>
                    <span>/</span>
                    <span class="text-gray-900 dark:text-white">Zoom</span>
                </div>

                <div class="grid gap-8 lg:grid-cols-3">
                    <!-- Main Content -->
                    <div class="lg:col-span-2 space-y-8">
                        <!-- Header -->
                        <div class="overflow-hidden rounded-2xl bg-white dark:bg-slate-800 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm">
                            <div class="bg-gradient-to-r from-blue-600/10 to-indigo-600/10 dark:from-blue-600/20 dark:to-indigo-600/20 px-8 py-12">
                                <div class="flex items-start gap-6">
                                    <div class="h-24 w-24 shrink-0 overflow-hidden rounded-2xl bg-white p-3 shadow-lg ring-1 ring-gray-100 dark:ring-transparent flex items-center justify-center">
                                        <svg viewBox="0 0 24 24" class="h-16 w-16">
                                            <circle cx="12" cy="12" r="10" fill="#2D8CFF"/>
                                            <path fill="#FFF" d="M8 9.5v5c0 .28.22.5.5.5h5c.28 0 .5-.22.5-.5v-5c0-.28-.22-.5-.5-.5h-5c-.28 0-.5.22-.5.5z"/>
                                            <path fill="#FFF" d="M15 10.5l2.5-1.5v6l-2.5-1.5v-3z"/>
                                        </svg>
                                    </div>
                                    <div class="pt-2">
                                        <div class="flex items-center gap-3 mb-2">
                                            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                                                Zoom Integration
                                            </h1>
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 dark:bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-500/20">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400"></span>
                                                Active
                                            </span>
                                        </div>
                                        <p class="text-lg text-gray-600 dark:text-slate-300">
                                            Create Zoom video meetings directly from CRM tasks.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="p-8">
                                <p class="text-gray-600 dark:text-slate-400 leading-relaxed text-lg">
                                    Automatically create Zoom video conference links when scheduling meetings in CRM.
                                    Invite attendees via email and share meeting links instantly.
                                    Perfect for sales calls, client meetings, and team collaborations.
                                </p>
                            </div>
                        </div>

                        <!-- Features -->
                        <div class="rounded-2xl bg-white dark:bg-slate-800 p-8 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm">
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">
                                🚀 Features
                            </h2>
                            <div class="grid gap-6 sm:grid-cols-2">
                                <div v-for="feature in features" :key="feature.title" class="flex gap-4">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-50 dark:bg-white/5 text-xl text-gray-600 dark:text-white">
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
                        <div class="rounded-2xl bg-white dark:bg-slate-800 p-8 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm">
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">
                                ⚙️ How to Set Up
                            </h2>
                            <div class="space-y-4">
                                <div v-for="(step, index) in setupSteps" :key="step.title" class="flex gap-4">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-500/10 text-sm font-bold text-blue-600 dark:text-blue-400">
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

                        <!-- OAuth App Instructions -->
                        <div class="rounded-2xl bg-white dark:bg-slate-800 p-8 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm">
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">
                                📋 Create Zoom OAuth App
                            </h2>
                            <div class="space-y-4 text-gray-600 dark:text-slate-400">
                                <p>
                                    1. Go to <a href="https://marketplace.zoom.us/develop/create" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline">Zoom App Marketplace</a>
                                </p>
                                <p>
                                    2. Click "Build App" and select "OAuth" app type
                                </p>
                                <p>
                                    3. Fill in app information and add required scopes:
                                </p>
                                <ul class="list-disc list-inside ml-4 space-y-1">
                                    <li><code class="bg-gray-100 dark:bg-slate-700 px-2 py-0.5 rounded text-sm">meeting:write:admin</code></li>
                                    <li><code class="bg-gray-100 dark:bg-slate-700 px-2 py-0.5 rounded text-sm">meeting:read:admin</code></li>
                                    <li><code class="bg-gray-100 dark:bg-slate-700 px-2 py-0.5 rounded text-sm">user:read:admin</code></li>
                                </ul>
                                <p>
                                    4. Copy Client ID and Client Secret to Settings
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Quick Actions -->
                        <div class="rounded-2xl bg-white dark:bg-slate-800 p-6 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-4">
                                Quick Actions
                            </h3>
                            <div class="space-y-3">
                                <Link
                                    :href="route('settings.zoom.index')"
                                    class="flex items-center gap-3 w-full rounded-xl bg-blue-600 px-4 py-3 text-sm font-medium text-white hover:bg-blue-700 transition-colors"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    Configure Zoom
                                </Link>
                                <Link
                                    :href="route('crm.tasks.index')"
                                    class="flex items-center gap-3 w-full rounded-xl bg-gray-100 dark:bg-white/5 px-4 py-3 text-sm font-medium text-gray-700 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-white/10 transition-colors"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                    </svg>
                                    Create Meeting Task
                                </Link>
                            </div>
                        </div>

                        <!-- Requirements -->
                        <div class="rounded-2xl bg-white dark:bg-slate-800 p-6 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-4">
                                Requirements
                            </h3>
                            <ul class="space-y-2 text-sm text-gray-600 dark:text-slate-400">
                                <li class="flex items-center gap-2">
                                    <svg class="h-4 w-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Zoom account (Pro/Business recommended)
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="h-4 w-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Zoom OAuth App credentials
                                </li>
                                <li class="flex items-center gap-2">
                                    <svg class="h-4 w-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Meeting scopes enabled
                                </li>
                            </ul>
                        </div>

                        <!-- Documentation -->
                        <div class="rounded-2xl bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 p-6 border border-blue-100 dark:border-blue-500/20">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-2">
                                📚 Documentation
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-slate-400 mb-4">
                                Learn more about Zoom API integration.
                            </p>
                            <a
                                href="https://developers.zoom.us/docs/api/"
                                target="_blank"
                                class="inline-flex items-center gap-2 text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300"
                            >
                                Zoom API Docs
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
