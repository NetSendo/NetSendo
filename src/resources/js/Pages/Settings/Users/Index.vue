<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    teamMembers: Array,
    pendingInvitations: Array,
    availableLists: Array,
});

// Modal states
const showAddUserModal = ref(false);
const showPermissionsModal = ref(false);
const selectedUser = ref(null);

// Form for adding new user
const addUserForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    list_permissions: [],
});

// Form for updating permissions
const permissionsForm = useForm({
    list_permissions: [],
});

function openAddUserModal() {
    addUserForm.reset();
    addUserForm.list_permissions = [];
    showAddUserModal.value = true;
}

function closeAddUserModal() {
    showAddUserModal.value = false;
}

function createUser() {
    addUserForm.post(route('settings.users.create-user'), {
        preserveScroll: true,
        onSuccess: () => {
            closeAddUserModal();
        },
    });
}

function openPermissionsModal(user) {
    selectedUser.value = user;
    permissionsForm.list_permissions = user.shared_lists.map(list => ({
        list_id: list.id,
        permission: list.permission,
    }));
    showPermissionsModal.value = true;
}

function closePermissionsModal() {
    showPermissionsModal.value = false;
    selectedUser.value = null;
}

function updatePermissions() {
    permissionsForm.put(route('settings.users.permissions', selectedUser.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            closePermissionsModal();
        },
    });
}

function toggleListPermission(listId) {
    const index = permissionsForm.list_permissions.findIndex(p => p.list_id === listId);
    if (index === -1) {
        permissionsForm.list_permissions.push({ list_id: listId, permission: 'view' });
    } else {
        permissionsForm.list_permissions.splice(index, 1);
    }
}

function getListPermission(listId) {
    return permissionsForm.list_permissions.find(p => p.list_id === listId);
}

function setListPermissionLevel(listId, level) {
    const perm = permissionsForm.list_permissions.find(p => p.list_id === listId);
    if (perm) {
        perm.permission = level;
    }
}

function deleteUser(userId) {
    if (confirm(t('users.delete_confirm'))) {
        router.delete(route('settings.users.destroy', userId));
    }
}

function cancelInvitation(invitationId) {
    if (confirm(t('users.cancel_invitation_confirm'))) {
        router.delete(route('settings.users.cancel-invitation', invitationId));
    }
}

const { locale } = useI18n();
function formatDateTime(dateString) {
    if (!dateString) return '-';
    return new Date(dateString).toLocaleString(locale.value);
}
</script>

<template>
    <Head :title="t('users.title')" />
    
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">
                    👥 {{ t('users.title') }}
                </h2>
                <button 
                    @click="openAddUserModal"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-500 transition-colors flex items-center gap-2"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                    </svg>
                    {{ t('users.add_user') }}
                </button>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Info Box -->
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                    <div class="flex items-start gap-3">
                        <svg class="h-5 w-5 text-blue-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-blue-800 dark:text-blue-200">{{ t('users.info_title') }}</p>
                            <p class="text-sm text-blue-600 dark:text-blue-300 mt-1">
                                {{ t('users.info_text') }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Team Members List -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                            {{ t('users.team_members') }}
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            {{ t('users.team_members_subtitle') }}
                        </p>
                    </div>
                    
                    <div v-if="teamMembers.length === 0" class="px-6 py-12 text-center">
                        <svg class="w-12 h-12 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <p class="mt-4 text-gray-500 dark:text-gray-400">
                            {{ t('users.no_members') }}
                        </p>
                        <button 
                            @click="openAddUserModal"
                            class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-500 transition-colors"
                        >
                            {{ t('users.add_first_user') }}
                        </button>
                    </div>

                    <table v-else class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ t('users.table.user') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ t('users.table.email') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ t('users.table.lists_access') }}
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ t('users.table.added') }}
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                    {{ t('users.table.actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-for="member in teamMembers" :key="member.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-medium">
                                            {{ member.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <span class="ml-3 font-medium text-gray-900 dark:text-white">{{ member.name }}</span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ member.email }}
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        <span 
                                            v-for="list in member.shared_lists.slice(0, 3)" 
                                            :key="list.id"
                                            class="px-2 py-0.5 text-xs rounded-full"
                                            :class="list.permission === 'edit' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'"
                                        >
                                            {{ list.name }}
                                            <span class="ml-1 opacity-75">{{ list.permission === 'edit' ? '✏️' : '👁️' }}</span>
                                        </span>
                                        <span 
                                            v-if="member.shared_lists.length > 3"
                                            class="px-2 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400"
                                        >
                                            +{{ member.shared_lists.length - 3 }}
                                        </span>
                                        <span 
                                            v-if="member.shared_lists.length === 0"
                                            class="text-sm text-gray-400 italic"
                                        >
                                            {{ t('users.no_lists_assigned') }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ member.created_at }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <button 
                                            @click="openPermissionsModal(member)"
                                            class="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition-colors"
                                            :title="t('users.manage_permissions')"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                            </svg>
                                        </button>
                                        <button 
                                            @click="deleteUser(member.id)"
                                            class="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition-colors"
                                            :title="t('users.delete_user')"
                                        >
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pending Invitations -->
                <div v-if="pendingInvitations.length > 0" class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                            📧 {{ t('users.pending_invitations') }}
                        </h3>
                    </div>
                    
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        <div v-for="invitation in pendingInvitations" :key="invitation.id" class="px-6 py-4 flex items-center justify-between">
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">{{ invitation.name }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ invitation.email }}</p>
                            </div>
                            <div class="flex items-center gap-4">
                                <span class="text-sm text-gray-400">{{ t('users.sent') }}: {{ invitation.created_at }}</span>
                                <button 
                                    @click="cancelInvitation(invitation.id)"
                                    class="text-red-600 hover:text-red-800 dark:text-red-400"
                                >
                                    {{ t('users.cancel') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add User Modal -->
        <div 
            v-if="showAddUserModal" 
            class="fixed inset-0 z-50 overflow-y-auto"
            @click.self="closeAddUserModal"
        >
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-black/50 transition-opacity"></div>
                
                <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-lg w-full p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                        👤 {{ t('users.modal.add_title') }}
                    </h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t('users.modal.name') }}
                            </label>
                            <input 
                                v-model="addUserForm.name"
                                type="text"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                required
                            />
                            <p v-if="addUserForm.errors.name" class="mt-1 text-sm text-red-600">{{ addUserForm.errors.name }}</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t('users.modal.email') }}
                            </label>
                            <input 
                                v-model="addUserForm.email"
                                type="email"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                required
                            />
                            <p v-if="addUserForm.errors.email" class="mt-1 text-sm text-red-600">{{ addUserForm.errors.email }}</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t('users.modal.password') }}
                            </label>
                            <input 
                                v-model="addUserForm.password"
                                type="password"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                required
                            />
                            <p v-if="addUserForm.errors.password" class="mt-1 text-sm text-red-600">{{ addUserForm.errors.password }}</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ t('users.modal.password_confirm') }}
                            </label>
                            <input 
                                v-model="addUserForm.password_confirmation"
                                type="password"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                required
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                {{ t('users.modal.list_permissions') }}
                            </label>
                            <div class="max-h-48 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-lg">
                                <label 
                                    v-for="list in availableLists" 
                                    :key="list.id"
                                    class="flex items-center gap-3 p-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer border-b border-gray-100 dark:border-gray-700 last:border-0"
                                >
                                    <input 
                                        type="checkbox"
                                        :value="{ list_id: list.id, permission: 'view' }"
                                        v-model="addUserForm.list_permissions"
                                        class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500"
                                    />
                                    <div class="flex-1">
                                        <span class="text-sm text-gray-900 dark:text-white">{{ list.name }}</span>
                                        <span v-if="list.group" class="ml-2 text-xs text-gray-500">{{ list.group }}</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end gap-3">
                        <button 
                            @click="closeAddUserModal"
                            class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                        >
                            {{ t('common.cancel') }}
                        </button>
                        <button 
                            @click="createUser"
                            :disabled="addUserForm.processing || !addUserForm.name || !addUserForm.email || !addUserForm.password"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-500 disabled:opacity-50 transition-colors"
                        >
                            {{ t('users.modal.create') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Permissions Modal -->
        <div 
            v-if="showPermissionsModal && selectedUser" 
            class="fixed inset-0 z-50 overflow-y-auto"
            @click.self="closePermissionsModal"
        >
            <div class="flex items-center justify-center min-h-screen px-4">
                <div class="fixed inset-0 bg-black/50 transition-opacity"></div>
                
                <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-lg w-full p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                        🔐 {{ t('users.permissions_modal.title') }}: {{ selectedUser.name }}
                    </h3>
                    
                    <div class="mb-4">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ t('users.permissions_modal.description') }}
                        </p>
                    </div>

                    <div class="max-h-96 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded-lg">
                        <div 
                            v-for="list in availableLists" 
                            :key="list.id"
                            class="flex items-center justify-between p-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 border-b border-gray-100 dark:border-gray-700 last:border-0"
                        >
                            <div class="flex items-center gap-3">
                                <input 
                                    type="checkbox"
                                    :checked="!!getListPermission(list.id)"
                                    @change="toggleListPermission(list.id)"
                                    class="w-4 h-4 text-blue-600 rounded focus:ring-blue-500"
                                />
                                <div>
                                    <span class="text-sm text-gray-900 dark:text-white">{{ list.name }}</span>
                                    <span v-if="list.group" class="ml-2 text-xs text-gray-500">{{ list.group }}</span>
                                </div>
                            </div>
                            <select 
                                v-if="getListPermission(list.id)"
                                :value="getListPermission(list.id).permission"
                                @change="setListPermissionLevel(list.id, $event.target.value)"
                                class="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-2 py-1 dark:bg-gray-700 dark:text-white"
                            >
                                <option value="view">👁️ {{ t('users.permission_view') }}</option>
                                <option value="edit">✏️ {{ t('users.permission_edit') }}</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end gap-3">
                        <button 
                            @click="closePermissionsModal"
                            class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                        >
                            {{ t('common.cancel') }}
                        </button>
                        <button 
                            @click="updatePermissions"
                            :disabled="permissionsForm.processing"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-500 disabled:opacity-50 transition-colors"
                        >
                            {{ t('users.permissions_modal.save') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
