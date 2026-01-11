<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import DeleteTagModal from './DeleteTagModal.vue';

const props = defineProps({
    tags: Array,
});

const form = ref({
    name: '',
    color: '#3b82f6',
});

const editingTag = ref(null);

const submit = () => {
    if (editingTag.value) {
        router.put(route('tags.update', editingTag.value.id), form.value, {
            onSuccess: () => {
                editingTag.value = null;
                form.value.name = '';
                form.value.color = '#3b82f6';
            },
        });
    } else {
        router.post(route('tags.store'), form.value, {
            onSuccess: () => {
                form.value.name = '';
                form.value.color = '#3b82f6';
            },
        });
    }
};

const edit = (tag) => {
    editingTag.value = tag;
    form.value.name = tag.name;
    form.value.color = tag.color;
};

const cancelEdit = () => {
    editingTag.value = null;
    form.value.name = '';
    form.value.color = '#3b82f6';
};

const destroy = (tag) => {
    confirmDelete(tag);
};

// Modal state
const confirmingTagDeletion = ref(false);
const tagToDelete = ref(null);

const confirmDelete = (tag) => {
    tagToDelete.value = tag;
    confirmingTagDeletion.value = true;
};

const closeDeleteModal = () => {
    confirmingTagDeletion.value = false;
    setTimeout(() => {
        tagToDelete.value = null;
    }, 300);
};
</script>

<template>
    <Head :title="$t('tags.head_title')" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-white">
                {{ $t('tags.title') }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">

                    <!-- Form -->
                    <div class="rounded-lg bg-slate-800 p-6 shadow-xl border border-slate-700">
                        <h3 class="mb-4 text-lg font-medium text-white">
                            {{ editingTag ? $t('tags.edit_tag') : $t('tags.add_new') }}
                        </h3>
                        <form @submit.prevent="submit" class="space-y-4">
                            <div>
                                <label for="name" class="block text-sm font-medium text-slate-300">{{ $t('tags.fields.name') }}</label>
                                <input
                                    id="name"
                                    v-model="form.name"
                                    type="text"
                                    class="mt-1 block w-full rounded-md border-slate-600 bg-slate-900 text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    required
                                />
                            </div>
                            <div>
                                <label for="color" class="block text-sm font-medium text-slate-300">{{ $t('tags.fields.color') }}</label>
                                <div class="flex items-center gap-2 mt-1">
                                    <input
                                        id="color"
                                        v-model="form.color"
                                        type="color"
                                        class="h-9 w-9 cursor-pointer rounded border border-slate-600 bg-slate-900 p-1"
                                    />
                                    <input
                                        v-model="form.color"
                                        type="text"
                                        class="block w-full rounded-md border-slate-600 bg-slate-900 text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                    />
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    type="submit"
                                    class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                >
                                    {{ editingTag ? $t('groups.actions.update') : $t('groups.actions.create') }}
                                </button>
                                <button
                                    v-if="editingTag"
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
                        <div v-if="tags.length === 0" class="rounded-lg bg-slate-800 p-6 text-center text-slate-400 border border-slate-700">
                            {{ $t('tags.empty_state') }}
                        </div>

                        <div v-else class="rounded-lg bg-slate-800 p-6 border border-slate-700">
                             <div class="flex flex-wrap gap-2">
                                <div
                                    v-for="tag in tags"
                                    :key="tag.id"
                                    class="group relative inline-flex items-center rounded-full px-3 py-1 text-sm font-medium text-white transition-transform hover:scale-105"
                                    :style="{ backgroundColor: tag.color }"
                                >
                                    {{ tag.name }}
                                    <div class="ml-2 flex items-center space-x-1 border-l border-white/20 pl-2">
                                        <button @click="edit(tag)" class="opacity-70 hover:opacity-100" :title="$t('common.edit')">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <button @click="destroy(tag)" class="opacity-70 hover:opacity-100" :title="$t('common.delete')">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        </button>
                                    </div>
                                    <span class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-slate-900 text-[10px] ring-1 ring-white/50">
                                        {{ tag.contact_lists_count }}
                                    </span>
                                </div>
                             </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <DeleteTagModal
            :show="confirmingTagDeletion"
            :tag="tagToDelete"
            @close="closeDeleteModal"
        />
    </AuthenticatedLayout>
</template>
