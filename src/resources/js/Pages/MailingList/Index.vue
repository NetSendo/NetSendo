<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import DeleteListModal from "./Partials/DeleteListModal.vue";

import { ref, watch } from "vue";
import { debounce } from "lodash";

const props = defineProps({
    lists: Object,
    filters: Object,
    groups: Array,
    tags: Array,
    allLists: Array,
});

const form = ref({
    search: props.filters.search || "",
    group_id: props.filters.group_id || "",
    tag_id: props.filters.tag_id || "",
    visibility: props.filters.visibility || "",
    sort_col: props.filters.sort_col || "created_at",
    sort_dir: props.filters.sort_dir || "desc",
});

const reset = () => {
    form.value = {
        search: "",
        group_id: "",
        tag_id: "",
        sort_col: "created_at",
        sort_dir: "desc",
    };
};

watch(
    form,
    debounce(() => {
        router.get(route("mailing-lists.index"), form.value, {
            preserveState: true,
            replace: true,
        });
    }, 300),
    { deep: true }
);

// Delete Modal Logic
const confirmingListDeletion = ref(false);
const listToDelete = ref(null);

const confirmDeleteList = (list) => {
    listToDelete.value = list;
    confirmingListDeletion.value = true;
};

const closeDeleteModal = () => {
    confirmingListDeletion.value = false;
    setTimeout(() => {
        listToDelete.value = null;
    }, 300);
};

const availableListsHelper = (currentListId) => {
    if (!props.allLists) return [];
    return props.allLists.filter((l) => l.id !== currentListId);
};

// View Mode Logic
const viewMode = ref(localStorage.getItem("mailingList_viewMode") || "grid");

watch(viewMode, (newMode) => {
    localStorage.setItem("mailingList_viewMode", newMode);
});
</script>

<template>
    <Head :title="$t('mailing_lists.title')" />

    <DeleteListModal
        :show="confirmingListDeletion"
        :list="listToDelete"
        :available-lists="availableListsHelper(listToDelete?.id)"
        @close="closeDeleteModal"
    />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1
                        class="text-2xl font-bold text-slate-900 dark:text-white"
                    >
                        {{ $t("mailing_lists.title") }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $t("mailing_lists.subtitle") }}
                    </p>
                </div>
                <Link
                    :href="route('mailing-lists.create')"
                    class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-lg transition-all hover:bg-indigo-500 hover:shadow-indigo-500/25"
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
                    {{ $t("mailing_lists.new_list") }}
                </Link>
            </div>
        </template>

        <!-- Filters -->
        <div
            class="mb-6 flex flex-col gap-4 rounded-xl bg-white p-4 shadow-sm dark:bg-slate-900 md:flex-row md:items-center md:justify-between"
        >
            <div class="flex flex-1 flex-col gap-4 md:flex-row">
                <div class="relative w-full md:max-w-xs">
                    <div
                        class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"
                    >
                        <svg
                            class="h-5 w-5 text-slate-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                            />
                        </svg>
                    </div>
                    <input
                        v-model="form.search"
                        type="text"
                        :placeholder="$t('mailing_lists.search_placeholder')"
                        class="block w-full rounded-xl border-slate-200 bg-slate-50 pl-10 text-sm text-slate-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-400"
                    />
                </div>

                <select
                    v-model="form.group_id"
                    class="block w-full rounded-xl border-slate-200 bg-slate-50 text-sm text-slate-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white md:w-48"
                >
                    <option value="">
                        {{ $t("mailing_lists.all_groups") }}
                    </option>
                    <option
                        v-for="group in groups"
                        :key="group.id"
                        :value="group.id"
                    >
                        {{ "—".repeat(group.depth)
                        }}{{ group.depth > 0 ? " " : "" }}{{ group.name }}
                    </option>
                </select>

                <select
                    v-model="form.visibility"
                    class="block w-full rounded-xl border-slate-200 bg-slate-50 text-sm text-slate-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white md:w-40"
                >
                    <option value="">{{ $t("mailing_lists.status") }}</option>
                    <option value="public">
                        {{ $t("mailing_lists.public") }}
                    </option>
                    <option value="private">
                        {{ $t("mailing_lists.private") }}
                    </option>
                </select>

                <select
                    v-model="form.tag_id"
                    class="block w-full rounded-xl border-slate-200 bg-slate-50 text-sm text-slate-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white md:w-48"
                >
                    <option value="">{{ $t("mailing_lists.all_tags") }}</option>
                    <option v-for="tag in tags" :key="tag.id" :value="tag.id">
                        {{ tag.name }}
                    </option>
                </select>
            </div>

            <div class="flex items-center gap-2">
                <div
                    class="flex items-center rounded-lg border border-slate-200 bg-slate-50 p-1 dark:border-slate-700 dark:bg-slate-800"
                >
                    <button
                        @click="viewMode = 'grid'"
                        class="rounded-md p-1.5 transition-colors"
                        :class="
                            viewMode === 'grid'
                                ? 'bg-white text-indigo-600 shadow-sm dark:bg-slate-700 dark:text-indigo-400'
                                : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300'
                        "
                        :title="$t('mailing_lists.grid_view')"
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
                                d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"
                            />
                        </svg>
                    </button>
                    <button
                        @click="viewMode = 'list'"
                        class="rounded-md p-1.5 transition-colors"
                        :class="
                            viewMode === 'list'
                                ? 'bg-white text-indigo-600 shadow-sm dark:bg-slate-700 dark:text-indigo-400'
                                : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300'
                        "
                        :title="$t('mailing_lists.list_view')"
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
                                d="M4 6h16M4 12h16M4 18h16"
                            />
                        </svg>
                    </button>
                </div>

                <button
                    v-if="form.search || form.group_id || form.tag_id"
                    @click="reset"
                    class="ml-2 text-sm font-medium text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
                >
                    {{ $t("mailing_lists.clear_filters") }}
                </button>
            </div>
        </div>

        <!-- Empty State -->
        <div
            v-if="lists.data.length === 0"
            class="flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-300 bg-white py-16 text-center dark:border-slate-700 dark:bg-slate-900"
        >
            <div
                class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-indigo-50 dark:bg-indigo-900/20"
            >
                <svg
                    class="h-8 w-8 text-indigo-500 dark:text-indigo-400"
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
            <h3 class="text-lg font-medium text-slate-900 dark:text-white">
                {{ $t("mailing_lists.empty_title") }}
            </h3>
            <p class="mt-1 text-slate-500 dark:text-slate-400">
                {{ $t("mailing_lists.empty_description") }}
            </p>
            <Link
                :href="route('mailing-lists.create')"
                class="mt-6 inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-lg transition-all hover:bg-indigo-500 hover:shadow-indigo-500/25"
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
                {{ $t("mailing_lists.create_list") }}
            </Link>
        </div>

        <!-- Lists Grid -->
        <div
            v-else-if="viewMode === 'grid'"
            class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <div
                v-for="list in lists.data"
                :key="list.id"
                class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition-all hover:-translate-y-1 hover:shadow-md dark:border-slate-700 dark:bg-slate-900"
            >
                <div>
                    <div class="mb-4 flex items-start justify-between">
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-900/20 dark:text-indigo-400"
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
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"
                                />
                            </svg>
                        </div>
                        <div class="flex items-center gap-1">
                            <Link
                                :href="route('mailing-lists.edit', list.id)"
                                class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-indigo-600 dark:hover:bg-slate-800 dark:hover:text-indigo-400"
                                :title="$t('mailing_lists.edit')"
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
                                @click="confirmDeleteList(list)"
                                class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                                :title="$t('mailing_lists.delete')"
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
                    </div>

                    <h3
                        class="mb-1 text-lg font-bold text-slate-900 dark:text-white"
                    >
                        <Link
                            :href="route('mailing-lists.edit', list.id)"
                            class="hover:text-indigo-600 dark:hover:text-indigo-400 focus:outline-none"
                        >
                            <span
                                class="absolute inset-0"
                                aria-hidden="true"
                            ></span>
                            {{ list.name }}
                        </Link>
                    </h3>
                    <p
                        class="mb-4 line-clamp-2 text-sm text-slate-500 dark:text-slate-400"
                    >
                        {{
                            list.description ||
                            $t("mailing_lists.no_description")
                        }}
                    </p>

                    <div class="mb-4">
                        <div class="mb-2 flex flex-wrap gap-2">
                            <!-- Visibility Badge -->
                            <div
                                class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium"
                                :class="
                                    list.is_public
                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                        : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
                                "
                            >
                                <span
                                    class="h-1.5 w-1.5 rounded-full"
                                    :class="
                                        list.is_public
                                            ? 'bg-green-500'
                                            : 'bg-slate-400'
                                    "
                                ></span>
                                {{
                                    list.is_public
                                        ? $t("mailing_lists.public")
                                        : $t("mailing_lists.private")
                                }}
                            </div>

                            <!-- Group -->
                            <div
                                v-if="list.group"
                                class="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400"
                            >
                                <svg
                                    class="h-3 w-3"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"
                                    />
                                </svg>
                                {{ list.group.name }}
                            </div>
                        </div>

                        <!-- Tags -->
                        <div class="flex flex-wrap gap-1">
                            <span
                                v-for="tag in list.tags"
                                :key="tag.id"
                                class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold text-white shadow-sm"
                                :style="{ backgroundColor: tag.color }"
                            >
                                {{ tag.name }}
                            </span>
                        </div>
                    </div>
                </div>

                <div
                    class="flex items-center justify-between border-t border-slate-100 pt-4 dark:border-slate-800"
                >
                    <div
                        class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400"
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
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                            />
                        </svg>
                        <span
                            class="font-semibold text-slate-900 dark:text-white"
                            >{{ list.subscribers_count }}</span
                        >
                        {{ $t("mailing_lists.subscribers") }}
                    </div>
                    <span class="text-xs text-slate-400">
                        {{ list.created_at }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Lists Table -->
        <div
            v-else
            class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900"
        >
            <div class="overflow-x-auto">
                <table
                    class="w-full text-left text-sm text-slate-500 dark:text-slate-400"
                >
                    <thead
                        class="bg-slate-50 text-xs uppercase text-slate-700 dark:bg-slate-800 dark:text-slate-200"
                    >
                        <tr>
                            <th
                                scope="col"
                                class="px-6 py-3 cursor-pointer group"
                                @click="
                                    form.sort_col = 'name';
                                    form.sort_dir =
                                        form.sort_col === 'name' &&
                                        form.sort_dir === 'asc'
                                            ? 'desc'
                                            : 'asc';
                                "
                            >
                                <div class="flex items-center gap-1">
                                    {{ $t("mailing_lists.table.name") }}
                                    <span class="flex flex-col">
                                        <svg
                                            class="h-2 w-2"
                                            :class="
                                                form.sort_col === 'name' &&
                                                form.sort_dir === 'asc'
                                                    ? 'text-indigo-600 dark:text-indigo-400'
                                                    : 'text-slate-300 dark:text-slate-600'
                                            "
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="4"
                                                d="M5 15l7-7 7 7"
                                            />
                                        </svg>
                                        <svg
                                            class="h-2 w-2"
                                            :class="
                                                form.sort_col === 'name' &&
                                                form.sort_dir === 'desc'
                                                    ? 'text-indigo-600 dark:text-indigo-400'
                                                    : 'text-slate-300 dark:text-slate-600'
                                            "
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="4"
                                                d="M19 9l-7 7-7-7"
                                            />
                                        </svg>
                                    </span>
                                </div>
                            </th>
                            <th scope="col" class="px-6 py-3">
                                {{ $t("mailing_lists.table.status") }}
                            </th>
                            <th scope="col" class="px-6 py-3">
                                {{ $t("mailing_lists.table.groups_tags") }}
                            </th>
                            <th scope="col" class="px-6 py-3 text-center">
                                {{ $t("mailing_lists.table.subscribers") }}
                            </th>
                            <th
                                scope="col"
                                class="px-6 py-3 cursor-pointer group"
                                @click="
                                    form.sort_col = 'created_at';
                                    form.sort_dir =
                                        form.sort_col === 'created_at' &&
                                        form.sort_dir === 'asc'
                                            ? 'desc'
                                            : 'asc';
                                "
                            >
                                <div class="flex items-center gap-1">
                                    {{ $t("mailing_lists.table.created_at") }}
                                    <span class="flex flex-col">
                                        <svg
                                            class="h-2 w-2"
                                            :class="
                                                form.sort_col ===
                                                    'created_at' &&
                                                form.sort_dir === 'asc'
                                                    ? 'text-indigo-600 dark:text-indigo-400'
                                                    : 'text-slate-300 dark:text-slate-600'
                                            "
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="4"
                                                d="M5 15l7-7 7 7"
                                            />
                                        </svg>
                                        <svg
                                            class="h-2 w-2"
                                            :class="
                                                !form.sort_col ||
                                                (form.sort_col ===
                                                    'created_at' &&
                                                    form.sort_dir === 'desc')
                                                    ? 'text-indigo-600 dark:text-indigo-400'
                                                    : 'text-slate-300 dark:text-slate-600'
                                            "
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="4"
                                                d="M19 9l-7 7-7-7"
                                            />
                                        </svg>
                                    </span>
                                </div>
                            </th>
                            <th scope="col" class="px-6 py-3 text-right">
                                {{ $t("mailing_lists.table.actions") }}
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-200 dark:divide-slate-700"
                    >
                        <tr
                            v-for="list in lists.data"
                            :key="list.id"
                            class="hover:bg-slate-50 dark:hover:bg-slate-800/50"
                        >
                            <td
                                class="px-6 py-4 font-medium text-slate-900 dark:text-white"
                            >
                                <Link
                                    :href="route('mailing-lists.edit', list.id)"
                                    class="hover:text-indigo-600 dark:hover:text-indigo-400"
                                >
                                    {{ list.name }}
                                </Link>
                                <div
                                    class="text-xs text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        list.description
                                            ? list.description.substring(
                                                  0,
                                                  50
                                              ) +
                                              (list.description.length > 50
                                                  ? "..."
                                                  : "")
                                            : ""
                                    }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div
                                    class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium"
                                    :class="
                                        list.is_public
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                            : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
                                    "
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full"
                                        :class="
                                            list.is_public
                                                ? 'bg-green-500'
                                                : 'bg-slate-400'
                                        "
                                    ></span>
                                    {{
                                        list.is_public
                                            ? $t("mailing_lists.public")
                                            : $t("mailing_lists.private")
                                    }}
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-col gap-1">
                                    <div
                                        v-if="list.group"
                                        class="inline-flex w-fit items-center gap-1 rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400"
                                    >
                                        <svg
                                            class="h-3 w-3"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"
                                            />
                                        </svg>
                                        {{ list.group.name }}
                                    </div>
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            v-for="tag in list.tags"
                                            :key="tag.id"
                                            class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold text-white shadow-sm"
                                            :style="{
                                                backgroundColor: tag.color,
                                            }"
                                        >
                                            {{ tag.name }}
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span
                                    class="font-semibold text-slate-900 dark:text-white"
                                    >{{ list.subscribers_count }}</span
                                >
                            </td>
                            <td class="px-6 py-4 text-xs">
                                {{ list.created_at }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        :href="
                                            route('mailing-lists.edit', list.id)
                                        "
                                        class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-slate-100 hover:text-indigo-600 dark:hover:bg-slate-800 dark:hover:text-indigo-400"
                                        :title="$t('mailing_lists.edit')"
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
                                        @click="confirmDeleteList(list)"
                                        class="rounded-lg p-2 text-slate-400 transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400"
                                        :title="$t('mailing_lists.delete')"
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
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
