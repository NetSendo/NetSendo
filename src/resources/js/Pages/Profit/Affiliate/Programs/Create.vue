<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const form = useForm({
    name: '',
    terms_text: '',
    cookie_days: 30,
    currency: 'PLN',
    default_commission_percent: 10,
    auto_approve_affiliates: false,
});

const submit = () => {
    form.post(route('affiliate.programs.store'));
};

const currencies = ['PLN', 'EUR', 'USD', 'GBP', 'CHF', 'CZK'];
</script>

<template>
    <Head :title="$t('affiliate.create_program')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('affiliate.programs.index')"
                    class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {{ $t('affiliate.create_program') }}
                </h2>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-200 dark:border-slate-700 p-6 space-y-6">
                    <!-- Program Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ $t('affiliate.program_name') }} *
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                            :placeholder="$t('affiliate.program_name_placeholder')"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <!-- Settings Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ $t('affiliate.cookie_days') }}
                            </label>
                            <input
                                v-model="form.cookie_days"
                                type="number"
                                min="1"
                                max="365"
                                class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ $t('affiliate.currency') }}
                            </label>
                            <select
                                v-model="form.currency"
                                class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option v-for="c in currencies" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                {{ $t('affiliate.default_commission') }} (%)
                            </label>
                            <input
                                v-model="form.default_commission_percent"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>

                    <!-- Terms -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            {{ $t('affiliate.terms_text') }}
                        </label>
                        <textarea
                            v-model="form.terms_text"
                            rows="4"
                            class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                            :placeholder="$t('affiliate.terms_placeholder')"
                        ></textarea>
                    </div>

                    <!-- Auto Approve -->
                    <div class="flex items-center gap-3">
                        <input
                            id="auto_approve"
                            v-model="form.auto_approve_affiliates"
                            type="checkbox"
                            class="rounded border-gray-300 dark:border-slate-600 text-indigo-600 focus:ring-indigo-500"
                        />
                        <label for="auto_approve" class="text-sm text-gray-700 dark:text-gray-300">
                            {{ $t('affiliate.auto_approve_affiliates') }}
                        </label>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200 dark:border-slate-700">
                        <Link
                            :href="route('affiliate.programs.index')"
                            class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg transition-colors"
                        >
                            {{ $t('common.cancel') }}
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-6 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                        >
                            {{ $t('affiliate.create_program') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
