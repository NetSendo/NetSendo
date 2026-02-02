<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const props = defineProps({
    domains: { type: Array, default: () => [] },
    recentSimulations: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    isGold: { type: Boolean, default: false },
    licenseRoute: { type: String, default: "" },
});

// Status colors
const statusColors = {
    excellent: {
        bg: "bg-emerald-500/20",
        text: "text-emerald-500",
        dot: "bg-emerald-500",
    },
    good: { bg: "bg-blue-500/20", text: "text-blue-400", dot: "bg-blue-500" },
    warning: {
        bg: "bg-amber-500/20",
        text: "text-amber-500",
        dot: "bg-amber-500",
    },
    critical: {
        bg: "bg-rose-500/20",
        text: "text-rose-500",
        dot: "bg-rose-500",
    },
    pending: {
        bg: "bg-slate-500/20",
        text: "text-slate-400",
        dot: "bg-slate-400",
    },
};

const getStatusColor = (status) => statusColors[status] || statusColors.pending;

// Format score
const formatScore = (score) => Math.round(score || 0);
</script>

<template>
    <Head :title="$t('deliverability.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2
                        class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
                    >
                        {{ $t("deliverability.title") }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $t("deliverability.subtitle") }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('deliverability.simulator')"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700"
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
                                d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"
                            />
                        </svg>
                        InboxPassport AI
                    </Link>
                    <Link
                        :href="route('deliverability.domains.create')"
                        class="inline-flex items-center gap-2 rounded-lg border border-indigo-600 px-4 py-2 text-sm font-medium text-indigo-600 transition-colors hover:bg-indigo-50 dark:border-indigo-400 dark:text-indigo-400 dark:hover:bg-indigo-900/20"
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
                                d="M12 4v16m8-8H4"
                            />
                        </svg>
                        {{ $t("deliverability.add_domain") }}
                    </Link>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Upsell Banner for non-GOLD users -->
            <div
                v-if="!isGold"
                class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-amber-500 via-yellow-500 to-orange-500 p-8 text-white shadow-xl"
            >
                <!-- Background decorations -->
                <div
                    class="absolute -right-10 -top-10 h-40 w-40 rounded-full bg-white/10"
                ></div>
                <div
                    class="absolute -bottom-8 -left-8 h-32 w-32 rounded-full bg-white/10"
                ></div>
                <div
                    class="absolute right-1/4 top-1/2 h-20 w-20 rounded-full bg-white/5"
                ></div>

                <div class="relative z-10">
                    <div class="flex items-start justify-between gap-6">
                        <div class="flex-1">
                            <!-- Badge -->
                            <span
                                class="inline-flex items-center gap-1 rounded-full bg-white/20 px-3 py-1 text-xs font-bold tracking-wide backdrop-blur-sm"
                            >
                                ✨ GOLD EXCLUSIVE
                            </span>

                            <h2 class="mt-4 text-3xl font-bold">
                                {{ $t("deliverability.upsell.title") }}
                            </h2>
                            <p class="mt-2 max-w-xl text-lg text-white/90">
                                {{ $t("deliverability.upsell.description") }}
                            </p>

                            <!-- Features list -->
                            <div class="mt-6 grid gap-3 sm:grid-cols-2">
                                <div class="flex items-center gap-2">
                                    <svg
                                        class="h-5 w-5 text-white"
                                        fill="currentColor"
                                        viewBox="0 0 20 20"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                    <span class="text-sm font-medium">{{
                                        $t("deliverability.upsell.feature1")
                                    }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <svg
                                        class="h-5 w-5 text-white"
                                        fill="currentColor"
                                        viewBox="0 0 20 20"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                    <span class="text-sm font-medium">{{
                                        $t("deliverability.upsell.feature2")
                                    }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <svg
                                        class="h-5 w-5 text-white"
                                        fill="currentColor"
                                        viewBox="0 0 20 20"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                    <span class="text-sm font-medium">{{
                                        $t("deliverability.upsell.feature3")
                                    }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <svg
                                        class="h-5 w-5 text-white"
                                        fill="currentColor"
                                        viewBox="0 0 20 20"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                    <span class="text-sm font-medium">{{
                                        $t("deliverability.upsell.feature4")
                                    }}</span>
                                </div>
                            </div>

                            <!-- CTA Button -->
                            <a
                                :href="licenseRoute"
                                class="mt-8 inline-flex items-center gap-2 rounded-xl bg-white px-6 py-3 text-base font-bold text-amber-600 shadow-lg transition-all hover:scale-105 hover:shadow-xl"
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
                                        d="M13 10V3L4 14h7v7l9-11h-7z"
                                    />
                                </svg>
                                {{ $t("deliverability.upsell.cta") }}
                            </a>
                        </div>

                        <!-- Shield Icon -->
                        <div
                            class="hidden lg:flex h-32 w-32 flex-shrink-0 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-sm"
                        >
                            <svg
                                class="h-20 w-20 text-white"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.5"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                                />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards (only for GOLD users) -->
            <div v-if="isGold" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Total Domains -->
                <div
                    class="rounded-xl border bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800"
                >
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-100 dark:bg-indigo-900/30"
                        >
                            <svg
                                class="h-6 w-6 text-indigo-600 dark:text-indigo-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"
                                />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $t("deliverability.stats.domains") }}
                            </p>
                            <p
                                class="text-2xl font-bold text-gray-900 dark:text-white"
                            >
                                {{ stats.total_domains || 0 }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Verified -->
                <div
                    class="rounded-xl border bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800"
                >
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-900/30"
                        >
                            <svg
                                class="h-6 w-6 text-emerald-600 dark:text-emerald-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                                />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $t("deliverability.stats.verified") }}
                            </p>
                            <p
                                class="text-2xl font-bold text-emerald-600 dark:text-emerald-400"
                            >
                                {{ stats.verified_domains || 0 }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Critical -->
                <div
                    class="rounded-xl border bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800"
                >
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-xl bg-rose-100 dark:bg-rose-900/30"
                        >
                            <svg
                                class="h-6 w-6 text-rose-600 dark:text-rose-400"
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
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $t("deliverability.stats.critical") }}
                            </p>
                            <p
                                class="text-2xl font-bold text-rose-600 dark:text-rose-400"
                            >
                                {{ stats.critical_domains || 0 }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Avg Score -->
                <div
                    class="rounded-xl border bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800"
                >
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-900/30"
                        >
                            <svg
                                class="h-6 w-6 text-amber-600 dark:text-amber-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"
                                />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $t("deliverability.stats.avg_score") }}
                            </p>
                            <p
                                class="text-2xl font-bold text-gray-900 dark:text-white"
                            >
                                {{ formatScore(stats.avg_inbox_score) }}%
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Domains Section -->
            <div
                class="rounded-xl border bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800"
            >
                <div
                    class="border-b border-gray-200 px-6 py-4 dark:border-slate-700"
                >
                    <h3 class="font-semibold text-gray-900 dark:text-white">
                        {{ $t("deliverability.domains.title") }}
                    </h3>
                </div>

                <div v-if="domains.length === 0" class="p-12 text-center">
                    <svg
                        class="mx-auto h-12 w-12 text-gray-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"
                        />
                    </svg>
                    <h3 class="mt-4 font-medium text-gray-900 dark:text-white">
                        {{ $t("deliverability.domains.empty.title") }}
                    </h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        {{ $t("deliverability.domains.empty.description") }}
                    </p>
                    <Link
                        :href="route('deliverability.domains.create')"
                        class="mt-6 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700"
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
                                d="M12 4v16m8-8H4"
                            />
                        </svg>
                        {{ $t("deliverability.add_domain") }}
                    </Link>
                </div>

                <div
                    v-else
                    class="divide-y divide-gray-200 dark:divide-slate-700"
                >
                    <Link
                        v-for="domain in domains"
                        :key="domain.id"
                        :href="route('deliverability.domains.show', domain.id)"
                        class="flex items-center gap-4 px-6 py-4 transition-colors hover:bg-gray-50 dark:hover:bg-slate-700/50"
                    >
                        <!-- Status indicator -->
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-full"
                            :class="getStatusColor(domain.overall_status).bg"
                        >
                            <span
                                class="h-3 w-3 rounded-full"
                                :class="
                                    getStatusColor(domain.overall_status).dot
                                "
                            ></span>
                        </div>

                        <!-- Domain info -->
                        <div class="flex-1 min-w-0">
                            <p
                                class="font-medium text-gray-900 dark:text-white truncate"
                            >
                                {{ domain.domain }}
                            </p>
                            <div class="flex items-center gap-2 mt-1">
                                <span
                                    v-if="domain.cname_verified"
                                    class="inline-flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-400"
                                >
                                    <svg
                                        class="h-3 w-3"
                                        fill="currentColor"
                                        viewBox="0 0 20 20"
                                    >
                                        <path
                                            fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                            clip-rule="evenodd"
                                        />
                                    </svg>
                                    {{ $t("deliverability.verified") }}
                                </span>
                                <span v-else class="text-xs text-amber-500">{{
                                    $t("deliverability.pending_verification")
                                }}</span>
                                <span class="text-xs text-gray-400">•</span>
                                <span
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                    >{{
                                        domain.last_check_at ||
                                        $t("deliverability.never_checked")
                                    }}</span
                                >
                            </div>
                        </div>

                        <!-- Records status -->
                        <div class="hidden sm:flex items-center gap-2">
                            <span
                                class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium"
                                :class="
                                    domain.spf_status === 'pass'
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                        : 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400'
                                "
                            >
                                SPF
                            </span>
                            <span
                                class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium"
                                :class="
                                    domain.dkim_status === 'pass'
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                        : 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400'
                                "
                            >
                                DKIM
                            </span>
                            <span
                                class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium"
                                :class="
                                    domain.dmarc_status === 'pass'
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                        : 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400'
                                "
                            >
                                DMARC
                            </span>
                        </div>

                        <!-- Arrow -->
                        <svg
                            class="h-5 w-5 text-gray-400"
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

            <!-- Recent Simulations -->
            <div
                class="rounded-xl border bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800"
            >
                <div
                    class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-slate-700"
                >
                    <h3 class="font-semibold text-gray-900 dark:text-white">
                        {{ $t("deliverability.simulations.recent") }}
                    </h3>
                    <Link
                        :href="route('deliverability.simulations.index')"
                        class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400"
                    >
                        {{ $t("common.view_all") }}
                    </Link>
                </div>

                <div
                    v-if="recentSimulations.length === 0"
                    class="p-8 text-center"
                >
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $t("deliverability.simulations.empty") }}
                    </p>
                </div>

                <div
                    v-else
                    class="divide-y divide-gray-200 dark:divide-slate-700"
                >
                    <Link
                        v-for="sim in recentSimulations"
                        :key="sim.id"
                        :href="route('deliverability.simulations.show', sim.id)"
                        class="flex items-center gap-4 px-6 py-4 transition-colors hover:bg-gray-50 dark:hover:bg-slate-700/50"
                    >
                        <!-- Score circle -->
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-full text-sm font-bold"
                            :class="
                                getStatusColor(
                                    sim.score_info?.category || 'pending',
                                ).bg +
                                ' ' +
                                getStatusColor(
                                    sim.score_info?.category || 'pending',
                                ).text
                            "
                        >
                            {{ sim.inbox_score }}%
                        </div>

                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <p
                                class="font-medium text-gray-900 dark:text-white truncate"
                            >
                                {{ sim.subject }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ sim.domain }} • {{ sim.created_at }}
                            </p>
                        </div>

                        <!-- Predicted folder -->
                        <span
                            class="hidden sm:inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium"
                            :class="{
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400':
                                    sim.predicted_folder === 'inbox',
                                'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400':
                                    sim.predicted_folder === 'promotions',
                                'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400':
                                    sim.predicted_folder === 'spam',
                            }"
                        >
                            {{ sim.folder_info?.label || sim.predicted_folder }}
                        </span>

                        <svg
                            class="h-5 w-5 text-gray-400"
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
        </div>
    </AuthenticatedLayout>
</template>
