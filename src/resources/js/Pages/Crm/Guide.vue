<script setup>
import { ref, computed, onMounted } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";

// Active section for navigation
const activeSection = ref("intro");
const expandedSections = ref(["intro"]);

// Scroll to section
const scrollToSection = (sectionId) => {
    activeSection.value = sectionId;
    const element = document.getElementById(sectionId);
    if (element) {
        element.scrollIntoView({ behavior: "smooth", block: "start" });
    }
};

// Toggle section expansion
const toggleSection = (sectionId) => {
    const index = expandedSections.value.indexOf(sectionId);
    if (index === -1) {
        expandedSections.value.push(sectionId);
    } else {
        expandedSections.value.splice(index, 1);
    }
};

const isExpanded = (sectionId) => expandedSections.value.includes(sectionId);

// Sections data
const sections = [
    {
        id: "intro",
        icon: "M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z",
    },
    {
        id: "dashboard",
        icon: "M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z",
    },
    {
        id: "contacts",
        icon: "M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z",
    },
    {
        id: "companies",
        icon: "M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4",
    },
    {
        id: "deals",
        icon: "M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z",
    },
    {
        id: "tasks",
        icon: "M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4",
    },
    {
        id: "sequences",
        icon: "M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15",
    },
    {
        id: "import",
        icon: "M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12",
    },
    {
        id: "tips",
        icon: "M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z",
    },
];
</script>

<template>
    <Head :title="$t('crm.guide.title', 'User Guide')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link
                        href="/crm/tasks"
                        class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-300"
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
                                d="M15 19l-7-7 7-7"
                            />
                        </svg>
                    </Link>
                    <div>
                        <h1
                            class="text-2xl font-bold text-slate-900 dark:text-white"
                        >
                            {{ $t("crm.guide.title", "User Guide") }}
                        </h1>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            {{
                                $t(
                                    "crm.guide.subtitle",
                                    "Learn how to use CRM effectively for sales success",
                                )
                            }}
                        </p>
                    </div>
                </div>
            </div>
        </template>

        <div class="flex gap-6">
            <!-- Sidebar Navigation -->
            <aside class="hidden w-64 flex-shrink-0 lg:block">
                <nav
                    class="sticky top-24 space-y-1 rounded-2xl bg-white p-4 shadow-sm dark:bg-slate-800"
                >
                    <h3
                        class="mb-3 px-3 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400"
                    >
                        {{ $t("crm.guide.toc", "Table of Contents") }}
                    </h3>
                    <button
                        v-for="section in sections"
                        :key="section.id"
                        @click="scrollToSection(section.id)"
                        :class="[
                            'flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm transition',
                            activeSection === section.id
                                ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300'
                                : 'text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700',
                        ]"
                    >
                        <svg
                            class="h-4 w-4 flex-shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                :d="section.icon"
                            />
                        </svg>
                        {{
                            $t(
                                `crm.guide.sections.${section.id}.title`,
                                section.id,
                            )
                        }}
                    </button>
                </nav>
            </aside>

            <!-- Main Content -->
            <main class="flex-1 space-y-6">
                <!-- Introduction Section -->
                <section
                    id="intro"
                    class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800"
                >
                    <button
                        @click="toggleSection('intro')"
                        class="flex w-full items-center justify-between text-left"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 dark:bg-indigo-900/30"
                            >
                                <svg
                                    class="h-5 w-5 text-indigo-600 dark:text-indigo-400"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                    />
                                </svg>
                            </div>
                            <h2
                                class="text-xl font-semibold text-slate-900 dark:text-white"
                            >
                                {{
                                    $t(
                                        "crm.guide.sections.intro.title",
                                        "Introduction",
                                    )
                                }}
                            </h2>
                        </div>
                        <svg
                            :class="[
                                'h-5 w-5 text-slate-400 transition',
                                isExpanded('intro') ? 'rotate-180' : '',
                            ]"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 9l-7 7-7-7"
                            />
                        </svg>
                    </button>
                    <div
                        v-show="isExpanded('intro')"
                        class="mt-4 space-y-4 text-slate-600 dark:text-slate-300"
                    >
                        <p>
                            {{
                                $t(
                                    "crm.guide.sections.intro.p1",
                                    "Welcome to the Sales CRM! This powerful module helps you manage your entire sales process - from lead generation to closing deals.",
                                )
                            }}
                        </p>
                        <div
                            class="rounded-xl bg-indigo-50 p-4 dark:bg-indigo-900/20"
                        >
                            <h4
                                class="mb-2 font-medium text-indigo-900 dark:text-indigo-200"
                            >
                                {{
                                    $t(
                                        "crm.guide.sections.intro.features_title",
                                        "Key Features:",
                                    )
                                }}
                            </h4>
                            <ul
                                class="space-y-1 text-sm text-indigo-800 dark:text-indigo-300"
                            >
                                <li>
                                    ✓
                                    {{
                                        $t(
                                            "crm.guide.sections.intro.feature1",
                                            "Contact and company management",
                                        )
                                    }}
                                </li>
                                <li>
                                    ✓
                                    {{
                                        $t(
                                            "crm.guide.sections.intro.feature2",
                                            "Visual sales pipeline with drag-and-drop",
                                        )
                                    }}
                                </li>
                                <li>
                                    ✓
                                    {{
                                        $t(
                                            "crm.guide.sections.intro.feature3",
                                            "Task management with Google Calendar sync",
                                        )
                                    }}
                                </li>
                                <li>
                                    ✓
                                    {{
                                        $t(
                                            "crm.guide.sections.intro.feature4",
                                            "Automated follow-up sequences",
                                        )
                                    }}
                                </li>
                                <li>
                                    ✓
                                    {{
                                        $t(
                                            "crm.guide.sections.intro.feature5",
                                            "CSV import for bulk data upload",
                                        )
                                    }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- Dashboard Section -->
                <section
                    id="dashboard"
                    class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800"
                >
                    <button
                        @click="toggleSection('dashboard')"
                        class="flex w-full items-center justify-between text-left"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-900/30"
                            >
                                <svg
                                    class="h-5 w-5 text-emerald-600 dark:text-emerald-400"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"
                                    />
                                </svg>
                            </div>
                            <h2
                                class="text-xl font-semibold text-slate-900 dark:text-white"
                            >
                                {{
                                    $t(
                                        "crm.guide.sections.dashboard.title",
                                        "Dashboard",
                                    )
                                }}
                            </h2>
                        </div>
                        <svg
                            :class="[
                                'h-5 w-5 text-slate-400 transition',
                                isExpanded('dashboard') ? 'rotate-180' : '',
                            ]"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 9l-7 7-7-7"
                            />
                        </svg>
                    </button>
                    <div
                        v-show="isExpanded('dashboard')"
                        class="mt-4 space-y-4 text-slate-600 dark:text-slate-300"
                    >
                        <p>
                            {{
                                $t(
                                    "crm.guide.sections.dashboard.p1",
                                    "The CRM Dashboard gives you a quick overview of your sales performance and pending activities.",
                                )
                            }}
                        </p>
                        <h4 class="font-medium text-slate-900 dark:text-white">
                            {{
                                $t(
                                    "crm.guide.sections.dashboard.widgets_title",
                                    "Dashboard Widgets:",
                                )
                            }}
                        </h4>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div
                                class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"
                            >
                                <span class="font-medium"
                                    >📊
                                    {{
                                        $t(
                                            "crm.guide.sections.dashboard.widget1_title",
                                            "Statistics Cards",
                                        )
                                    }}</span
                                >
                                <p class="mt-1 text-sm">
                                    {{
                                        $t(
                                            "crm.guide.sections.dashboard.widget1_desc",
                                            "View total contacts, open deals, won deals this month, and pending tasks at a glance.",
                                        )
                                    }}
                                </p>
                            </div>
                            <div
                                class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"
                            >
                                <span class="font-medium"
                                    >⚠️
                                    {{
                                        $t(
                                            "crm.guide.sections.dashboard.widget2_title",
                                            "Overdue Tasks",
                                        )
                                    }}</span
                                >
                                <p class="mt-1 text-sm">
                                    {{
                                        $t(
                                            "crm.guide.sections.dashboard.widget2_desc",
                                            "See tasks that need immediate attention - highlighted in red for priority.",
                                        )
                                    }}
                                </p>
                            </div>
                            <div
                                class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"
                            >
                                <span class="font-medium"
                                    >🔥
                                    {{
                                        $t(
                                            "crm.guide.sections.dashboard.widget3_title",
                                            "Hot Leads",
                                        )
                                    }}</span
                                >
                                <p class="mt-1 text-sm">
                                    {{
                                        $t(
                                            "crm.guide.sections.dashboard.widget3_desc",
                                            "Your highest-scoring leads that are most likely to convert.",
                                        )
                                    }}
                                </p>
                            </div>
                            <div
                                class="rounded-lg border border-slate-200 p-3 dark:border-slate-700"
                            >
                                <span class="font-medium"
                                    >📅
                                    {{
                                        $t(
                                            "crm.guide.sections.dashboard.widget4_title",
                                            "Today's Tasks",
                                        )
                                    }}</span
                                >
                                <p class="mt-1 text-sm">
                                    {{
                                        $t(
                                            "crm.guide.sections.dashboard.widget4_desc",
                                            "Your scheduled activities for today - calls, meetings, and follow-ups.",
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Contacts Section -->
                <section
                    id="contacts"
                    class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800"
                >
                    <button
                        @click="toggleSection('contacts')"
                        class="flex w-full items-center justify-between text-left"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-900/30"
                            >
                                <svg
                                    class="h-5 w-5 text-blue-600 dark:text-blue-400"
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
                            </div>
                            <h2
                                class="text-xl font-semibold text-slate-900 dark:text-white"
                            >
                                {{
                                    $t(
                                        "crm.guide.sections.contacts.title",
                                        "Managing Contacts",
                                    )
                                }}
                            </h2>
                        </div>
                        <svg
                            :class="[
                                'h-5 w-5 text-slate-400 transition',
                                isExpanded('contacts') ? 'rotate-180' : '',
                            ]"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 9l-7 7-7-7"
                            />
                        </svg>
                    </button>
                    <div
                        v-show="isExpanded('contacts')"
                        class="mt-4 space-y-4 text-slate-600 dark:text-slate-300"
                    >
                        <p>
                            {{
                                $t(
                                    "crm.guide.sections.contacts.p1",
                                    "Contacts are the heart of your CRM. Each contact represents a person you're doing business with.",
                                )
                            }}
                        </p>

                        <h4 class="font-medium text-slate-900 dark:text-white">
                            {{
                                $t(
                                    "crm.guide.sections.contacts.statuses_title",
                                    "Contact Statuses:",
                                )
                            }}
                        </h4>
                        <div class="flex flex-wrap gap-2">
                            <span
                                class="rounded-full bg-blue-100 px-3 py-1 text-sm text-blue-700 dark:bg-blue-900/30 dark:text-blue-300"
                                >Lead</span
                            >
                            <span
                                class="rounded-full bg-purple-100 px-3 py-1 text-sm text-purple-700 dark:bg-purple-900/30 dark:text-purple-300"
                                >Prospect</span
                            >
                            <span
                                class="rounded-full bg-green-100 px-3 py-1 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-300"
                                >Client</span
                            >
                            <span
                                class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-700 dark:bg-slate-700 dark:text-slate-300"
                                >Dormant</span
                            >
                            <span
                                class="rounded-full bg-red-100 px-3 py-1 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-300"
                                >Archived</span
                            >
                        </div>

                        <h4 class="font-medium text-slate-900 dark:text-white">
                            {{
                                $t(
                                    "crm.guide.sections.contacts.howto_title",
                                    "How to Add a Contact:",
                                )
                            }}
                        </h4>
                        <ol class="list-inside list-decimal space-y-2 text-sm">
                            <li>
                                {{
                                    $t(
                                        "crm.guide.sections.contacts.step1",
                                        "Click 'Create Contact' button in the top right",
                                    )
                                }}
                            </li>
                            <li>
                                {{
                                    $t(
                                        "crm.guide.sections.contacts.step2",
                                        "Fill in email (required), name, phone, and other details",
                                    )
                                }}
                            </li>
                            <li>
                                {{
                                    $t(
                                        "crm.guide.sections.contacts.step3",
                                        "Assign to a company (optional)",
                                    )
                                }}
                            </li>
                            <li>
                                {{
                                    $t(
                                        "crm.guide.sections.contacts.step4",
                                        "Set the initial status (Lead, Prospect, etc.)",
                                    )
                                }}
                            </li>
                            <li>
                                {{
                                    $t(
                                        "crm.guide.sections.contacts.step5",
                                        "Save - the contact is now ready for follow-up!",
                                    )
                                }}
                            </li>
                        </ol>

                        <div
                            class="rounded-xl bg-amber-50 p-4 dark:bg-amber-900/20"
                        >
                            <h4
                                class="mb-1 font-medium text-amber-900 dark:text-amber-200"
                            >
                                💡
                                {{
                                    $t(
                                        "crm.guide.sections.contacts.tip_title",
                                        "Pro Tip",
                                    )
                                }}
                            </h4>
                            <p
                                class="text-sm text-amber-800 dark:text-amber-300"
                            >
                                {{
                                    $t(
                                        "crm.guide.sections.contacts.tip_text",
                                        "Use filters to quickly find contacts by status, company, or owner. You can also search by name or email.",
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Companies Section -->
                <section
                    id="companies"
                    class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800"
                >
                    <button
                        @click="toggleSection('companies')"
                        class="flex w-full items-center justify-between text-left"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-100 dark:bg-violet-900/30"
                            >
                                <svg
                                    class="h-5 w-5 text-violet-600 dark:text-violet-400"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                                    />
                                </svg>
                            </div>
                            <h2
                                class="text-xl font-semibold text-slate-900 dark:text-white"
                            >
                                {{
                                    $t(
                                        "crm.guide.sections.companies.title",
                                        "Companies",
                                    )
                                }}
                            </h2>
                        </div>
                        <svg
                            :class="[
                                'h-5 w-5 text-slate-400 transition',
                                isExpanded('companies') ? 'rotate-180' : '',
                            ]"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 9l-7 7-7-7"
                            />
                        </svg>
                    </button>
                    <div
                        v-show="isExpanded('companies')"
                        class="mt-4 space-y-4 text-slate-600 dark:text-slate-300"
                    >
                        <p>
                            {{
                                $t(
                                    "crm.guide.sections.companies.p1",
                                    "Companies help you organize contacts by organization. Link multiple contacts to a single company for B2B sales.",
                                )
                            }}
                        </p>

                        <h4 class="font-medium text-slate-900 dark:text-white">
                            {{
                                $t(
                                    "crm.guide.sections.companies.fields_title",
                                    "Company Information:",
                                )
                            }}
                        </h4>
                        <ul class="list-inside list-disc space-y-1 text-sm">
                            <li>
                                {{
                                    $t(
                                        "crm.guide.sections.companies.field1",
                                        "Company name and domain",
                                    )
                                }}
                            </li>
                            <li>
                                {{
                                    $t(
                                        "crm.guide.sections.companies.field2",
                                        "Industry and company size",
                                    )
                                }}
                            </li>
                            <li>
                                {{
                                    $t(
                                        "crm.guide.sections.companies.field3",
                                        "Address, city, and country",
                                    )
                                }}
                            </li>
                            <li>
                                {{
                                    $t(
                                        "crm.guide.sections.companies.field4",
                                        "Tax ID (NIP) and REGON for Polish companies",
                                    )
                                }}
                            </li>
                        </ul>

                        <div
                            class="rounded-xl bg-green-50 p-4 dark:bg-green-900/20"
                        >
                            <h4
                                class="mb-1 font-medium text-green-900 dark:text-green-200"
                            >
                                🇵🇱
                                {{
                                    $t(
                                        "crm.guide.sections.companies.lookup_title",
                                        "Polish Company Lookup",
                                    )
                                }}
                            </h4>
                            <p
                                class="text-sm text-green-800 dark:text-green-300"
                            >
                                {{
                                    $t(
                                        "crm.guide.sections.companies.lookup_text",
                                        "Enter NIP or REGON and click 'Fetch data' to automatically fill company information from the REGON database.",
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Deals Section -->
                <section
                    id="deals"
                    class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800"
                >
                    <button
                        @click="toggleSection('deals')"
                        class="flex w-full items-center justify-between text-left"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-green-100 dark:bg-green-900/30"
                            >
                                <svg
                                    class="h-5 w-5 text-green-600 dark:text-green-400"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                                    />
                                </svg>
                            </div>
                            <h2
                                class="text-xl font-semibold text-slate-900 dark:text-white"
                            >
                                {{
                                    $t(
                                        "crm.guide.sections.deals.title",
                                        "Deals Pipeline",
                                    )
                                }}
                            </h2>
                        </div>
                        <svg
                            :class="[
                                'h-5 w-5 text-slate-400 transition',
                                isExpanded('deals') ? 'rotate-180' : '',
                            ]"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 9l-7 7-7-7"
                            />
                        </svg>
                    </button>
                    <div
                        v-show="isExpanded('deals')"
                        class="mt-4 space-y-4 text-slate-600 dark:text-slate-300"
                    >
                        <p>
                            {{
                                $t(
                                    "crm.guide.sections.deals.p1",
                                    "The Deals view shows your sales pipeline as a Kanban board. Drag deals between stages to track progress.",
                                )
                            }}
                        </p>

                        <h4 class="font-medium text-slate-900 dark:text-white">
                            {{
                                $t(
                                    "crm.guide.sections.deals.stages_title",
                                    "Pipeline Stages:",
                                )
                            }}
                        </h4>
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-lg bg-slate-100 px-3 py-1 text-sm dark:bg-slate-700"
                                >New</span
                            >
                            <span class="text-slate-400">→</span>
                            <span
                                class="rounded-lg bg-blue-100 px-3 py-1 text-sm text-blue-700 dark:bg-blue-900/30 dark:text-blue-300"
                                >Contact</span
                            >
                            <span class="text-slate-400">→</span>
                            <span
                                class="rounded-lg bg-purple-100 px-3 py-1 text-sm text-purple-700 dark:bg-purple-900/30 dark:text-purple-300"
                                >Meeting</span
                            >
                            <span class="text-slate-400">→</span>
                            <span
                                class="rounded-lg bg-amber-100 px-3 py-1 text-sm text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
                                >Offer</span
                            >
                            <span class="text-slate-400">→</span>
                            <span
                                class="rounded-lg bg-orange-100 px-3 py-1 text-sm text-orange-700 dark:bg-orange-900/30 dark:text-orange-300"
                                >Negotiation</span
                            >
                            <span class="text-slate-400">→</span>
                            <span
                                class="rounded-lg bg-green-100 px-3 py-1 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-300"
                                >Won ✓</span
                            >
                        </div>

                        <h4 class="font-medium text-slate-900 dark:text-white">
                            {{
                                $t(
                                    "crm.guide.sections.deals.howto_title",
                                    "Working with Deals:",
                                )
                            }}
                        </h4>
                        <ol class="list-inside list-decimal space-y-2 text-sm">
                            <li>
                                {{
                                    $t(
                                        "crm.guide.sections.deals.step1",
                                        "Click 'New Deal' to create a sales opportunity",
                                    )
                                }}
                            </li>
                            <li>
                                {{
                                    $t(
                                        "crm.guide.sections.deals.step2",
                                        "Set the deal value, currency, and expected close date",
                                    )
                                }}
                            </li>
                            <li>
                                {{
                                    $t(
                                        "crm.guide.sections.deals.step3",
                                        "Link to a contact and/or company",
                                    )
                                }}
                            </li>
                            <li>
                                {{
                                    $t(
                                        "crm.guide.sections.deals.step4",
                                        "Drag the deal card to move it through stages",
                                    )
                                }}
                            </li>
                            <li>
                                {{
                                    $t(
                                        "crm.guide.sections.deals.step5",
                                        "Move to 'Won' when closed, or 'Lost' if unsuccessful",
                                    )
                                }}
                            </li>
                        </ol>
                    </div>
                </section>

                <!-- Tasks Section -->
                <section
                    id="tasks"
                    class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800"
                >
                    <button
                        @click="toggleSection('tasks')"
                        class="flex w-full items-center justify-between text-left"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-900/30"
                            >
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
                                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
                                    />
                                </svg>
                            </div>
                            <h2
                                class="text-xl font-semibold text-slate-900 dark:text-white"
                            >
                                {{
                                    $t(
                                        "crm.guide.sections.tasks.title",
                                        "Tasks",
                                    )
                                }}
                            </h2>
                        </div>
                        <svg
                            :class="[
                                'h-5 w-5 text-slate-400 transition',
                                isExpanded('tasks') ? 'rotate-180' : '',
                            ]"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 9l-7 7-7-7"
                            />
                        </svg>
                    </button>
                    <div
                        v-show="isExpanded('tasks')"
                        class="mt-4 space-y-4 text-slate-600 dark:text-slate-300"
                    >
                        <p>
                            {{
                                $t(
                                    "crm.guide.sections.tasks.p1",
                                    "Tasks help you stay organized with follow-ups, calls, meetings, and other activities.",
                                )
                            }}
                        </p>

                        <h4 class="font-medium text-slate-900 dark:text-white">
                            {{
                                $t(
                                    "crm.guide.sections.tasks.types_title",
                                    "Task Types:",
                                )
                            }}
                        </h4>
                        <div class="flex flex-wrap gap-2">
                            <span
                                class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-3 py-1 text-sm text-blue-700 dark:bg-blue-900/30 dark:text-blue-300"
                                >📞 Call</span
                            >
                            <span
                                class="inline-flex items-center gap-1 rounded-full bg-green-100 px-3 py-1 text-sm text-green-700 dark:bg-green-900/30 dark:text-green-300"
                                >✉️ Email</span
                            >
                            <span
                                class="inline-flex items-center gap-1 rounded-full bg-purple-100 px-3 py-1 text-sm text-purple-700 dark:bg-purple-900/30 dark:text-purple-300"
                                >📅 Meeting</span
                            >
                            <span
                                class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1 text-sm text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
                                >🔄 Follow-up</span
                            >
                            <span
                                class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-700 dark:bg-slate-700 dark:text-slate-300"
                                >📋 Other</span
                            >
                        </div>

                        <h4 class="font-medium text-slate-900 dark:text-white">
                            {{
                                $t(
                                    "crm.guide.sections.tasks.views_title",
                                    "Task Views:",
                                )
                            }}
                        </h4>
                        <ul class="list-inside list-disc space-y-1 text-sm">
                            <li>
                                <span class="text-red-600 dark:text-red-400">{{
                                    $t(
                                        "crm.guide.sections.tasks.view1",
                                        "Overdue",
                                    )
                                }}</span>
                                -
                                {{
                                    $t(
                                        "crm.guide.sections.tasks.view1_desc",
                                        "Tasks past their due date",
                                    )
                                }}
                            </li>
                            <li>
                                <span
                                    class="text-amber-600 dark:text-amber-400"
                                    >{{
                                        $t(
                                            "crm.guide.sections.tasks.view2",
                                            "Today",
                                        )
                                    }}</span
                                >
                                -
                                {{
                                    $t(
                                        "crm.guide.sections.tasks.view2_desc",
                                        "Tasks due today",
                                    )
                                }}
                            </li>
                            <li>
                                <span
                                    class="text-blue-600 dark:text-blue-400"
                                    >{{
                                        $t(
                                            "crm.guide.sections.tasks.view3",
                                            "Upcoming",
                                        )
                                    }}</span
                                >
                                -
                                {{
                                    $t(
                                        "crm.guide.sections.tasks.view3_desc",
                                        "Tasks scheduled for the future",
                                    )
                                }}
                            </li>
                            <li>
                                <span
                                    class="text-green-600 dark:text-green-400"
                                    >{{
                                        $t(
                                            "crm.guide.sections.tasks.view4",
                                            "Completed",
                                        )
                                    }}</span
                                >
                                -
                                {{
                                    $t(
                                        "crm.guide.sections.tasks.view4_desc",
                                        "Finished tasks",
                                    )
                                }}
                            </li>
                        </ul>

                        <div
                            class="rounded-xl bg-blue-50 p-4 dark:bg-blue-900/20"
                        >
                            <h4
                                class="mb-1 font-medium text-blue-900 dark:text-blue-200"
                            >
                                📅
                                {{
                                    $t(
                                        "crm.guide.sections.tasks.calendar_title",
                                        "Google Calendar Sync",
                                    )
                                }}
                            </h4>
                            <p class="text-sm text-blue-800 dark:text-blue-300">
                                {{
                                    $t(
                                        "crm.guide.sections.tasks.calendar_text",
                                        "Connect your Google Calendar in Settings → Integrations to automatically sync tasks. Enable 'Sync to Google Calendar' when creating a task to see it in your calendar.",
                                    )
                                }}
                            </p>
                        </div>

                        <div
                            class="rounded-xl bg-purple-50 p-4 dark:bg-purple-900/20"
                        >
                            <h4
                                class="mb-1 font-medium text-purple-900 dark:text-purple-200"
                            >
                                🔄
                                {{
                                    $t(
                                        "crm.guide.sections.tasks.recurring_title",
                                        "Recurring Tasks",
                                    )
                                }}
                            </h4>
                            <p
                                class="text-sm text-purple-800 dark:text-purple-300"
                            >
                                {{
                                    $t(
                                        "crm.guide.sections.tasks.recurring_text",
                                        "Enable 'Recurring Task' to automatically create follow-up tasks on a schedule - daily, weekly, monthly, or custom intervals.",
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Sequences Section -->
                <section
                    id="sequences"
                    class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800"
                >
                    <button
                        @click="toggleSection('sequences')"
                        class="flex w-full items-center justify-between text-left"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-pink-100 dark:bg-pink-900/30"
                            >
                                <svg
                                    class="h-5 w-5 text-pink-600 dark:text-pink-400"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                    />
                                </svg>
                            </div>
                            <h2
                                class="text-xl font-semibold text-slate-900 dark:text-white"
                            >
                                {{
                                    $t(
                                        "crm.guide.sections.sequences.title",
                                        "Follow-up Sequences",
                                    )
                                }}
                            </h2>
                        </div>
                        <svg
                            :class="[
                                'h-5 w-5 text-slate-400 transition',
                                isExpanded('sequences') ? 'rotate-180' : '',
                            ]"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 9l-7 7-7-7"
                            />
                        </svg>
                    </button>
                    <div
                        v-show="isExpanded('sequences')"
                        class="mt-4 space-y-4 text-slate-600 dark:text-slate-300"
                    >
                        <p>
                            {{
                                $t(
                                    "crm.guide.sections.sequences.p1",
                                    "Sequences automate your follow-up process. Define a series of tasks that are automatically created based on triggers.",
                                )
                            }}
                        </p>

                        <h4 class="font-medium text-slate-900 dark:text-white">
                            {{
                                $t(
                                    "crm.guide.sections.sequences.triggers_title",
                                    "Available Triggers:",
                                )
                            }}
                        </h4>
                        <ul class="list-inside list-disc space-y-1 text-sm">
                            <li>
                                <strong>{{
                                    $t(
                                        "crm.guide.sections.sequences.trigger1",
                                        "Manual",
                                    )
                                }}</strong>
                                -
                                {{
                                    $t(
                                        "crm.guide.sections.sequences.trigger1_desc",
                                        "Start the sequence yourself",
                                    )
                                }}
                            </li>
                            <li>
                                <strong>{{
                                    $t(
                                        "crm.guide.sections.sequences.trigger2",
                                        "On Contact Created",
                                    )
                                }}</strong>
                                -
                                {{
                                    $t(
                                        "crm.guide.sections.sequences.trigger2_desc",
                                        "When a new contact is added",
                                    )
                                }}
                            </li>
                            <li>
                                <strong>{{
                                    $t(
                                        "crm.guide.sections.sequences.trigger3",
                                        "On Deal Created",
                                    )
                                }}</strong>
                                -
                                {{
                                    $t(
                                        "crm.guide.sections.sequences.trigger3_desc",
                                        "When a new deal is created",
                                    )
                                }}
                            </li>
                            <li>
                                <strong>{{
                                    $t(
                                        "crm.guide.sections.sequences.trigger4",
                                        "On Task Completed",
                                    )
                                }}</strong>
                                -
                                {{
                                    $t(
                                        "crm.guide.sections.sequences.trigger4_desc",
                                        "When any task is marked complete",
                                    )
                                }}
                            </li>
                            <li>
                                <strong>{{
                                    $t(
                                        "crm.guide.sections.sequences.trigger5",
                                        "On Deal Stage Changed",
                                    )
                                }}</strong>
                                -
                                {{
                                    $t(
                                        "crm.guide.sections.sequences.trigger5_desc",
                                        "When a deal moves to a new stage",
                                    )
                                }}
                            </li>
                        </ul>

                        <h4 class="font-medium text-slate-900 dark:text-white">
                            {{
                                $t(
                                    "crm.guide.sections.sequences.example_title",
                                    "Example Sequence:",
                                )
                            }}
                        </h4>
                        <div
                            class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"
                        >
                            <p class="mb-3 text-sm font-medium">
                                {{
                                    $t(
                                        "crm.guide.sections.sequences.example_name",
                                        "New Lead Follow-up",
                                    )
                                }}
                            </p>
                            <div class="space-y-2 text-sm">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-600"
                                        >1</span
                                    >
                                    <span>{{
                                        $t(
                                            "crm.guide.sections.sequences.example_step1",
                                            "Day 1: Send welcome email",
                                        )
                                    }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-600"
                                        >2</span
                                    >
                                    <span>{{
                                        $t(
                                            "crm.guide.sections.sequences.example_step2",
                                            "Day 3: Call to introduce yourself",
                                        )
                                    }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-600"
                                        >3</span
                                    >
                                    <span>{{
                                        $t(
                                            "crm.guide.sections.sequences.example_step3",
                                            "Day 7: Send case study",
                                        )
                                    }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-600"
                                        >4</span
                                    >
                                    <span>{{
                                        $t(
                                            "crm.guide.sections.sequences.example_step4",
                                            "Day 14: Schedule demo meeting",
                                        )
                                    }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Import Section -->
                <section
                    id="import"
                    class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800"
                >
                    <button
                        @click="toggleSection('import')"
                        class="flex w-full items-center justify-between text-left"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-cyan-100 dark:bg-cyan-900/30"
                            >
                                <svg
                                    class="h-5 w-5 text-cyan-600 dark:text-cyan-400"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"
                                    />
                                </svg>
                            </div>
                            <h2
                                class="text-xl font-semibold text-slate-900 dark:text-white"
                            >
                                {{
                                    $t(
                                        "crm.guide.sections.import.title",
                                        "Importing Data",
                                    )
                                }}
                            </h2>
                        </div>
                        <svg
                            :class="[
                                'h-5 w-5 text-slate-400 transition',
                                isExpanded('import') ? 'rotate-180' : '',
                            ]"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 9l-7 7-7-7"
                            />
                        </svg>
                    </button>
                    <div
                        v-show="isExpanded('import')"
                        class="mt-4 space-y-4 text-slate-600 dark:text-slate-300"
                    >
                        <p>
                            {{
                                $t(
                                    "crm.guide.sections.import.p1",
                                    "Import contacts in bulk from a CSV file. This is perfect for migrating from another CRM or adding a large list.",
                                )
                            }}
                        </p>

                        <h4 class="font-medium text-slate-900 dark:text-white">
                            {{
                                $t(
                                    "crm.guide.sections.import.steps_title",
                                    "Import Steps:",
                                )
                            }}
                        </h4>
                        <ol class="list-inside list-decimal space-y-2 text-sm">
                            <li>
                                {{
                                    $t(
                                        "crm.guide.sections.import.step1",
                                        "Prepare your CSV file with headers (email is required)",
                                    )
                                }}
                            </li>
                            <li>
                                {{
                                    $t(
                                        "crm.guide.sections.import.step2",
                                        "Upload the file - we'll auto-detect columns",
                                    )
                                }}
                            </li>
                            <li>
                                {{
                                    $t(
                                        "crm.guide.sections.import.step3",
                                        "Map your CSV columns to CRM fields",
                                    )
                                }}
                            </li>
                            <li>
                                {{
                                    $t(
                                        "crm.guide.sections.import.step4",
                                        "Choose how to handle duplicates (skip or update)",
                                    )
                                }}
                            </li>
                            <li>
                                {{
                                    $t(
                                        "crm.guide.sections.import.step5",
                                        "Start import and review results",
                                    )
                                }}
                            </li>
                        </ol>

                        <div
                            class="rounded-xl bg-slate-50 p-4 dark:bg-slate-900/50"
                        >
                            <h4
                                class="mb-2 font-medium text-slate-900 dark:text-white"
                            >
                                {{
                                    $t(
                                        "crm.guide.sections.import.csv_title",
                                        "CSV Format Example:",
                                    )
                                }}
                            </h4>
                            <code
                                class="block overflow-x-auto rounded bg-slate-200 p-2 text-xs dark:bg-slate-800"
                            >
                                email,first_name,last_name,phone,company<br />
                                john@example.com,John,Doe,+1234567890,Acme
                                Inc<br />
                                jane@company.com,Jane,Smith,+0987654321,Tech
                                Corp
                            </code>
                        </div>
                    </div>
                </section>

                <!-- Tips Section -->
                <section
                    id="tips"
                    class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800"
                >
                    <button
                        @click="toggleSection('tips')"
                        class="flex w-full items-center justify-between text-left"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-yellow-100 dark:bg-yellow-900/30"
                            >
                                <svg
                                    class="h-5 w-5 text-yellow-600 dark:text-yellow-400"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"
                                    />
                                </svg>
                            </div>
                            <h2
                                class="text-xl font-semibold text-slate-900 dark:text-white"
                            >
                                {{
                                    $t(
                                        "crm.guide.sections.tips.title",
                                        "Best Practices",
                                    )
                                }}
                            </h2>
                        </div>
                        <svg
                            :class="[
                                'h-5 w-5 text-slate-400 transition',
                                isExpanded('tips') ? 'rotate-180' : '',
                            ]"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 9l-7 7-7-7"
                            />
                        </svg>
                    </button>
                    <div
                        v-show="isExpanded('tips')"
                        class="mt-4 space-y-4 text-slate-600 dark:text-slate-300"
                    >
                        <p>
                            {{
                                $t(
                                    "crm.guide.sections.tips.p1",
                                    "Follow these practices to maximize your sales effectiveness with the CRM.",
                                )
                            }}
                        </p>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div
                                class="rounded-xl border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20"
                            >
                                <h4
                                    class="mb-2 font-medium text-green-800 dark:text-green-200"
                                >
                                    ✅
                                    {{
                                        $t(
                                            "crm.guide.sections.tips.do_title",
                                            "Do",
                                        )
                                    }}
                                </h4>
                                <ul
                                    class="space-y-1 text-sm text-green-700 dark:text-green-300"
                                >
                                    <li>
                                        •
                                        {{
                                            $t(
                                                "crm.guide.sections.tips.do1",
                                                "Log activities after every interaction",
                                            )
                                        }}
                                    </li>
                                    <li>
                                        •
                                        {{
                                            $t(
                                                "crm.guide.sections.tips.do2",
                                                "Set follow-up tasks immediately",
                                            )
                                        }}
                                    </li>
                                    <li>
                                        •
                                        {{
                                            $t(
                                                "crm.guide.sections.tips.do3",
                                                "Update deal stages regularly",
                                            )
                                        }}
                                    </li>
                                    <li>
                                        •
                                        {{
                                            $t(
                                                "crm.guide.sections.tips.do4",
                                                "Use sequences for consistent follow-up",
                                            )
                                        }}
                                    </li>
                                    <li>
                                        •
                                        {{
                                            $t(
                                                "crm.guide.sections.tips.do5",
                                                "Review dashboard daily",
                                            )
                                        }}
                                    </li>
                                </ul>
                            </div>
                            <div
                                class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20"
                            >
                                <h4
                                    class="mb-2 font-medium text-red-800 dark:text-red-200"
                                >
                                    ❌
                                    {{
                                        $t(
                                            "crm.guide.sections.tips.dont_title",
                                            "Don't",
                                        )
                                    }}
                                </h4>
                                <ul
                                    class="space-y-1 text-sm text-red-700 dark:text-red-300"
                                >
                                    <li>
                                        •
                                        {{
                                            $t(
                                                "crm.guide.sections.tips.dont1",
                                                "Let tasks go overdue",
                                            )
                                        }}
                                    </li>
                                    <li>
                                        •
                                        {{
                                            $t(
                                                "crm.guide.sections.tips.dont2",
                                                "Forget to update contact status",
                                            )
                                        }}
                                    </li>
                                    <li>
                                        •
                                        {{
                                            $t(
                                                "crm.guide.sections.tips.dont3",
                                                "Leave deals in wrong stages",
                                            )
                                        }}
                                    </li>
                                    <li>
                                        •
                                        {{
                                            $t(
                                                "crm.guide.sections.tips.dont4",
                                                "Skip logging important notes",
                                            )
                                        }}
                                    </li>
                                    <li>
                                        •
                                        {{
                                            $t(
                                                "crm.guide.sections.tips.dont5",
                                                "Ignore hot leads",
                                            )
                                        }}
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div
                            class="rounded-xl bg-gradient-to-r from-indigo-500 to-purple-600 p-4 text-white"
                        >
                            <h4 class="mb-2 font-medium">
                                🚀
                                {{
                                    $t(
                                        "crm.guide.sections.tips.workflow_title",
                                        "Recommended Daily Workflow",
                                    )
                                }}
                            </h4>
                            <ol
                                class="list-inside list-decimal space-y-1 text-sm text-indigo-100"
                            >
                                <li>
                                    {{
                                        $t(
                                            "crm.guide.sections.tips.workflow1",
                                            "Start with Dashboard - check overdue tasks and hot leads",
                                        )
                                    }}
                                </li>
                                <li>
                                    {{
                                        $t(
                                            "crm.guide.sections.tips.workflow2",
                                            "Complete your tasks for today",
                                        )
                                    }}
                                </li>
                                <li>
                                    {{
                                        $t(
                                            "crm.guide.sections.tips.workflow3",
                                            "Update deal stages based on progress",
                                        )
                                    }}
                                </li>
                                <li>
                                    {{
                                        $t(
                                            "crm.guide.sections.tips.workflow4",
                                            "Review upcoming tasks and prepare for tomorrow",
                                        )
                                    }}
                                </li>
                                <li>
                                    {{
                                        $t(
                                            "crm.guide.sections.tips.workflow5",
                                            "End the day with 0 overdue tasks!",
                                        )
                                    }}
                                </li>
                            </ol>
                        </div>
                    </div>
                </section>

                <!-- Footer -->
                <div
                    class="rounded-2xl bg-slate-100 p-6 text-center dark:bg-slate-900"
                >
                    <p class="text-sm text-slate-600 dark:text-slate-400">
                        {{
                            $t(
                                "crm.guide.footer",
                                "Need more help? Contact support or check the documentation.",
                            )
                        }}
                    </p>
                </div>
            </main>
        </div>
    </AuthenticatedLayout>
</template>
