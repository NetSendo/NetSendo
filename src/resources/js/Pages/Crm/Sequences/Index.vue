<script setup>
import { ref } from "vue";
import { useI18n } from "vue-i18n";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { useDateTime } from "@/Composables/useDateTime";

const { t } = useI18n();
const { formatDate } = useDateTime();

const props = defineProps({
    sequences: Object,
    hasDefaults: Boolean,
    defaultsCount: Number,
});

// Restore defaults modal state
const showRestoreModal = ref(false);
const confirmRestore = ref(false);
const isRestoring = ref(false);

// Toggle active status
const toggleActive = async (sequence) => {
    await router.post(
        `/crm/sequences/${sequence.id}/toggle`,
        {},
        { preserveScroll: true },
    );
};

// Duplicate sequence
const duplicateSequence = async (sequence) => {
    await router.post(
        `/crm/sequences/${sequence.id}/duplicate`,
        {},
        { preserveScroll: true },
    );
};

// Delete sequence
const deleteSequence = async (sequence) => {
    if (
        confirm(
            t(
                "crm.sequences.actions.delete_confirm",
                "Czy na pewno chcesz usunąć tę sekwencję?",
            ),
        )
    ) {
        await router.delete(`/crm/sequences/${sequence.id}`, {
            preserveScroll: true,
        });
    }
};

// Restore defaults
const restoreDefaults = async () => {
    if (!confirmRestore.value) return;

    isRestoring.value = true;
    await router.post(
        `/crm/sequences/restore-defaults`,
        { confirm: true },
        {
            preserveScroll: true,
            onFinish: () => {
                isRestoring.value = false;
                showRestoreModal.value = false;
                confirmRestore.value = false;
            }
        },
    );
};

// Create defaults (for empty state)
const createDefaults = async () => {
    await router.post(
        `/crm/sequences/create-defaults`,
        {},
        { preserveScroll: true },
    );
};

// Trigger type labels
const getTriggerLabel = (type) => {
    return t(`crm.sequences.triggers.${type}`, type);
};

// Trigger type icons
const getTriggerIcon = (type) => {
    const icons = {
        manual: "M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z",
        on_contact_created:
            "M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z",
        on_deal_created:
            "M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z",
        on_task_completed: "M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z",
        on_deal_stage_changed:
            "M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01",
    };
    return icons[type] || icons.manual;
};
</script>

<template>
    <Head :title="$t('crm.sequences.title', 'Sekwencje Follow-up')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link
                        href="/crm/tasks"
                        class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700"
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
                    <h1
                        class="text-2xl font-bold text-slate-900 dark:text-white"
                    >
                        {{ $t("crm.sequences.title", "Sekwencje Follow-up") }}
                    </h1>
                </div>
                <div class="flex items-center gap-3">
                    <!-- Restore Defaults Button -->
                    <button
                        v-if="sequences?.data?.length"
                        @click="showRestoreModal = true"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                    >
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
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                            />
                        </svg>
                        {{ $t("crm.defaults.restore_button", "Przywróć domyślne") }}
                    </button>
                    <Link
                        href="/crm/sequences/create"
                        class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-indigo-700"
                    >
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
                                d="M12 4v16m8-8H4"
                            />
                        </svg>
                        {{ $t("crm.sequences.add", "Nowa sekwencja") }}
                    </Link>
                </div>
            </div>
        </template>

        <!-- Info Banner -->
        <div
            class="mb-6 rounded-2xl bg-gradient-to-r from-indigo-500 to-purple-600 p-6 text-white"
        >
            <div class="flex items-start gap-4">
                <div
                    class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-white/20"
                >
                    <svg
                        class="h-6 w-6"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                        />
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold">
                        {{
                            $t(
                                "crm.sequences.banner.title",
                                "Automatyczne sekwencje kontaktu",
                            )
                        }}
                    </h3>
                    <p class="mt-1 text-white/80">
                        {{
                            $t(
                                "crm.sequences.banner.description",
                                "Twórz sekwencje zadań...",
                            )
                        }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Sequences Grid -->
        <div
            v-if="sequences?.data?.length"
            class="grid gap-4 md:grid-cols-2 lg:grid-cols-3"
        >
            <div
                v-for="sequence in sequences.data"
                :key="sequence.id"
                class="group relative rounded-2xl bg-white p-6 shadow-sm transition hover:shadow-md dark:bg-slate-800"
            >
                <!-- Status Badge -->
                <div class="absolute right-4 top-4">
                    <button
                        @click="toggleActive(sequence)"
                        :class="[
                            'rounded-full px-3 py-1 text-xs font-medium transition',
                            sequence.is_active
                                ? 'bg-green-100 text-green-700 hover:bg-green-200 dark:bg-green-900/30 dark:text-green-300'
                                : 'bg-slate-100 text-slate-500 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-400',
                        ]"
                    >
                        {{
                            sequence.is_active
                                ? $t("crm.sequences.status.active", "Aktywna")
                                : $t(
                                      "crm.sequences.status.inactive",
                                      "Nieaktywna",
                                  )
                        }}
                    </button>
                </div>

                <!-- Trigger Icon -->
                <div
                    class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-100 dark:bg-indigo-900/30"
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
                            :d="getTriggerIcon(sequence.trigger_type)"
                        />
                    </svg>
                </div>

                <!-- Sequence Name -->
                <h3
                    class="text-lg font-semibold text-slate-900 dark:text-white"
                >
                    {{ sequence.name }}
                </h3>
                <p
                    v-if="sequence.description"
                    class="mt-1 text-sm text-slate-500 line-clamp-2 dark:text-slate-400"
                >
                    {{ sequence.description }}
                </p>

                <!-- Stats -->
                <div
                    class="mt-4 flex items-center gap-4 text-sm text-slate-500 dark:text-slate-400"
                >
                    <div class="flex items-center gap-1">
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
                                d="M4 6h16M4 10h16M4 14h16M4 18h16"
                            />
                        </svg>
                        {{
                            $t(
                                "crm.sequences.steps_count",
                                { count: sequence.steps_count },
                                sequence.steps_count + " kroków",
                            )
                        }}
                    </div>
                    <div class="flex items-center gap-1">
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
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"
                            />
                        </svg>
                        {{
                            $t(
                                "crm.sequences.active_enrollments",
                                {
                                    count:
                                        sequence.active_enrollments_count || 0,
                                },
                                (sequence.active_enrollments_count || 0) +
                                    " aktywnych",
                            )
                        }}
                    </div>
                </div>

                <!-- Trigger Type & Default Badge -->
                <div class="mt-3 flex flex-wrap gap-2">
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
                    >
                        {{ getTriggerLabel(sequence.trigger_type) }}
                    </span>
                    <span
                        v-if="sequence.is_default"
                        class="inline-flex items-center gap-1 rounded-full bg-indigo-100 px-2 py-1 text-xs font-medium text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-300"
                    >
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {{ $t("crm.defaults.badge", "Domyślna") }}
                    </span>
                    <span
                        v-else
                        class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-600 dark:bg-amber-900/30 dark:text-amber-300"
                    >
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        {{ $t("crm.defaults.badge_modified", "Własna") }}
                    </span>
                </div>

                <!-- Actions -->
                <div
                    class="mt-4 flex items-center gap-2 border-t border-slate-100 pt-4 dark:border-slate-700"
                >
                    <Link
                        :href="`/crm/sequences/${sequence.id}/edit`"
                        class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600"
                    >
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
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                            />
                        </svg>
                        {{ $t("crm.sequences.actions.edit", "Edytuj") }}
                    </Link>
                    <Link
                        :href="`/crm/sequences/${sequence.id}/report`"
                        class="rounded-xl p-2 text-slate-400 hover:bg-violet-50 hover:text-violet-600 dark:hover:bg-violet-900/20"
                        :title="$t('crm.sequences.actions.report', 'Raport')"
                    >
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
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                            />
                        </svg>
                    </Link>
                    <button
                        @click="duplicateSequence(sequence)"
                        class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700"
                        :title="
                            $t('crm.sequences.actions.duplicate', 'Duplikuj')
                        "
                    >
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
                                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                            />
                        </svg>
                    </button>
                    <button
                        @click="deleteSequence(sequence)"
                        class="rounded-xl p-2 text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20"
                        :title="$t('crm.sequences.actions.delete', 'Usuń')"
                    >
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
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                            />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div
            v-else
            class="rounded-2xl bg-white p-16 text-center shadow-sm dark:bg-slate-800"
        >
            <div
                class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-700"
            >
                <svg
                    class="h-8 w-8 text-slate-400"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                    />
                </svg>
            </div>
            <h3
                class="mt-4 text-lg font-semibold text-slate-900 dark:text-white"
            >
                {{ $t("crm.sequences.empty.title", "Brak sekwencji") }}
            </h3>
            <p class="mt-2 text-slate-500 dark:text-slate-400">
                {{
                    $t(
                        "crm.sequences.empty.description",
                        "Utwórz pierwszą sekwencję follow-up...",
                    )
                }}
            </p>
            <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                <button
                    @click="createDefaults"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-3 text-sm font-medium text-white transition hover:bg-indigo-700"
                >
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
                            d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"
                        />
                    </svg>
                    {{ $t("crm.defaults.restore_button", "Utwórz domyślne sekwencje") }}
                </button>
                <Link
                    href="/crm/sequences/create"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-6 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                >
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
                            d="M12 4v16m8-8H4"
                        />
                    </svg>
                    {{
                        $t(
                            "crm.sequences.empty.button",
                            "Utwórz własną sekwencję",
                        )
                    }}
                </Link>
            </div>
        </div>

        <!-- Restore Defaults Modal -->
        <Teleport to="body">
            <div
                v-if="showRestoreModal"
                class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto"
            >
                <!-- Backdrop -->
                <div
                    class="fixed inset-0 bg-black/50 transition-opacity"
                    @click="showRestoreModal = false"
                ></div>

                <!-- Modal -->
                <div
                    class="relative z-10 mx-4 w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800"
                >
                    <!-- Warning Icon -->
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30">
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
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                            />
                        </svg>
                    </div>

                    <!-- Title -->
                    <h3
                        class="mt-4 text-center text-lg font-semibold text-slate-900 dark:text-white"
                    >
                        {{ $t("crm.defaults.restore_modal.title", "Przywróć domyślne sekwencje") }}
                    </h3>

                    <!-- Warning Text -->
                    <p class="mt-2 text-center text-sm text-slate-500 dark:text-slate-400">
                        {{ $t("crm.defaults.restore_modal.warning", "Ta operacja usunie wszystkie obecne sekwencje i utworzy nowe domyślne sekwencje. Tej operacji nie można cofnąć.") }}
                    </p>

                    <!-- Confirmation Checkbox -->
                    <div class="mt-6">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input
                                v-model="confirmRestore"
                                type="checkbox"
                                class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700"
                            />
                            <span class="text-sm text-slate-600 dark:text-slate-300">
                                {{ $t("crm.defaults.restore_modal.confirm_checkbox", "Rozumiem, że wszystkie moje obecne sekwencje zostaną usunięte") }}
                            </span>
                        </label>
                    </div>

                    <!-- Actions -->
                    <div class="mt-6 flex gap-3">
                        <button
                            @click="showRestoreModal = false; confirmRestore = false"
                            class="flex-1 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600"
                        >
                            {{ $t("crm.defaults.restore_modal.cancel", "Anuluj") }}
                        </button>
                        <button
                            @click="restoreDefaults"
                            :disabled="!confirmRestore || isRestoring"
                            :class="[
                                'flex-1 rounded-xl px-4 py-2 text-sm font-medium transition',
                                confirmRestore && !isRestoring
                                    ? 'bg-red-600 text-white hover:bg-red-700'
                                    : 'cursor-not-allowed bg-slate-200 text-slate-400 dark:bg-slate-700 dark:text-slate-500',
                            ]"
                        >
                            <span v-if="isRestoring" class="flex items-center justify-center gap-2">
                                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                ...
                            </span>
                            <span v-else>
                                {{ $t("crm.defaults.restore_modal.confirm", "Przywróć domyślne") }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
