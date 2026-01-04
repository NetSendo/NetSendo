<script setup>
import { useI18n } from "vue-i18n";

const { t } = useI18n();

defineProps({
    selectedCount: {
        type: Number,
        required: true,
    },
    processing: {
        type: Boolean,
        default: false,
    },
    currentListId: {
        type: [String, Number],
        default: null,
    },
    isSelectingAllInList: {
        type: Boolean,
        default: false,
    },
});

defineEmits([
    "delete",
    "move",
    "copy",
    "change-status",
    "clear-selection",
    "delete-from-list",
    "select-all-in-list",
]);
</script>

<template>
    <div
        class="mb-4 flex flex-col justify-between gap-4 rounded-xl border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-800 dark:bg-indigo-900/30 md:flex-row md:items-center"
    >
        <div
            class="flex flex-wrap items-center justify-center gap-3 md:justify-start"
        >
            <span class="font-medium text-indigo-700 dark:text-indigo-300">
                {{ t("subscribers.bulk.selected", { count: selectedCount }) }}
            </span>
            <button
                @click="$emit('clear-selection')"
                class="text-xs text-indigo-600 hover:underline dark:text-indigo-400"
            >
                {{ t("common.clear_selection") }}
            </button>
            <button
                v-if="currentListId"
                @click="$emit('select-all-in-list')"
                :disabled="processing || isSelectingAllInList"
                class="text-xs text-indigo-600 disabled:opacity-50 hover:underline dark:text-indigo-400"
            >
                <span v-if="isSelectingAllInList"
                    >{{ t("common.loading") }}...</span
                >
                <span v-else>{{
                    t("subscribers.bulk.select_all_in_list_short")
                }}</span>
            </button>
        </div>
        <div
            class="flex flex-wrap items-center justify-center gap-2 md:justify-end"
        >
            <!-- Move to list button -->
            <button
                @click="$emit('move')"
                :disabled="processing"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm transition-colors disabled:opacity-50 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
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
                        d="M13 7l5 5m0 0l-5 5m5-5H6"
                    />
                </svg>
                {{ t("subscribers.bulk.move") }}
            </button>

            <!-- Copy to list button -->
            <button
                @click="$emit('copy')"
                :disabled="processing"
                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 shadow-sm transition-colors disabled:opacity-50 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
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
                {{ t("subscribers.bulk.copy") }}
            </button>

            <!-- Change status dropdown -->
            <select
                @change="
                    $emit('change-status', $event.target.value);
                    $event.target.value = '';
                "
                :disabled="processing"
                class="rounded-lg border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700 disabled:opacity-50 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
            >
                <option value="">
                    {{ t("subscribers.bulk.change_status") }}
                </option>
                <option value="active">
                    {{ t("subscribers.statuses.active") }}
                </option>
                <option value="inactive">
                    {{ t("subscribers.statuses.inactive") }}
                </option>
            </select>

            <!-- Delete from list button (only when filtering by specific list) -->
            <button
                v-if="currentListId"
                @click="$emit('delete-from-list')"
                :disabled="processing"
                class="inline-flex items-center gap-1.5 rounded-lg bg-amber-50 px-3 py-1.5 text-sm font-medium text-amber-700 transition-colors disabled:opacity-50 hover:bg-amber-100 dark:bg-amber-900/30 dark:text-amber-400 dark:hover:bg-amber-900/50"
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
                        d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
                {{ t("subscribers.bulk.delete_from_list") }}
            </button>

            <!-- Delete button -->
            <button
                @click="$emit('delete')"
                :disabled="processing"
                class="inline-flex items-center gap-1.5 rounded-lg bg-red-50 px-3 py-1.5 text-sm font-medium text-red-700 transition-colors disabled:opacity-50 hover:bg-red-100 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-900/50"
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
                {{ t("subscribers.bulk.delete") }}
            </button>
        </div>
    </div>
</template>
