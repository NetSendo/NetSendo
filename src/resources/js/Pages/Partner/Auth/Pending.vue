<script setup>
import { Head, Link } from "@inertiajs/vue3";

const props = defineProps({
    program: Object,
});
</script>

<template>
    <Head :title="`Application Pending - ${program.name}`" />

    <div
        class="min-h-screen flex items-center justify-center bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-500 p-4"
    >
        <div class="w-full max-w-lg">
            <div
                class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl p-8 text-center"
            >
                <!-- Success Icon -->
                <div
                    class="mx-auto w-20 h-20 bg-gradient-to-r from-amber-400 to-orange-500 rounded-full flex items-center justify-center mb-6"
                >
                    <svg
                        class="w-10 h-10 text-white"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                </div>

                <h2
                    class="text-2xl font-bold text-gray-900 dark:text-white mb-2"
                >
                    Application Submitted!
                </h2>

                <p class="text-gray-600 dark:text-gray-400 mb-6">
                    Thank you for applying to join
                    <strong class="text-indigo-600 dark:text-indigo-400">{{
                        program.name
                    }}</strong>
                    affiliate program.
                </p>

                <div
                    class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4 mb-6"
                >
                    <div class="flex items-start gap-3">
                        <svg
                            class="w-5 h-5 text-amber-600 dark:text-amber-400 mt-0.5 flex-shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                        <div class="text-left">
                            <h3
                                class="text-sm font-semibold text-amber-800 dark:text-amber-300"
                            >
                                Pending Review
                            </h3>
                            <p
                                class="text-sm text-amber-700 dark:text-amber-400 mt-1"
                            >
                                Your application is being reviewed by the
                                program administrator. You'll receive an email
                                notification once your account has been
                                approved.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="text-sm text-gray-500 dark:text-gray-400">
                    <p>What happens next?</p>
                    <ul class="mt-2 space-y-1 text-left list-disc list-inside">
                        <li>We'll review your application</li>
                        <li>You'll receive an email when approved</li>
                        <li>Then you can log in and start earning</li>
                    </ul>
                </div>

                <div
                    class="mt-8 pt-6 border-t border-gray-200 dark:border-slate-700"
                >
                    <Link
                        :href="route('partner.login', program.slug)"
                        class="inline-flex items-center gap-2 text-indigo-600 dark:text-indigo-400 hover:underline font-medium"
                    >
                        <svg
                            class="w-4 h-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18"
                            />
                        </svg>
                        Back to Login
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
