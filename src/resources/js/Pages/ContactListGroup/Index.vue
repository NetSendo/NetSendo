<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    groups: Array,
});

const form = ref({
    name: '',
});

const editingGroup = ref(null);

const submit = () => {
    if (editingGroup.value) {
        router.put(route('groups.update', editingGroup.value.id), { name: form.value.name }, {
            onSuccess: () => {
                editingGroup.value = null;
                form.value.name = '';
            },
        });
    } else {
        router.post(route('groups.store'), form.value, {
            onSuccess: () => form.value.name = '',
        });
    }
};

const edit = (group) => {
    editingGroup.value = group;
    form.value.name = group.name;
};

const cancelEdit = () => {
    editingGroup.value = null;
    form.value.name = '';
};

const destroy = (id) => {
    if (confirm($t('groups.confirm_delete'))) {
        router.delete(route('groups.destroy', id));
    }
};
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

                        <div v-else class="overflow-hidden rounded-lg border border-slate-700 bg-slate-800 shadow-xl">
                            <table class="min-w-full divide-y divide-slate-700">
                                <thead class="bg-slate-900/50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-400">{{ $t('groups.table.name') }}</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-400">{{ $t('groups.table.lists_count') }}</th>
                                        <th scope="col" class="relative px-6 py-3"><span class="sr-only">{{ $t('groups.table.actions') }}</span></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-700 bg-slate-800">
                                    <tr v-for="group in groups" :key="group.id">
                                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-white">{{ group.name }}</td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-400">{{ group.contact_lists_count }}</td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                            <button @click="edit(group)" class="text-indigo-400 hover:text-indigo-300 mr-3">{{ $t('common.edit') }}</button>
                                            <button @click="destroy(group.id)" class="text-red-400 hover:text-red-300">{{ $t('common.delete') }}</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
