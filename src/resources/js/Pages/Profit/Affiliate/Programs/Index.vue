<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import { ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const props = defineProps({
    programs: Array,
});

// Delete confirmation modal
const showDeleteModal = ref(false);
const programToDelete = ref(null);

const confirmDelete = (program) => {
    programToDelete.value = program;
    showDeleteModal.value = true;
};

const cancelDelete = () => {
    showDeleteModal.value = false;
    programToDelete.value = null;
};

const deleteProgram = () => {
    if (programToDelete.value) {
        router.delete(
            route("affiliate.programs.destroy", programToDelete.value.id),
            {
                onSuccess: () => {
                    showDeleteModal.value = false;
                    programToDelete.value = null;
                },
            },
        );
    }
};

// Copy link
const copiedProgramId = ref(null);

const getFullLink = (program) => {
    return `${window.location.origin}/partners/${program.slug}/join`;
};

const copyLink = async (program) => {
    try {
        await navigator.clipboard.writeText(getFullLink(program));
        copiedProgramId.value = program.id;
        setTimeout(() => {
            copiedProgramId.value = null;
        }, 2000);
    } catch (err) {
        console.error("Failed to copy:", err);
    }
};

const openLink = (program) => {
    window.open(getFullLink(program), "_blank");
};
</script>

<template>
    <Head :title="$t('affiliate.programs')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link
                        :href="route('affiliate.index')"
                        class="inline-flex items-center text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors"
                    >
                        <svg
                            class="w-5 h-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18"
                            />
                        </svg>
                    </Link>
                    <h2
                        class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
                    >
                        {{ $t("affiliate.programs") }}
                    </h2>
                </div>
                <Link
                    :href="route('affiliate.programs.create')"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors"
                >
                    <svg
                        class="w-4 h-4 mr-2"
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
                    {{ $t("affiliate.create_program") }}
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div
                    v-if="programs.length > 0"
                    class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 overflow-hidden"
                >
                    <table
                        class="min-w-full divide-y divide-gray-200 dark:divide-slate-700"
                    >
                        <thead class="bg-gray-50 dark:bg-slate-700">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                                >
                                    {{ $t("affiliate.program_name") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                                >
                                    {{ $t("common.status") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                                >
                                    {{ $t("affiliate.affiliates") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                                >
                                    {{ $t("affiliate.offers") }}
                                </th>
                                <th
                                    class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider"
                                >
                                    {{ $t("common.actions") }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700"
                        >
                            <tr v-for="program in programs" :key="program.id">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div
                                            class="w-10 h-10 flex-shrink-0 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center"
                                        >
                                            <span
                                                class="text-white font-bold"
                                                >{{
                                                    program.name
                                                        .charAt(0)
                                                        .toUpperCase()
                                                }}</span
                                            >
                                        </div>
                                        <div class="ml-4">
                                            <div
                                                class="font-medium text-gray-900 dark:text-white"
                                            >
                                                {{ program.name }}
                                            </div>
                                            <div
                                                class="flex items-center gap-2 mt-1"
                                            >
                                                <a
                                                    :href="getFullLink(program)"
                                                    target="_blank"
                                                    class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline truncate max-w-xs"
                                                    :title="
                                                        getFullLink(program)
                                                    "
                                                >
                                                    {{ getFullLink(program) }}
                                                </a>
                                                <button
                                                    @click="copyLink(program)"
                                                    class="flex-shrink-0 p-1 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                                    :title="
                                                        $t('common.copy_link')
                                                    "
                                                >
                                                    <svg
                                                        v-if="
                                                            copiedProgramId !==
                                                            program.id
                                                        "
                                                        class="w-4 h-4"
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
                                                    <svg
                                                        v-else
                                                        class="w-4 h-4 text-green-500"
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
                                                </button>
                                                <button
                                                    @click="openLink(program)"
                                                    class="flex-shrink-0 p-1 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                                                    :title="
                                                        $t(
                                                            'common.open_in_new_tab',
                                                        )
                                                    "
                                                >
                                                    <svg
                                                        class="w-4 h-4"
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
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                        :class="{
                                            'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400':
                                                program.status === 'active',
                                            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400':
                                                program.status === 'paused',
                                            'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400':
                                                program.status === 'closed',
                                        }"
                                    >
                                        {{
                                            $t(
                                                `affiliate.status_${program.status}`,
                                            )
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white"
                                >
                                    {{ program.affiliates_count }}
                                </td>
                                <td
                                    class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white"
                                >
                                    {{ program.offers_count }}
                                </td>
                                <td
                                    class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium"
                                >
                                    <Link
                                        :href="
                                            route(
                                                'affiliate.programs.edit',
                                                program.id,
                                            )
                                        "
                                        class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 mr-3"
                                    >
                                        {{ $t("common.edit") }}
                                    </Link>
                                    <button
                                        @click="confirmDelete(program)"
                                        class="text-red-600 dark:text-red-400 hover:text-red-900"
                                    >
                                        {{ $t("common.delete") }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-else
                    class="bg-white dark:bg-slate-800 rounded-xl shadow-sm p-8 text-center"
                >
                    <div
                        class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 dark:bg-slate-700 flex items-center justify-center"
                    >
                        <svg
                            class="w-8 h-8 text-gray-400"
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
                    <h3
                        class="text-lg font-medium text-gray-900 dark:text-white mb-2"
                    >
                        {{ $t("affiliate.no_programs") }}
                    </h3>
                    <p class="text-gray-500 dark:text-gray-400 mb-4">
                        {{ $t("affiliate.no_programs_description") }}
                    </p>
                    <Link
                        :href="route('affiliate.programs.create')"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700"
                    >
                        {{ $t("affiliate.create_program") }}
                    </Link>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <Teleport to="body">
            <div
                v-if="showDeleteModal"
                class="fixed inset-0 z-50 overflow-y-auto"
            >
                <div
                    class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0"
                >
                    <!-- Backdrop -->
                    <div
                        class="fixed inset-0 bg-black/50 transition-opacity"
                        @click="cancelDelete"
                    ></div>

                    <!-- Modal -->
                    <div
                        class="relative inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white dark:bg-slate-800 shadow-xl rounded-2xl"
                    >
                        <div class="flex items-center gap-4 mb-4">
                            <div
                                class="flex-shrink-0 w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center"
                            >
                                <svg
                                    class="w-6 h-6 text-red-600 dark:text-red-400"
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
                                <h3
                                    class="text-lg font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ $t("affiliate.delete_program") }}
                                </h3>
                                <p
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{ $t("common.action_cannot_be_undone") }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="programToDelete"
                            class="mb-6 p-4 bg-gray-50 dark:bg-slate-700 rounded-lg"
                        >
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                {{
                                    $t("affiliate.confirm_delete_program", {
                                        name: programToDelete.name,
                                    })
                                }}
                            </p>
                            <div
                                class="mt-2 text-sm text-gray-500 dark:text-gray-400"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        >{{ $t("affiliate.affiliates") }}:</span
                                    >
                                    <strong>{{
                                        programToDelete.affiliates_count
                                    }}</strong>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span>{{ $t("affiliate.offers") }}:</span>
                                    <strong>{{
                                        programToDelete.offers_count
                                    }}</strong>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3 justify-end">
                            <button
                                @click="cancelDelete"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors"
                            >
                                {{ $t("common.cancel") }}
                            </button>
                            <button
                                @click="deleteProgram"
                                class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors"
                            >
                                {{ $t("common.delete") }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
