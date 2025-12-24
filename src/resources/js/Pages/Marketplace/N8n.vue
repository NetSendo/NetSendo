<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const features = [
    {
        title: "Manage Subscribers",
        description: "Create, update, delete, and search for subscribers.",
        icon: "👥",
    },
    {
        title: "Manage Lists",
        description: "Retrieve contact lists and their subscribers.",
        icon: "📋",
    },
    {
        title: "Manage Tags",
        description: "Access and organize your tags.",
        icon: "🏷️",
    },
    {
        title: "Dynamic Dropdowns",
        description:
            "Automatically loads your contact lists for easy selection.",
        icon: "⚡",
    },
];

const installSteps = [
    {
        title: "Go to Settings",
        description:
            "Navigate to Settings > Community Nodes in your n8n instance.",
    },
    {
        title: "Select Install",
        description: "Click on the Install button.",
    },
    {
        title: "Enter Package Name",
        description:
            "Enter <code>n8n-nodes-netsendo</code> as the package name.",
    },
    {
        title: "Install",
        description: "Click Install to finish the setup.",
    },
];
</script>

<template>
    <Head title="n8n Integration - NetSendo Marketplace" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Breadcrum -->
                <div
                    class="mb-8 flex items-center gap-2 text-sm text-slate-400"
                >
                    <Link
                        :href="route('marketplace.index')"
                        class="hover:text-white transition-colors"
                    >
                        Marketplace
                    </Link>
                    <span>/</span>
                    <span class="text-white">n8n</span>
                </div>

                <div class="grid gap-8 lg:grid-cols-3">
                    <!-- Main Content -->
                    <div class="lg:col-span-2 space-y-8">
                        <!-- Header -->
                        <div
                            class="overflow-hidden rounded-2xl bg-slate-800 ring-1 ring-white/10"
                        >
                            <div
                                class="bg-gradient-to-r from-red-600/20 to-orange-600/20 px-8 py-12"
                            >
                                <div class="flex items-start gap-6">
                                    <div
                                        class="h-24 w-24 shrink-0 overflow-hidden rounded-2xl bg-white p-2 shadow-lg"
                                    >
                                        <img
                                            src="https://gregciupek.com/wp-content/uploads/2025/12/n8n-logo.png"
                                            alt="n8n Logo"
                                            class="h-full w-full object-contain"
                                        />
                                    </div>
                                    <div class="pt-2">
                                        <h1
                                            class="text-3xl font-bold text-white mb-2"
                                        >
                                            n8n
                                        </h1>
                                        <p class="text-lg text-slate-300">
                                            This is an n8n community node for
                                            NetSendo – a powerful, self-hosted
                                            email marketing automation platform.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="p-8">
                                <p
                                    class="text-slate-400 leading-relaxed text-lg"
                                >
                                    This node allows you to automate your email
                                    marketing workflows directly within n8n,
                                    connecting NetSendo with thousands of other
                                    apps and services.
                                </p>
                            </div>
                        </div>

                        <!-- Features -->
                        <div
                            class="rounded-2xl bg-slate-800 p-8 ring-1 ring-white/10"
                        >
                            <h2 class="text-xl font-bold text-white mb-6">
                                🚀 Features
                            </h2>
                            <div class="grid gap-6 sm:grid-cols-2">
                                <div
                                    v-for="feature in features"
                                    :key="feature.title"
                                    class="flex gap-4"
                                >
                                    <div
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-white/5 text-xl"
                                    >
                                        {{ feature.icon }}
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-white">
                                            {{ feature.title }}
                                        </h3>
                                        <p class="text-sm text-slate-400 mt-1">
                                            {{ feature.description }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Installation -->
                        <div
                            class="rounded-2xl bg-slate-800 p-8 ring-1 ring-white/10"
                        >
                            <h2 class="text-xl font-bold text-white mb-6">
                                📦 Installation
                            </h2>

                            <div class="space-y-4 mb-8">
                                <div
                                    v-for="(step, index) in installSteps"
                                    :key="index"
                                    class="flex gap-4"
                                >
                                    <div
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-500/10 text-sm font-bold text-indigo-400 ring-1 ring-indigo-500/20"
                                    >
                                        {{ index + 1 }}
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-white">
                                            {{ step.title }}
                                        </h3>
                                        <p
                                            class="text-sm text-slate-400 mt-1"
                                            v-html="step.description"
                                        ></p>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="rounded-xl bg-slate-950 p-6 ring-1 ring-white/10"
                            >
                                <p class="text-sm text-slate-400 mb-3">
                                    Alternatively, if you are running n8n via
                                    Docker or npm, you can install it manually:
                                </p>
                                <div
                                    class="flex items-center justify-between rounded-lg bg-white/5 px-4 py-3"
                                >
                                    <code class="text-emerald-400 font-mono"
                                        >npm install n8n-nodes-netsendo</code
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <div
                            class="rounded-2xl bg-slate-800 p-6 ring-1 ring-white/10"
                        >
                            <h3 class="font-semibold text-white mb-4">
                                Resources
                            </h3>
                            <div class="space-y-3">
                                <a
                                    href="https://www.npmjs.com/package/n8n-nodes-netsendo"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex items-center justify-between rounded-xl bg-white/5 px-4 py-3 text-slate-300 transition-colors hover:bg-white/10 hover:text-white"
                                >
                                    <span class="flex items-center gap-2">
                                        <svg
                                            class="h-5 w-5 text-red-500"
                                            viewBox="0 0 24 24"
                                            fill="currentColor"
                                        >
                                            <path
                                                d="M0 7.334v8h6.666v1.332H12v-1.332h12v-8H0zm6.666 6.664H5.334v-4H2.667v4H1.335V8.667h5.331v5.331zm4 0v1.336H8.001V8.667h5.334v5.332h-2.669v-.001zm12.001 0h-1.33l-1.334-4v4h-2.667V8.667h2.667l1.334 4v-4h1.333v5.331h.001z"
                                            />
                                        </svg>
                                        NPM Package
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

                        <div
                            class="rounded-2xl bg-gradient-to-br from-indigo-500/10 to-purple-500/10 p-6 ring-1 ring-indigo-500/20"
                        >
                            <h3 class="font-semibold text-white mb-2">
                                Need help?
                            </h3>
                            <p class="text-sm text-slate-400 mb-4">
                                Check out our documentation for more details on
                                how to use this integration.
                            </p>
                            <a
                                href="https://support.netsendo.com/"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-indigo-500"
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
