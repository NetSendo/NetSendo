<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import PartnerLayout from '@/Layouts/PartnerLayout.vue';

const props = defineProps({
    links: Array,
});

const copyLink = (link, index) => {
    navigator.clipboard.writeText(link.tracking_url);
    copiedIndex.value = index;
    setTimeout(() => copiedIndex.value = null, 2000);
};

const copiedIndex = ref(null);
</script>

<template>
    <Head title="My Links" />

    <PartnerLayout>
        <div class="space-y-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">My Tracking Links</h1>

            <div v-if="links?.length" class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 divide-y divide-gray-200 dark:divide-slate-700">
                <div v-for="(link, index) in links" :key="link.id" class="p-4 flex items-center justify-between">
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-900 dark:text-white truncate">{{ link.offer?.name }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ link.tracking_url }}</p>
                    </div>
                    <div class="flex items-center gap-4 ml-4">
                        <span class="text-sm text-gray-600 dark:text-gray-300">{{ link.clicks_count || 0 }} clicks</span>
                        <button
                            @click="copyLink(link, index)"
                            class="px-3 py-1 text-sm bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400 rounded-lg hover:bg-indigo-200"
                        >
                            {{ copiedIndex === index ? 'Copied!' : 'Copy' }}
                        </button>
                    </div>
                </div>
            </div>

            <div v-else class="bg-white dark:bg-slate-800 rounded-xl p-8 text-center text-gray-500 dark:text-gray-400">
                No tracking links yet. Browse offers to generate your first link!
            </div>
        </div>
    </PartnerLayout>
</template>
