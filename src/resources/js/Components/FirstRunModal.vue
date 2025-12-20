<script setup>
import Modal from '@/Components/Modal.vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <Modal :show="show" :closeable="false" max-width="lg">
        <div class="p-8 text-center">
            <!-- Welcome Icon -->
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gradient-to-br from-purple-500 via-indigo-500 to-blue-500 mb-6 shadow-xl shadow-purple-500/30 animate-pulse">
                <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                </svg>
            </div>

            <!-- Title -->
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                {{ t('first_run.modal_title') }}
            </h2>

            <!-- Subtitle -->
            <p class="text-gray-500 dark:text-gray-400 mb-6">
                {{ t('first_run.modal_subtitle') }}
            </p>

            <!-- Description -->
            <div class="bg-gradient-to-r from-purple-50 to-indigo-50 dark:from-purple-900/20 dark:to-indigo-900/20 border border-purple-100 dark:border-purple-800/30 rounded-xl p-5 mb-6">
                <p class="text-gray-700 dark:text-gray-300 text-sm leading-relaxed">
                    {{ t('first_run.modal_description') }}
                </p>
            </div>

            <!-- Features List -->
            <div class="mb-8">
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400 mb-3">
                    {{ t('first_run.features_heading') }}
                </p>
                <div class="flex flex-wrap justify-center gap-3">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 rounded-full text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        {{ t('first_run.feature_1') }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 rounded-full text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        {{ t('first_run.feature_2') }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 rounded-full text-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        {{ t('first_run.feature_3') }}
                    </span>
                </div>
            </div>

            <!-- CTA Button -->
            <Link
                :href="route('register')"
                class="inline-flex items-center justify-center gap-2 w-full px-6 py-4 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-semibold rounded-xl shadow-lg shadow-purple-500/30 transition-all duration-200 transform hover:scale-[1.02]"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                {{ t('first_run.create_admin_button') }}
            </Link>
        </div>
    </Modal>
</template>
