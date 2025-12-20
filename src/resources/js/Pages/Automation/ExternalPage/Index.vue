<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

defineProps({
    externalPages: Object,
});

const deletePage = (id) => {
    if (confirm('Are you sure you want to delete this page?')) {
        router.delete(route('external-pages.destroy', id));
    }
};

const copyLink = (id) => {
    const url = route('page.show', id);
    navigator.clipboard.writeText(url);
    alert('Link copied to clipboard!');
}
</script>

<template>
    <Head title="External Pages" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">External Pages</h2>
                <Link :href="route('external-pages.create')">
                    <PrimaryButton>Add New Page</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        
                        <div v-if="externalPages.data.length === 0" class="text-center py-8 text-gray-500">
                            No external pages found. Create one to get started.
                        </div>

                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Target URL</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-for="page in externalPages.data" :key="page.id">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                            {{ page.name }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300 truncate max-w-xs">
                                            {{ page.url }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">
                                            <span v-if="page.is_redirect" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                Redirect
                                            </span>
                                            <span v-else class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                Content Proxy
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                            <button @click="copyLink(page.id)" class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">Link</button>
                                            <Link :href="route('external-pages.edit', page.id)" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-200">Edit</Link>
                                            <button @click="deletePage(page.id)" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-200">Delete</button>
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
                                    :class="{'bg-indigo-600 text-white': link.active, 'bg-white text-gray-700': !link.active}"
                                    :href="link.url"
                                    v-html="link.label"
                                />
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
