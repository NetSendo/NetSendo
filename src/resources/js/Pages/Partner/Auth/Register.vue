<script setup>
import { Head, Link, useForm } from "@inertiajs/vue3";

const props = defineProps({
    program: Object,
});

const form = useForm({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    company_name: "",
    website: "",
    accept_terms: false,
});

const submit = () => {
    form.post(route("partner.register.store", props.program.slug));
};
</script>

<template>
    <Head :title="`Join - ${program.name}`" />

    <div
        class="min-h-screen flex items-center justify-center bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-500 p-4"
    >
        <div class="w-full max-w-lg">
            <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl p-8">
                <div class="text-center mb-6">
                    <h2
                        class="text-2xl font-bold text-gray-900 dark:text-white"
                    >
                        Join {{ program.name }}
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 mt-1">
                        Become an affiliate partner
                    </p>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2 sm:col-span-1">
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                                >Your Name *</label
                            >
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            />
                            <p
                                v-if="form.errors.name"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>
                        <div class="col-span-2 sm:col-span-1">
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                                >Company (Optional)</label
                            >
                            <input
                                v-model="form.company_name"
                                type="text"
                                class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            />
                        </div>
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                            >Email *</label
                        >
                        <input
                            v-model="form.email"
                            type="email"
                            required
                            class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                        />
                        <p
                            v-if="form.errors.email"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                            >Website (Optional)</label
                        >
                        <input
                            v-model="form.website"
                            type="url"
                            class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            placeholder="https://"
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                                >Password *</label
                            >
                            <input
                                v-model="form.password"
                                type="password"
                                required
                                class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            />
                            <p
                                v-if="form.errors.password"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ form.errors.password }}
                            </p>
                        </div>
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                                >Confirm *</label
                            >
                            <input
                                v-model="form.password_confirmation"
                                type="password"
                                required
                                class="w-full rounded-lg border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            />
                        </div>
                    </div>

                    <label class="flex items-start gap-2">
                        <input
                            v-model="form.accept_terms"
                            type="checkbox"
                            required
                            class="mt-1 rounded border-gray-300 text-indigo-600"
                        />
                        <span class="text-sm text-gray-600 dark:text-gray-300">
                            I agree to the
                            <a href="#" class="text-indigo-600 hover:underline"
                                >terms and conditions</a
                            >
                            of this affiliate program
                        </span>
                    </label>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-3 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-semibold rounded-lg hover:from-indigo-700 hover:to-purple-700 disabled:opacity-50 transition-all"
                    >
                        Create Account
                    </button>
                </form>

                <p
                    class="mt-6 text-center text-sm text-gray-600 dark:text-gray-400"
                >
                    Already have an account?
                    <Link
                        :href="route('partner.login', program.slug)"
                        class="text-indigo-600 dark:text-indigo-400 hover:underline font-medium"
                    >
                        Sign In
                    </Link>
                </p>
            </div>
        </div>
    </div>
</template>
