<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import GroupTreeItem from '@/Components/GroupTreeItem.vue';
import DeleteGroupModal from './DeleteGroupModal.vue';

const props = defineProps({
    groups: Array,
    allGroups: Array,
});

const form = ref({
    name: '',
    parent_id: '',
});

const editingGroup = ref(null);

// Filter out the editing group and its descendants from parent options
const availableParents = computed(() => {
    if (!editingGroup.value) {
        return props.allGroups;
    }

    // Get all descendant IDs (simple recursive check via depth/parent)
    const getDescendantIds = (groupId, groups) => {
        const ids = [groupId];
        groups.forEach(g => {
            if (g.parent_id === groupId) {
                ids.push(...getDescendantIds(g.id, groups));
            }
        });
        return ids;
    };

    const excludeIds = getDescendantIds(editingGroup.value.id, props.allGroups);
    return props.allGroups.filter(g => !excludeIds.includes(g.id));
});

const submit = () => {
    if (editingGroup.value) {
        router.put(route('groups.update', editingGroup.value.id), {
            name: form.value.name,
            parent_id: form.value.parent_id || null,
        }, {
            onSuccess: () => {
                editingGroup.value = null;
                form.value.name = '';
                form.value.parent_id = '';
            },
        });
    } else {
        router.post(route('groups.store'), {
            name: form.value.name,
            parent_id: form.value.parent_id || null,
        }, {
            onSuccess: () => {
                form.value.name = '';
                form.value.parent_id = '';
            },
        });
    }
};

const edit = (group) => {
    editingGroup.value = group;
    form.value.name = group.name;
    form.value.parent_id = group.parent_id || '';
};

const cancelEdit = () => {
    editingGroup.value = null;
    form.value.name = '';
    form.value.parent_id = '';
};

// Modal state
const confirmingGroupDeletion = ref(false);
const groupToDelete = ref(null);

const confirmDelete = (group) => {
    groupToDelete.value = group;
    confirmingGroupDeletion.value = true;
};

const closeDeleteModal = () => {
    confirmingGroupDeletion.value = false;
    setTimeout(() => {
        groupToDelete.value = null;
    }, 300);
};

// Count all groups (including nested)
const totalGroupsCount = computed(() => {
    return props.allGroups?.length || 0;
});
</script>

<template>
    <Head :title="$t('groups.title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-white">
                {{ $t('groups.title') }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">

                    <!-- Form -->
                    <div class="rounded-lg bg-slate-800 p-6 shadow-xl border border-slate-700">
                        <h3 class="mb-4 text-lg font-medium text-white">
                            {{ editingGroup ? $t('groups.edit_group') : $t('groups.add_new') }}
                        </h3>
                        <form @submit.prevent="submit" class="space-y-4">
                            <div>
                                <label for="name" class="block text-sm font-medium text-slate-300">{{ $t('groups.fields.name') }}</label>
                                <input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    class="mt-1 block w-full rounded-md border-slate-600 bg-slate-900 text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    required
                                />
                            </div>

                            <div>
                                <label for="parent_id" class="block text-sm font-medium text-slate-300">{{ $t('groups.fields.parent') }}</label>
                                <select
                                    id="parent_id"
                                    v-model="form.parent_id"
                                    class="mt-1 block w-full rounded-md border-slate-600 bg-slate-900 text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                >
                                    <option value="">{{ $t('groups.no_parent') }}</option>
                                    <option
                                        v-for="group in availableParents"
                                        :key="group.id"
                                        :value="group.id"
                                    >
                                        {{ '—'.repeat(group.depth) }}{{ group.depth > 0 ? ' ' : '' }}{{ group.name }}
                                    </option>
                                </select>
                            </div>

                            <div class="flex items-center gap-2">
                                <button
                                    type="submit"
                                    class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                >
                                    {{ editingGroup ? $t('groups.actions.update') : $t('groups.actions.create') }}
                                </button>
                                <button
                                    v-if="editingGroup"
                                    @click="cancelEdit"
                                    type="button"
                                    class="inline-flex justify-center rounded-md border border-slate-600 bg-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-slate-600"
                                >
                                    {{ $t('groups.actions.cancel') }}
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- List -->
                    <div class="md:col-span-2 space-y-4">
                        <div v-if="groups.length === 0" class="rounded-lg bg-slate-800 p-6 text-center text-slate-400 border border-slate-700">
                            {{ $t('groups.empty_state') }}
                        </div>

                        <div v-else class="rounded-lg border border-slate-700 bg-slate-800 shadow-xl overflow-hidden">
                            <!-- Header -->
                            <div class="bg-slate-900/50 px-6 py-3 flex items-center justify-between">
                                <span class="text-xs font-medium uppercase tracking-wider text-slate-400">
                                    {{ $t('groups.table.name') }}
                                </span>
                                <div class="flex gap-6">
                                    <span class="text-xs font-medium uppercase tracking-wider text-slate-400 w-20 text-center">
                                        {{ $t('groups.table.lists_count') }}
                                    </span>
                                    <span class="text-xs font-medium uppercase tracking-wider text-slate-400 w-24 text-right">
                                        {{ $t('groups.table.actions') }}
                                    </span>
                                </div>
                            </div>

                            <!-- Tree -->
                            <div class="divide-y divide-slate-700">
                                <GroupTreeItem
                                    v-for="group in groups"
                                    :key="group.id"
                                    :group="group"
                                    :depth="0"
                                    @edit="edit"
                                    @delete="confirmDelete"
                                />
                            </div>
                        </div>

                        <!-- Total count footer -->
                        <div class="text-sm text-slate-400 text-right">
                            {{ $t('groups.total_count', { count: totalGroupsCount }) }}
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <DeleteGroupModal
            :show="confirmingGroupDeletion"
            :group="groupToDelete"
            @close="closeDeleteModal"
        />
    </AuthenticatedLayout>
</template>
