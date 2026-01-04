<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import Modal from "@/Components/Modal.vue";
import DangerButton from "@/Components/DangerButton.vue";
import { ref } from "vue";

defineProps({
    externalPages: Object,
});

const { t } = useI18n();

// Toast Notification
const toast = ref(null);
const showToast = (message, success = true) => {
    toast.value = { message, success };
    setTimeout(() => {
        toast.value = null;
    }, 4000);
};

// Delete Confirmation Modal
const showDeleteModal = ref(false);
const deletingPage = ref(null);

const confirmDelete = (page) => {
    deletingPage.value = page;
    showDeleteModal.value = true;
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    deletingPage.value = null;
};

const deletePage = () => {
    if (!deletingPage.value) return;

    router.delete(route("external-pages.destroy", deletingPage.value.id), {
        onSuccess: () => {
            closeDeleteModal();
            showToast(t("external_pages.messages.deleted"));
        },
        onError: () => {
            showToast(t("common.notifications.error"), false);
        },
    });
};

const copyLink = (id) => {
    const url = route("page.show", id);
    navigator.clipboard
        .writeText(url)
        .then(() => {
            showToast(t("external_pages.messages.link_copied"));
        })
        .catch(() => {
            showToast(t("common.notifications.error"), false);
        });
};
</script>

<template>
    <Head :title="$t('external_pages.index.title')" />

    <AuthenticatedLayout>
        <!-- Toast Notification -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-300"
                enter-from-class="opacity-0 translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 translate-y-2"
            >
                <div
                    v-if="toast"
                    class="fixed bottom-6 right-6 z-[200] flex items-center gap-3 rounded-xl px-5 py-4 shadow-lg"
                    :class="
                        toast.success
                            ? 'bg-emerald-600 text-white'
                            : 'bg-rose-600 text-white'
                    "
                >
                    <svg
                        v-if="toast.success"
                        class="h-5 w-5 flex-shrink-0"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <svg
                        v-else
                        class="h-5 w-5 flex-shrink-0"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                    <span class="font-medium">{{ toast.message }}</span>
                    <button
                        @click="toast = null"
                        class="ml-2 opacity-80 hover:opacity-100"
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
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>
            </Transition>
        </Teleport>

        <template #header>
            <div class="flex justify-between items-center">
                <h2
                    class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight"
                >
                    {{ $t("external_pages.index.title") }}
                </h2>
                <Link :href="route('external-pages.create')">
                    <PrimaryButton>{{
                        $t("external_pages.index.add_new")
                    }}</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div
                    class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg"
                >
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div
                            v-if="externalPages.data.length === 0"
                            class="text-center py-8 text-gray-500"
                        >
                            {{ $t("external_pages.index.empty") }}
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table
                                class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"
                            >
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th
                                            scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                                        >
                                            {{
                                                $t("external_pages.labels.name")
                                            }}
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                                        >
                                            {{
                                                $t(
                                                    "external_pages.labels.target_url"
                                                )
                                            }}
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                                        >
                                            {{
                                                $t("external_pages.labels.type")
                                            }}
                                        </th>
                                        <th
                                            scope="col"
                                            class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                                        >
                                            {{
                                                $t(
                                                    "external_pages.labels.actions"
                                                )
                                            }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700"
                                >
                                    <tr
                                        v-for="page in externalPages.data"
                                        :key="page.id"
                                    >
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white"
                                        >
                                            {{ page.name }}
                                        </td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 truncate max-w-xs"
                                        >
                                            {{ page.url }}
                                        </td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300"
                                        >
                                            <span
                                                v-if="page.is_redirect"
                                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800"
                                            >
                                                {{
                                                    $t(
                                                        "external_pages.types.redirect"
                                                    )
                                                }}
                                            </span>
                                            <span
                                                v-else
                                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800"
                                            >
                                                {{
                                                    $t(
                                                        "external_pages.types.proxy"
                                                    )
                                                }}
                                            </span>
                                        </td>
                                        <td
                                            class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2"
                                        >
                                            <button
                                                @click="copyLink(page.id)"
                                                class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                                            >
                                                {{
                                                    $t(
                                                        "external_pages.actions.link"
                                                    )
                                                }}
                                            </button>
                                            <Link
                                                :href="
                                                    route(
                                                        'external-pages.edit',
                                                        page.id
                                                    )
                                                "
                                                class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-200"
                                                >{{ $t("common.edit") }}</Link
                                            >
                                            <button
                                                @click="confirmDelete(page)"
                                                class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-200"
                                            >
                                                {{ $t("common.delete") }}
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div v-if="externalPages.links.length > 3" class="mt-4">
                            <div class="flex justify-center">
                                <Link
                                    v-for="(link, k) in externalPages.links"
                                    :key="k"
                                    class="px-3 py-1 border rounded text-sm mx-1"
                                    :class="{
                                        'bg-indigo-600 text-white': link.active,
                                        'bg-white text-gray-700': !link.active,
                                    }"
                                    :href="link.url"
                                    v-html="link.label"
                                />
                            </div>
                        </div>

                        <!-- Delete Confirmation Modal -->
                        <Modal
                            :show="showDeleteModal"
                            @close="closeDeleteModal"
                        >
                            <div class="p-6">
                                <h2
                                    class="text-lg font-medium text-gray-900 dark:text-gray-100"
                                >
                                    {{
                                        $t(
                                            "external_pages.messages.confirm_delete"
                                        )
                                    }}
                                </h2>

                                <p
                                    class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    {{ $t("common.messages.delete_warning") }}
                                </p>

                                <div class="mt-6 flex justify-end">
                                    <SecondaryButton @click="closeDeleteModal">
                                        {{ $t("common.cancel") }}
                                    </SecondaryButton>

                                    <DangerButton
                                        class="ml-3"
                                        @click="deletePage"
                                    >
                                        {{ $t("common.delete") }}
                                    </DangerButton>
                                </div>
                            </div>
                        </Modal>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
