<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PartnerLayout from '@/Layouts/PartnerLayout.vue';

const props = defineProps({
    offer: Object,
    existingLink: Object,
});

const form = useForm({ offer_id: props.offer.id });
const showCopied = ref(false);

const generateLink = () => {
    form.post(route('partner.links.generate'));
};

const copyLink = () => {
    if (props.existingLink) {
        navigator.clipboard.writeText(props.existingLink.tracking_url);
        showCopied.value = true;
        setTimeout(() => showCopied.value = false, 2000);
    }
};
</script>

<template>
    <Head :title="offer.name" />

    <PartnerLayout>
        <div class="max-w-3xl space-y-6">
            <Link :href="route('partner.offers')" class="inline-flex items-center text-gray-600 dark:text-gray-400 hover:text-gray-900">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to Offers
            </Link>

            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6">
                <div class="flex items-start gap-4 mb-6">
                    <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ offer.name }}</h1>
                        <span class="inline-block mt-1 px-3 py-1 bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 text-sm font-medium rounded-full">
                            {{ offer.commission_type === 'percent' ? `${offer.commission_value}% Commission` : `${offer.commission_value} PLN per conversion` }}
                        </span>
                    </div>
                </div>

                <p v-if="offer.description" class="text-gray-600 dark:text-gray-400 mb-6">{{ offer.description }}</p>

                <!-- Generate or Show Link -->
                <div class="bg-gray-50 dark:bg-slate-700/50 rounded-xl p-6">
                    <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Your Tracking Link</h3>

                    <div v-if="existingLink" class="space-y-4">
                        <div class="flex gap-2">
                            <input
                                type="text"
                                :value="existingLink.tracking_url"
                                readonly
                                class="flex-1 rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white text-sm"
                            />
                            <button
                                @click="copyLink"
                                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700"
                            >
                                {{ showCopied ? 'Copied!' : 'Copy' }}
                            </button>
                        </div>
                        <div class="flex gap-6 text-sm text-gray-600 dark:text-gray-400">
                            <span>Clicks: {{ existingLink.clicks_count || 0 }}</span>
                        </div>
                    </div>

                    <div v-else>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">Generate a unique tracking link for this offer.</p>
                        <button
                            @click="generateLink"
                            :disabled="form.processing"
                            class="px-6 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-medium rounded-xl hover:from-indigo-700 hover:to-purple-700 disabled:opacity-50"
                        >
                            Generate Link
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </PartnerLayout>
</template>
