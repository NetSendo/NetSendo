<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const props = defineProps({
    simulations: { type: Object, required: true },
});

// Status colors
const getScoreColor = (score) => {
    if (score >= 80)
        return "text-emerald-500 bg-emerald-100 dark:bg-emerald-900/30";
    if (score >= 60) return "text-blue-500 bg-blue-100 dark:bg-blue-900/30";
    if (score >= 40) return "text-amber-500 bg-amber-100 dark:bg-amber-900/30";
    return "text-rose-500 bg-rose-100 dark:bg-rose-900/30";
};
</script>

<template>
    <Head :title="$t('deliverability.simulations.history')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link
                        :href="route('deliverability.index')"
                        class="rounded-lg p-2 text-gray-500 transition-colors hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-slate-700"
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
                    <h2
                        class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
                    >
                        {{ $t("deliverability.simulations.history") }}
                    </h2>
                </div>
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
                            d="M12 4v16m8-8H4"
                        />
                    </svg>
                    {{ $t("deliverability.new_simulation") }}
                </Link>
            </div>
        </template>

        <div
            class="rounded-xl border bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800"
        >
            <div v-if="simulations.data.length === 0" class="p-12 text-center">
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
                        d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"
                    />
                </svg>
                <h3 class="mt-4 font-medium text-gray-900 dark:text-white">
                    {{ $t("deliverability.simulations.no_history") }}
                </h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{ $t("deliverability.simulations.no_history_desc") }}
                </p>
            </div>

            <div v-else>
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-slate-900">
                        <tr>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                            >
                                {{ $t("deliverability.subject") }}
                            </th>
                            <th
                                class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                            >
                                {{ $t("deliverability.domain") }}
                            </th>
                            <th
                                class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                            >
                                {{ $t("deliverability.score") }}
                            </th>
                            <th
                                class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                            >
                                {{ $t("deliverability.folder") }}
                            </th>
                            <th
                                class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                            >
                                {{ $t("common.date") }}
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-200 dark:divide-slate-700"
                    >
                        <tr
                            v-for="sim in simulations.data"
                            :key="sim.id"
                            class="hover:bg-gray-50 dark:hover:bg-slate-700/50"
                        >
                            <td class="px-6 py-4">
                                <Link
                                    :href="
                                        route(
                                            'deliverability.simulations.show',
                                            sim.id,
                                        )
                                    "
                                    class="font-medium text-gray-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400"
                                >
                                    {{ sim.subject }}
                                </Link>
                            </td>
                            <td
                                class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400"
                            >
                                {{ sim.domain }}
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-full text-sm font-bold"
                                    :class="getScoreColor(sim.inbox_score)"
                                >
                                    {{ sim.inbox_score }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span
                                    class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium"
                                    :class="{
                                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400':
                                            sim.predicted_folder === 'inbox',
                                        'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400':
                                            sim.predicted_folder ===
                                            'promotions',
                                        'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400':
                                            sim.predicted_folder === 'spam',
                                    }"
                                >
                                    {{
                                        sim.folder_info?.label ||
                                        sim.predicted_folder
                                    }}
                                </span>
                            </td>
                            <td
                                class="px-6 py-4 text-right text-sm text-gray-500 dark:text-gray-400"
                            >
                                {{ sim.created_at }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div
                    v-if="simulations.links"
                    class="border-t px-6 py-4 dark:border-slate-700"
                >
                    <nav class="flex items-center justify-between">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $t("common.showing") }} {{ simulations.from }} -
                            {{ simulations.to }} {{ $t("common.of") }}
                            {{ simulations.total }}
                        </p>
                        <div class="flex gap-2">
                            <Link
                                v-for="link in simulations.links"
                                :key="link.label"
                                :href="link.url"
                                class="rounded px-3 py-1 text-sm"
                                :class="
                                    link.active
                                        ? 'bg-indigo-600 text-white'
                                        : 'text-gray-600 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-slate-700'
                                "
                                v-html="link.label"
                            />
                        </div>
                    </nav>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
