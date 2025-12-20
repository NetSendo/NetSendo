<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

defineProps({
    twoFactorEnabled: Boolean,
});

const page = usePage();
</script>

<template>
    <Head :title="$t('profile.two_factor.title_full')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('profile.edit')" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {{ $t('profile.two_factor.title_full') }}
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <!-- Flash messages -->
                <div v-if="$page.props.flash?.success" class="mb-6 rounded-lg bg-green-100 p-4 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                    {{ $page.props.flash.success }}
                </div>

                <div class="overflow-hidden bg-white shadow-sm rounded-xl dark:bg-gray-800">
                    <div class="p-6">
                        <!-- Header icon -->
                        <div class="flex items-center gap-4 mb-6">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full"
                                :class="twoFactorEnabled ? 'bg-green-100 dark:bg-green-900/30' : 'bg-gray-100 dark:bg-gray-700'">
                                <svg class="w-6 h-6" :class="twoFactorEnabled ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                    {{ twoFactorEnabled ? $t('profile.two_factor.status_enabled') : $t('profile.two_factor.status_disabled') }}
                                </h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ twoFactorEnabled ? $t('profile.two_factor.enabled_subtitle') : $t('profile.two_factor.disabled_subtitle') }}
                                </p>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="mb-6 p-4 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                {{ $t('profile.two_factor.intro') }}
                            </p>
                        </div>

                        <!-- Action buttons -->
                        <div v-if="!twoFactorEnabled">
                            <Link
                                :href="route('profile.2fa.enable')"
                                class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-semibold rounded-lg shadow-lg hover:shadow-xl transition-all"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                {{ $t('profile.two_factor.enable_button') }}
                            </Link>
                        </div>

                        <div v-else class="space-y-4">
                            <div class="flex items-center gap-3 p-4 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
                                <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="text-green-700 dark:text-green-300 font-medium">
                                    {{ $t('profile.two_factor.active_status') }}
                                </span>
                            </div>

                            <form @submit.prevent="disableSubmit" class="mt-4">
                                <button
                                    type="button"
                                    @click="showDisableModal = true"
                                    class="inline-flex items-center gap-2 px-4 py-2 text-red-600 dark:text-red-400 border border-red-300 dark:border-red-700 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                    </svg>
                                    {{ $t('profile.two_factor.disable_button') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script>
export default {
    data() {
        return {
            showDisableModal: false,
        };
    },
};
</script>
