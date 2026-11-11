<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import { onMounted, ref } from "vue";
import PageHeader from "@/Components/Ui/PageHeader.vue";
import ListsBrowser from "@/Components/ContactLists/ListsBrowser.vue";
import DeleteListModal from "./Partials/DeleteListModal.vue";
import CopyListModal from "./Partials/CopyListModal.vue";

const props = defineProps({
    lists: Object,
    filters: [Object, Array],
    groups: Array,
    tags: Array,
    allLists: Array,
});

// Delete dialog
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

// Copy dialog
const confirmingListCopy = ref(false);
const listToCopy = ref(null);

const confirmCopyList = (list) => {
    listToCopy.value = list;
    confirmingListCopy.value = true;
};

const closeCopyModal = () => {
    confirmingListCopy.value = false;
    setTimeout(() => {
        listToCopy.value = null;
    }, 300);
};

// Flash messages of the last action
const toast = ref(null);
const showToast = (message, success = true) => {
    toast.value = { message, success };
    setTimeout(() => {
        toast.value = null;
    }, 4000);
};

onMounted(() => {
    const flash = usePage().props.flash;
    if (flash?.success) showToast(flash.success);
    if (flash?.error) showToast(flash.error, false);
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

    <CopyListModal :show="confirmingListCopy" :list="listToCopy" @close="closeCopyModal" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader :title="$t('mailing_lists.title')" :subtitle="$t('mailing_lists.subtitle')">
                <Link
                    :href="route('mailing-lists.create')"
                    class="inline-flex min-h-10 items-center gap-2 rounded-xl bg-indigo-600 px-4 text-sm font-semibold text-white transition-colors hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
                >
                    <svg
                        aria-hidden="true"
                        class="h-4 w-4"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.4"
                        stroke-linecap="round"
                    >
                        <path d="M12 5v14M5 12h14" />
                    </svg>
                    {{ $t("mailing_lists.new_list") }}
                </Link>
            </PageHeader>
        </template>

        <div class="mx-auto flex max-w-screen-2xl flex-col gap-4">
            <ListsBrowser
                :lists="lists"
                :filters="filters"
                :groups="groups"
                :tags="tags"
                ns="mailing_lists"
                list-type="email"
                index-route="mailing-lists.index"
                create-route="mailing-lists.create"
                edit-route="mailing-lists.edit"
                message-route="messages.create"
                :message-label="$t('mailing_lists.create_message')"
                view-key="mailingList_viewMode"
                @delete="confirmDeleteList"
                @copy="confirmCopyList"
            />
        </div>

        <!-- What the last action did -->
        <Teleport to="body">
            <Transition
                enter-active-class="transform transition duration-300 ease-out"
                enter-from-class="translate-y-2 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="toast"
                    role="status"
                    class="fixed bottom-6 right-6 z-50 flex max-w-sm items-start gap-3 rounded-xl border bg-white px-4 py-3 text-sm text-slate-900 shadow-lg dark:bg-slate-800 dark:text-white"
                    :class="
                        toast.success
                            ? 'border-emerald-200 dark:border-emerald-500/30'
                            : 'border-rose-200 dark:border-rose-500/30'
                    "
                >
                    <span
                        aria-hidden="true"
                        class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                        :class="toast.success ? 'bg-emerald-600 dark:bg-emerald-400' : 'bg-rose-600 dark:bg-rose-400'"
                    ></span>
                    <span class="min-w-0 flex-1">{{ toast.message }}</span>
                    <button
                        type="button"
                        :aria-label="$t('common.close')"
                        class="-mr-1 -mt-1 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-700/60"
                        @click="toast = null"
                    >
                        <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" />
                        </svg>
                    </button>
                </div>
            </Transition>
        </Teleport>
    </AuthenticatedLayout>
</template>
