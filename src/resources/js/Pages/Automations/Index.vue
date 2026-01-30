<script setup>
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { ref, computed, watch } from "vue";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const props = defineProps({
    rules: Object,
    filters: Object,
    triggerEvents: Object,
});

const filters = ref({
    status: props.filters?.status || "all",
    trigger: props.filters?.trigger || "",
    search: props.filters?.search || "",
});

// Modal states
const showDuplicateModal = ref(false);
const showDeleteModal = ref(false);
const showRestoreModal = ref(false);
const selectedRule = ref(null);
const isRestoring = ref(false);

let searchTimeout = null;

watch(
    filters,
    () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            router.get(
                route("automations.index"),
                {
                    status:
                        filters.value.status !== "all"
                            ? filters.value.status
                            : null,
                    trigger: filters.value.trigger || null,
                    search: filters.value.search || null,
                },
                {
                    preserveState: true,
                    replace: true,
                },
            );
        }, 300);
    },
    { deep: true },
);

const toggleStatus = (rule) => {
    router.post(
        route("automations.toggle-status", rule.id),
        {},
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const openDuplicateModal = (rule) => {
    selectedRule.value = rule;
    showDuplicateModal.value = true;
};

const confirmDuplicate = () => {
    if (selectedRule.value) {
        router.post(route("automations.duplicate", selectedRule.value.id));
        showDuplicateModal.value = false;
        selectedRule.value = null;
    }
};

const openDeleteModal = (rule) => {
    selectedRule.value = rule;
    showDeleteModal.value = true;
};

const confirmDelete = () => {
    if (selectedRule.value) {
        router.delete(route("automations.destroy", selectedRule.value.id));
        showDeleteModal.value = false;
        selectedRule.value = null;
    }
};

const closeModal = () => {
    showDuplicateModal.value = false;
    showDeleteModal.value = false;
    showRestoreModal.value = false;
    selectedRule.value = null;
};

const openRestoreModal = () => {
    showRestoreModal.value = true;
};

const confirmRestore = () => {
    isRestoring.value = true;
    router.post(
        route("automations.restore-defaults"),
        {},
        {
            onFinish: () => {
                isRestoring.value = false;
                showRestoreModal.value = false;
            },
        },
    );
};

const getTriggerIcon = (trigger) => {
    const icons = {
        subscriber_signup: "📝",
        subscriber_activated: "✅",
        email_opened: "👁️",
        email_clicked: "🖱️",
        subscriber_unsubscribed: "🚪",
        email_bounced: "📨",
        form_submitted: "📋",
        tag_added: "🏷️",
        tag_removed: "🗑️",
        field_updated: "✏️",
    };
    return icons[trigger] || "⚡";
};
</script>

<template>
    <Head :title="$t('automations.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2
                    class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
                >
                    ⚡ {{ $t("automations.title") }}
                </h2>
                <div class="flex items-center gap-3">
                    <button
                        @click="openRestoreModal"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
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
                        {{ $t("automations.restore_defaults") }}
                    </button>
                    <Link
                        :href="route('automations.create')"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
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
                        {{ $t("automations.create") }}
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Filters -->
                <div
                    class="mb-6 flex flex-wrap items-center gap-4 rounded-lg bg-white p-4 shadow dark:bg-gray-800"
                >
                    <!-- Status Filter -->
                    <div class="flex items-center gap-2">
                        <label
                            class="text-sm font-medium text-gray-700 dark:text-gray-300"
                            >{{ $t("common.status") }}:</label
                        >
                        <select
                            v-model="filters.status"
                            class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        >
                            <option value="all">{{ $t("common.all") }}</option>
                            <option value="active">
                                {{ $t("common.active") }}
                            </option>
                            <option value="inactive">
                                {{ $t("common.inactive") }}
                            </option>
                        </select>
                    </div>

                    <!-- Trigger Filter -->
                    <div class="flex items-center gap-2">
                        <label
                            class="text-sm font-medium text-gray-700 dark:text-gray-300"
                            >{{ $t("automations.trigger") }}:</label
                        >
                        <select
                            v-model="filters.trigger"
                            class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        >
                            <option value="">{{ $t("common.all") }}</option>
                            <option
                                v-for="(label, key) in triggerEvents"
                                :key="key"
                                :value="key"
                            >
                                {{ getTriggerIcon(key) }} {{ label }}
                            </option>
                        </select>
                    </div>

                    <!-- Search -->
                    <div class="flex flex-1 items-center gap-2">
                        <input
                            v-model="filters.search"
                            type="text"
                            :placeholder="$t('common.search') + '...'"
                            class="w-full max-w-xs rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                        />
                    </div>
                </div>

                <!-- Rules Table -->
                <div
                    class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800"
                >
                    <table
                        class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"
                    >
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                >
                                    {{ $t("common.name") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                >
                                    {{ $t("automations.trigger") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                >
                                    {{ $t("automations.actions_count") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                >
                                    {{ $t("automations.executions") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                >
                                    {{ $t("common.status") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                >
                                    {{ $t("common.actions") }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800"
                        >
                            <tr
                                v-for="rule in rules.data"
                                :key="rule.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                                <td class="px-6 py-4">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <Link
                                                :href="
                                                    route(
                                                        'automations.edit',
                                                        rule.id,
                                                    )
                                                "
                                                class="font-medium text-gray-900 hover:text-indigo-600 dark:text-gray-100 dark:hover:text-indigo-400"
                                            >
                                                {{ rule.name }}
                                            </Link>
                                            <span
                                                v-if="rule.is_system"
                                                class="inline-flex items-center rounded-full bg-gradient-to-r from-emerald-500 to-teal-500 px-2 py-0.5 text-[10px] font-bold text-white"
                                            >
                                                {{ $t("automations.default") }}
                                            </span>
                                        </div>
                                        <p
                                            v-if="rule.description"
                                            class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            {{ rule.description }}
                                        </p>
                                    </div>
                                </td>
                                <td
                                    class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300"
                                >
                                    <span
                                        class="inline-flex items-center gap-1"
                                    >
                                        {{ getTriggerIcon(rule.trigger_event) }}
                                        {{ rule.trigger_event_label }}
                                    </span>
                                </td>
                                <td
                                    class="px-6 py-4 text-center text-sm text-gray-600 dark:text-gray-300"
                                >
                                    {{ rule.actions_count }}
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <Link
                                        :href="
                                            route('automations.logs', rule.id)
                                        "
                                        class="text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
                                    >
                                        {{ rule.execution_count }}
                                    </Link>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <button
                                        @click="toggleStatus(rule)"
                                        :class="[
                                            'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
                                            rule.is_active
                                                ? 'bg-indigo-600'
                                                : 'bg-gray-200 dark:bg-gray-600',
                                        ]"
                                    >
                                        <span
                                            :class="[
                                                'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                                rule.is_active
                                                    ? 'translate-x-5'
                                                    : 'translate-x-0',
                                            ]"
                                        />
                                    </button>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div
                                        class="flex items-center justify-end gap-2"
                                    >
                                        <Link
                                            :href="
                                                route(
                                                    'automations.edit',
                                                    rule.id,
                                                )
                                            "
                                            class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                            :title="$t('common.edit')"
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
                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                                />
                                            </svg>
                                        </Link>
                                        <button
                                            @click="openDuplicateModal(rule)"
                                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                            :title="$t('common.duplicate')"
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
                                                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                                                />
                                            </svg>
                                        </button>
                                        <Link
                                            :href="
                                                route(
                                                    'automations.logs',
                                                    rule.id,
                                                )
                                            "
                                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                            :title="$t('automations.logs')"
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
                                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"
                                                />
                                            </svg>
                                        </Link>
                                        <button
                                            @click="openDeleteModal(rule)"
                                            class="text-red-400 hover:text-red-600 dark:hover:text-red-300"
                                            :title="$t('common.delete')"
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
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                                                />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="rules.data.length === 0">
                                <td
                                    colspan="6"
                                    class="px-6 py-12 text-center text-gray-500 dark:text-gray-400"
                                >
                                    <div class="flex flex-col items-center">
                                        <svg
                                            class="mb-4 h-12 w-12 text-gray-400"
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
                                        <p class="text-lg font-medium">
                                            {{ $t("automations.no_rules") }}
                                        </p>
                                        <p class="mt-1 text-sm">
                                            {{
                                                $t("automations.no_rules_hint")
                                            }}
                                        </p>
                                        <Link
                                            :href="route('automations.create')"
                                            class="mt-4 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                                        >
                                            {{ $t("automations.create_first") }}
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div
                        v-if="rules.meta && rules.meta.last_page > 1"
                        class="border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900"
                    >
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                {{
                                    $t("pagination.showing", {
                                        from: rules.meta.from,
                                        to: rules.meta.to,
                                        total: rules.meta.total,
                                    })
                                }}
                            </p>
                            <div class="flex gap-2">
                                <Link
                                    v-for="link in rules.links"
                                    :key="link.label"
                                    :href="link.url"
                                    v-html="link.label"
                                    :class="[
                                        'rounded px-3 py-1 text-sm',
                                        link.active
                                            ? 'bg-indigo-600 text-white'
                                            : link.url
                                              ? 'bg-white text-gray-700 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                                              : 'cursor-not-allowed bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500',
                                    ]"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Duplicate Confirmation Modal -->
        <teleport to="body">
            <div
                v-if="showDuplicateModal"
                class="fixed inset-0 z-50 overflow-y-auto"
                aria-labelledby="modal-title"
                role="dialog"
                aria-modal="true"
            >
                <div
                    class="flex min-h-screen items-end justify-center px-4 pb-20 pt-4 text-center sm:block sm:p-0"
                >
                    <div
                        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                        aria-hidden="true"
                        @click="closeModal"
                    ></div>
                    <span
                        class="hidden sm:inline-block sm:h-screen sm:align-middle"
                        aria-hidden="true"
                        >&#8203;</span
                    >
                    <div
                        class="inline-block transform overflow-hidden rounded-lg bg-white text-left align-bottom shadow-xl transition-all dark:bg-gray-800 sm:my-8 sm:w-full sm:max-w-lg sm:align-middle"
                    >
                        <div
                            class="bg-white px-4 pb-4 pt-5 dark:bg-gray-800 sm:p-6 sm:pb-4"
                        >
                            <div class="sm:flex sm:items-start">
                                <div
                                    class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900 sm:mx-0 sm:h-10 sm:w-10"
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
                                            d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                                        />
                                    </svg>
                                </div>
                                <div
                                    class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left"
                                >
                                    <h3
                                        class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100"
                                        id="modal-title"
                                    >
                                        {{ $t("common.duplicate") }}
                                    </h3>
                                    <div class="mt-2">
                                        <p
                                            class="text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            {{
                                                $t(
                                                    "automations.confirm_duplicate",
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div
                            class="bg-gray-50 px-4 py-3 dark:bg-gray-900 sm:flex sm:flex-row-reverse sm:px-6"
                        >
                            <button
                                type="button"
                                @click="confirmDuplicate"
                                class="inline-flex w-full justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                {{ $t("common.duplicate") }}
                            </button>
                            <button
                                type="button"
                                @click="closeModal"
                                class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 sm:mt-0 sm:w-auto sm:text-sm"
                            >
                                {{ $t("common.cancel") }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </teleport>

        <!-- Delete Confirmation Modal -->
        <teleport to="body">
            <div
                v-if="showDeleteModal"
                class="fixed inset-0 z-50 overflow-y-auto"
                aria-labelledby="modal-title"
                role="dialog"
                aria-modal="true"
            >
                <div
                    class="flex min-h-screen items-end justify-center px-4 pb-20 pt-4 text-center sm:block sm:p-0"
                >
                    <div
                        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                        aria-hidden="true"
                        @click="closeModal"
                    ></div>
                    <span
                        class="hidden sm:inline-block sm:h-screen sm:align-middle"
                        aria-hidden="true"
                        >&#8203;</span
                    >
                    <div
                        class="inline-block transform overflow-hidden rounded-lg bg-white text-left align-bottom shadow-xl transition-all dark:bg-gray-800 sm:my-8 sm:w-full sm:max-w-lg sm:align-middle"
                    >
                        <div
                            class="bg-white px-4 pb-4 pt-5 dark:bg-gray-800 sm:p-6 sm:pb-4"
                        >
                            <div class="sm:flex sm:items-start">
                                <div
                                    class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900 sm:mx-0 sm:h-10 sm:w-10"
                                >
                                    <svg
                                        class="h-6 w-6 text-red-600 dark:text-red-400"
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
                                <div
                                    class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left"
                                >
                                    <h3
                                        class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100"
                                        id="modal-title"
                                    >
                                        {{ $t("common.delete") }}
                                    </h3>
                                    <div class="mt-2">
                                        <p
                                            class="text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            {{
                                                $t("automations.confirm_delete")
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div
                            class="bg-gray-50 px-4 py-3 dark:bg-gray-900 sm:flex sm:flex-row-reverse sm:px-6"
                        >
                            <button
                                type="button"
                                @click="confirmDelete"
                                class="inline-flex w-full justify-center rounded-md border border-transparent bg-red-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                {{ $t("common.delete") }}
                            </button>
                            <button
                                type="button"
                                @click="closeModal"
                                class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 sm:mt-0 sm:w-auto sm:text-sm"
                            >
                                {{ $t("common.cancel") }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </teleport>

        <!-- Restore Defaults Confirmation Modal -->
        <teleport to="body">
            <div
                v-if="showRestoreModal"
                class="fixed inset-0 z-50 overflow-y-auto"
                aria-labelledby="modal-title"
                role="dialog"
                aria-modal="true"
            >
                <div
                    class="flex min-h-screen items-end justify-center px-4 pb-20 pt-4 text-center sm:block sm:p-0"
                >
                    <div
                        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                        aria-hidden="true"
                        @click="closeModal"
                    ></div>
                    <span
                        class="hidden sm:inline-block sm:h-screen sm:align-middle"
                        aria-hidden="true"
                        >&#8203;</span
                    >
                    <div
                        class="inline-block transform overflow-hidden rounded-lg bg-white text-left align-bottom shadow-xl transition-all dark:bg-gray-800 sm:my-8 sm:w-full sm:max-w-lg sm:align-middle"
                    >
                        <div
                            class="bg-white px-4 pb-4 pt-5 dark:bg-gray-800 sm:p-6 sm:pb-4"
                        >
                            <div class="sm:flex sm:items-start">
                                <div
                                    class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900 sm:mx-0 sm:h-10 sm:w-10"
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
                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                        />
                                    </svg>
                                </div>
                                <div
                                    class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left"
                                >
                                    <h3
                                        class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100"
                                        id="modal-title"
                                    >
                                        {{ $t("automations.restore_defaults") }}
                                    </h3>
                                    <div class="mt-2">
                                        <p
                                            class="text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            {{
                                                $t(
                                                    "automations.confirm_restore",
                                                )
                                            }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div
                            class="bg-gray-50 px-4 py-3 dark:bg-gray-900 sm:flex sm:flex-row-reverse sm:px-6"
                        >
                            <button
                                type="button"
                                @click="confirmRestore"
                                :disabled="isRestoring"
                                class="inline-flex w-full justify-center rounded-md border border-transparent bg-emerald-600 px-4 py-2 text-base font-medium text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                <svg
                                    v-if="isRestoring"
                                    class="mr-2 h-4 w-4 animate-spin"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        class="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                    ></circle>
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                    ></path>
                                </svg>
                                {{
                                    isRestoring
                                        ? $t("common.processing")
                                        : $t("automations.restore")
                                }}
                            </button>
                            <button
                                type="button"
                                @click="closeModal"
                                :disabled="isRestoring"
                                class="mt-3 inline-flex w-full justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-base font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 sm:mt-0 sm:w-auto sm:text-sm"
                            >
                                {{ $t("common.cancel") }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </teleport>
    </AuthenticatedLayout>
</template>
