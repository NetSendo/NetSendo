<script setup>
import { Head } from '@inertiajs/vue3';
import PartnerLayout from '@/Layouts/PartnerLayout.vue';

const props = defineProps({
    assets: Array,
});
</script>

<template>
    <Head title="Marketing Assets" />

    <PartnerLayout>
        <div class="space-y-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Marketing Assets</h1>

            <div v-if="assets?.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="asset in assets" :key="asset.id" class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden">
                    <div v-if="asset.type === 'image'" class="aspect-video bg-gray-100 dark:bg-slate-700">
                        <img :src="asset.url" :alt="asset.name" class="w-full h-full object-cover" />
                    </div>
                    <div class="p-4">
                        <p class="font-medium text-gray-900 dark:text-white mb-1">{{ asset.name }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">{{ asset.description }}</p>
                        <a
                            :href="asset.url"
                            download
                            class="inline-flex items-center text-sm text-indigo-600 dark:text-indigo-400 hover:underline"
                        >
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Download
                        </a>
                    </div>
                </div>
            </div>

            <div v-else class="bg-white dark:bg-slate-800 rounded-xl p-8 text-center text-gray-500 dark:text-gray-400">
                No marketing assets available yet.
            </div>
        </div>
    </PartnerLayout>
</template>
