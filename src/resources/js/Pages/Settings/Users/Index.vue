<script setup>
import { computed, ref } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ConfirmModal from "@/Components/ConfirmModal.vue";
import GoldBadge from "@/Components/License/GoldBadge.vue";
import GoldNotice from "@/Components/License/GoldNotice.vue";
import ActionMenu from "@/Components/Ui/ActionMenu.vue";
import ActionMenuItem from "@/Components/Ui/ActionMenuItem.vue";
import CreateLink from "@/Components/Ui/CreateLink.vue";
import EmptyState from "@/Components/Ui/EmptyState.vue";
import FormDialog from "@/Components/Ui/FormDialog.vue";
import PageHeader from "@/Components/Ui/PageHeader.vue";
import Panel from "@/Components/Ui/Panel.vue";
import Toast from "@/Components/Ui/Toast.vue";
import { errorClass, fieldClass, hintClass, labelClass } from "@/Components/Ui/fieldClass";
import { requireGold } from "@/Composables/useLicense";
import { useToast } from "@/Composables/useToast";
import ListPermissionsPicker from "./Partials/ListPermissionsPicker.vue";

const { t } = useI18n();
// The outcome of each action is worded here, in the user's language; the server's own
// message is shown only when something went wrong
const { toast, showToast, dismiss } = useToast({ flash: "error" });

const props = defineProps({
    teamMembers: Array,
    pendingInvitations: Array,
    availableLists: Array,
    // Team members allowed besides the owner; null = unlimited (GOLD)
    teamMemberLimit: { type: Number, default: null },
});

// SILVER has room for one team member (pending invitations count); more needs GOLD
const teamFull = computed(
    () =>
        props.teamMemberLimit !== null &&
        (props.teamMembers?.length ?? 0) + (props.pendingInvitations?.length ?? 0) >= props.teamMemberLimit,
);

const messagesOf = (form) => [...new Set(Object.values(form.errors).filter(Boolean))];

// ---- A new team member
const showAddUserModal = ref(false);

const addUserForm = useForm({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    list_permissions: [],
});

const openAddUserModal = () => {
    if (teamFull.value) {
        requireGold("unlimited_team_members");
        return;
    }
    addUserForm.reset();
    addUserForm.clearErrors();
    addUserForm.list_permissions = [];
    dismiss();
    showAddUserModal.value = true;
};

const closeAddUserModal = () => {
    showAddUserModal.value = false;
};

const createUser = () => {
    addUserForm.post(route("settings.users.create-user"), {
        preserveScroll: true,
        onSuccess: () => {
            closeAddUserModal();
            showToast(t("users.notifications.added"));
        },
    });
};

// ---- Which lists a member may open
const showPermissionsModal = ref(false);
const selectedUser = ref(null);

const permissionsForm = useForm({
    list_permissions: [],
});

const openPermissionsModal = (user) => {
    selectedUser.value = user;
    permissionsForm.clearErrors();
    permissionsForm.list_permissions = user.shared_lists.map((list) => ({
        list_id: list.id,
        permission: list.permission,
    }));
    dismiss();
    showPermissionsModal.value = true;
};

const closePermissionsModal = () => {
    showPermissionsModal.value = false;
};

const updatePermissions = () => {
    permissionsForm.put(route("settings.users.permissions", selectedUser.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closePermissionsModal();
            showToast(t("users.notifications.permissions_saved"));
        },
    });
};

// ---- Removing a member, withdrawing an invitation
const userToDelete = ref(null);
const invitationToCancel = ref(null);
const removing = ref(false);

const deleteUser = () => {
    if (!userToDelete.value) return;
    removing.value = true;
    router.delete(route("settings.users.destroy", userToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => showToast(t("users.notifications.removed")),
        onFinish: () => {
            removing.value = false;
            userToDelete.value = null;
        },
    });
};

const cancelInvitation = () => {
    if (!invitationToCancel.value) return;
    removing.value = true;
    router.delete(route("settings.users.cancel-invitation", invitationToCancel.value.id), {
        preserveScroll: true,
        onSuccess: () => showToast(t("users.notifications.invitation_cancelled")),
        onFinish: () => {
            removing.value = false;
            invitationToCancel.value = null;
        },
    });
};

// "Customers (edit), Newsletter" and how many more there are
const listsSummary = (member) =>
    member.shared_lists
        .slice(0, 2)
        .map((list) => (list.permission === "edit" ? t("users.list_with_edit", { name: list.name }) : list.name))
        .join(", ");

const dialogOpen = computed(() => showAddUserModal.value || showPermissionsModal.value);
</script>

<template>
    <Head :title="t('users.title')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader :title="t('users.title')" :subtitle="t('users.team_members_subtitle')">
                <CreateLink @click="openAddUserModal">
                    {{ t("users.add_user") }}
                    <GoldBadge v-if="teamFull" locked />
                </CreateLink>
            </PageHeader>
        </template>

        <div class="mx-auto flex max-w-screen-2xl flex-col gap-4">
            <GoldNotice
                v-if="teamFull"
                feature="unlimited_team_members"
                :message="t('license.gating.team_locked')"
            />

            <Panel flush :title="t('users.team_members')">
                <p class="px-5 pb-4 text-sm text-slate-600 dark:text-slate-400">
                    {{ t("users.info_text") }}
                </p>

                <EmptyState v-if="teamMembers.length === 0" :title="t('users.no_members')">
                    <CreateLink @click="openAddUserModal">{{ t("users.add_first_user") }}</CreateLink>
                </EmptyState>

                <div v-else class="relative overflow-x-auto border-t border-slate-200 dark:border-slate-700/60">
                    <table class="w-full text-sm sm:min-w-[720px]">
                        <thead>
                            <tr
                                class="border-b border-slate-200 text-left text-xs text-slate-600 dark:border-slate-700/60 dark:text-slate-400"
                            >
                                <th scope="col" class="px-5 py-2.5 font-medium">{{ t("users.table.user") }}</th>
                                <th scope="col" class="px-3 py-2.5 font-medium max-sm:hidden">
                                    {{ t("users.table.lists_access") }}
                                </th>
                                <th scope="col" class="px-3 py-2.5 font-medium max-sm:hidden">
                                    {{ t("users.table.added") }}
                                </th>
                                <th scope="col" class="py-2.5 pl-3 pr-5 text-right font-medium">
                                    <span class="sr-only">{{ t("users.table.actions") }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="member in teamMembers"
                                :key="member.id"
                                class="border-b border-slate-100 transition-colors last:border-0 hover:bg-slate-50 dark:border-slate-700/40 dark:hover:bg-slate-700/20"
                            >
                                <td class="max-w-0 px-5 py-3 max-sm:w-full sm:w-[40%]">
                                    <div class="flex items-center gap-3">
                                        <span
                                            aria-hidden="true"
                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-200 text-sm font-semibold text-slate-800 dark:bg-slate-700 dark:text-slate-100"
                                        >
                                            {{ member.name.charAt(0).toUpperCase() }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate font-medium text-slate-900 dark:text-white" :title="member.name">
                                                {{ member.name }}
                                            </p>
                                            <p class="truncate text-xs text-slate-600 dark:text-slate-400" :title="member.email">
                                                {{ member.email }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <td class="max-w-0 px-3 py-3 max-sm:hidden" style="width: 34%">
                                    <template v-if="member.shared_lists.length">
                                        <p
                                            class="truncate text-slate-900 dark:text-slate-100"
                                            :title="member.shared_lists.map((list) => list.name).join(', ')"
                                        >
                                            {{ listsSummary(member) }}
                                        </p>
                                        <p
                                            v-if="member.shared_lists.length > 2"
                                            class="text-xs tabular-nums text-slate-600 dark:text-slate-400"
                                        >
                                            {{ t("users.more_lists", { count: member.shared_lists.length - 2 }) }}
                                        </p>
                                    </template>
                                    <span v-else class="text-slate-600 dark:text-slate-400">
                                        {{ t("users.no_lists_assigned") }}
                                    </span>
                                </td>

                                <td class="whitespace-nowrap px-3 py-3 tabular-nums text-slate-600 max-sm:hidden dark:text-slate-400">
                                    {{ member.created_at }}
                                </td>

                                <td class="py-3 pl-3 pr-5">
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            type="button"
                                            class="inline-flex min-h-9 items-center whitespace-nowrap rounded-lg px-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900 max-sm:hidden dark:text-slate-200 dark:hover:bg-slate-700/60 dark:hover:text-white"
                                            @click="openPermissionsModal(member)"
                                        >
                                            {{ t("users.permissions_short") }}
                                        </button>
                                        <ActionMenu :label="t('users.actions_for', { name: member.name })">
                                            <!-- On a phone the button beside the menu has no room: it is the first entry here -->
                                            <ActionMenuItem class="sm:hidden" @click="openPermissionsModal(member)">
                                                {{ t("users.manage_permissions") }}
                                            </ActionMenuItem>
                                            <ActionMenuItem danger @click="userToDelete = member">
                                                {{ t("users.delete_user") }}
                                            </ActionMenuItem>
                                        </ActionMenu>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Panel>

            <!-- Invitations nobody has accepted yet -->
            <Panel v-if="pendingInvitations.length > 0" flush :title="t('users.pending_invitations')">
                <ul class="divide-y divide-slate-100 border-t border-slate-200 dark:divide-slate-700/40 dark:border-slate-700/60">
                    <li
                        v-for="invitation in pendingInvitations"
                        :key="invitation.id"
                        class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-5 py-3"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-slate-900 dark:text-white">{{ invitation.name }}</p>
                            <p class="truncate text-xs text-slate-600 dark:text-slate-400">{{ invitation.email }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xs tabular-nums text-slate-600 dark:text-slate-400">
                                {{ t("users.sent") }}: {{ invitation.created_at }}
                            </span>
                            <button
                                type="button"
                                class="inline-flex min-h-9 items-center rounded-lg px-2.5 text-sm font-medium text-rose-700 transition-colors hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/10"
                                @click="invitationToCancel = invitation"
                            >
                                {{ t("users.cancel") }}
                            </button>
                        </div>
                    </li>
                </ul>
            </Panel>
        </div>

        <!-- A new team member: who, how they sign in, what they may open -->
        <FormDialog
            :show="showAddUserModal"
            :title="t('users.modal.add_title')"
            :processing="addUserForm.processing"
            :submit-label="t('users.modal.create')"
            :errors="messagesOf(addUserForm)"
            :toast="toast"
            @close="closeAddUserModal"
            @submit="createUser"
        >
            <div class="space-y-4">
                <div>
                    <label for="team-name" :class="labelClass">{{ t("users.modal.name") }}</label>
                    <input
                        id="team-name"
                        v-model="addUserForm.name"
                        type="text"
                        required
                        autocomplete="off"
                        class="mt-1"
                        :class="[fieldClass, { '!border-rose-500': addUserForm.errors.name }]"
                    />
                    <p v-if="addUserForm.errors.name" :class="errorClass">{{ addUserForm.errors.name }}</p>
                </div>
                <div>
                    <label for="team-email" :class="labelClass">{{ t("users.modal.email") }}</label>
                    <input
                        id="team-email"
                        v-model="addUserForm.email"
                        type="email"
                        required
                        autocomplete="off"
                        class="mt-1"
                        :class="[fieldClass, { '!border-rose-500': addUserForm.errors.email }]"
                    />
                    <p v-if="addUserForm.errors.email" :class="errorClass">{{ addUserForm.errors.email }}</p>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="team-password" :class="labelClass">{{ t("users.modal.password") }}</label>
                        <input
                            id="team-password"
                            v-model="addUserForm.password"
                            type="password"
                            required
                            autocomplete="new-password"
                            class="mt-1"
                            :class="[fieldClass, { '!border-rose-500': addUserForm.errors.password }]"
                        />
                        <p v-if="addUserForm.errors.password" :class="errorClass">{{ addUserForm.errors.password }}</p>
                    </div>
                    <div>
                        <label for="team-password-confirm" :class="labelClass">
                            {{ t("users.modal.password_confirm") }}
                        </label>
                        <input
                            id="team-password-confirm"
                            v-model="addUserForm.password_confirmation"
                            type="password"
                            required
                            autocomplete="new-password"
                            class="mt-1"
                            :class="fieldClass"
                        />
                    </div>
                </div>
                <div>
                    <span :class="labelClass">{{ t("users.modal.list_permissions") }}</span>
                    <p :class="hintClass" class="mb-2">{{ t("users.permissions_modal.description") }}</p>
                    <ListPermissionsPicker v-model="addUserForm.list_permissions" :lists="availableLists" />
                </div>
            </div>
        </FormDialog>

        <FormDialog
            :show="showPermissionsModal"
            :title="t('users.permissions_modal.title') + ': ' + (selectedUser?.name || '')"
            :processing="permissionsForm.processing"
            :submit-label="t('users.permissions_modal.save')"
            :errors="messagesOf(permissionsForm)"
            :toast="toast"
            @close="closePermissionsModal"
            @submit="updatePermissions"
        >
            <p class="mb-3 text-sm text-slate-600 dark:text-slate-400">
                {{ t("users.permissions_modal.description") }}
            </p>
            <ListPermissionsPicker v-model="permissionsForm.list_permissions" :lists="availableLists" />
        </FormDialog>

        <ConfirmModal
            :show="!!userToDelete"
            type="danger"
            :title="t('users.delete_title')"
            :message="t('users.delete_confirm')"
            :confirm-text="t('users.delete_user')"
            :processing="removing"
            @close="userToDelete = null"
            @confirm="deleteUser"
        >
            {{ userToDelete?.name }} ({{ userToDelete?.email }})
        </ConfirmModal>

        <ConfirmModal
            :show="!!invitationToCancel"
            type="danger"
            :title="t('users.cancel_invitation_title')"
            :message="t('users.cancel_invitation_confirm')"
            :confirm-text="t('users.cancel_invitation')"
            :processing="removing"
            @close="invitationToCancel = null"
            @confirm="cancelInvitation"
        >
            {{ invitationToCancel?.email }}
        </ConfirmModal>

        <!-- In the corner of the page; an open dialog shows the same message in its footer -->
        <Toast :toast="dialogOpen ? null : toast" @close="dismiss" />
    </AuthenticatedLayout>
</template>
