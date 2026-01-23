<script setup>
import GuestLayout from "@/Layouts/GuestLayout.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { computed } from "vue";

const props = defineProps({
    isFirstUser: {
        type: Boolean,
        default: true,
    },
    referralCode: {
        type: String,
        default: null,
    },
    referralAffiliateName: {
        type: String,
        default: null,
    },
});

const form = useForm({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    referral_code: props.referralCode || "",
});

const hasReferral = computed(
    () => props.referralCode && props.referralAffiliateName,
);

const submit = () => {
    form.post(route("register"), {
        onFinish: () => form.reset("password", "password_confirmation"),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('auth.register.title')" />

        <!-- Referral Banner -->
        <div
            v-if="hasReferral"
            class="mb-4 -mt-2 py-3 px-4 bg-gradient-to-r from-purple-500/20 to-indigo-500/20 border border-purple-400/30 rounded-xl"
        >
            <div class="flex items-center gap-2 text-purple-200 text-sm">
                <svg
                    class="w-5 h-5 text-purple-400 flex-shrink-0"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
                    />
                </svg>
                <span
                    >{{ $t("affiliate.referred_by") }}:
                    <strong class="text-purple-300">{{
                        referralAffiliateName
                    }}</strong></span
                >
            </div>
        </div>

        <!-- Header -->
        <div class="text-center mb-6">
            <div
                class="inline-flex items-center justify-center w-14 h-14 rounded-full mb-4 shadow-lg"
                :class="
                    isFirstUser
                        ? 'bg-gradient-to-br from-amber-500 to-orange-600 shadow-amber-500/25'
                        : 'bg-gradient-to-br from-emerald-500 to-teal-600 shadow-emerald-500/25'
                "
            >
                <svg
                    v-if="isFirstUser"
                    class="w-7 h-7 text-white"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                    />
                </svg>
                <svg
                    v-else
                    class="w-7 h-7 text-white"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"
                    />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-white mb-1">
                {{
                    isFirstUser
                        ? $t("auth.register.admin_title")
                        : $t("auth.register.user_title")
                }}
            </h1>
            <p class="text-white/60 text-sm">
                {{
                    isFirstUser
                        ? $t("auth.register.admin_subtitle")
                        : $t("auth.register.user_subtitle")
                }}
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <!-- Hidden referral code -->
            <input type="hidden" v-model="form.referral_code" />

            <!-- Name -->
            <div>
                <label
                    for="name"
                    class="block text-sm font-medium text-white/80 mb-2"
                >
                    {{ $t("auth.register.name_label") }}
                </label>
                <div class="relative">
                    <div
                        class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"
                    >
                        <svg
                            class="h-5 w-5 text-white/40"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                            />
                        </svg>
                    </div>
                    <input
                        id="name"
                        type="text"
                        v-model="form.name"
                        required
                        autofocus
                        autocomplete="name"
                        class="w-full pl-10 pr-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500/50 transition-all duration-200"
                        :placeholder="$t('auth.register.name_placeholder')"
                    />
                </div>
                <p v-if="form.errors.name" class="mt-2 text-sm text-red-400">
                    {{ form.errors.name }}
                </p>
            </div>

            <!-- Email -->
            <div>
                <label
                    for="email"
                    class="block text-sm font-medium text-white/80 mb-2"
                >
                    {{ $t("auth.register.email_label") }}
                </label>
                <div class="relative">
                    <div
                        class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"
                    >
                        <svg
                            class="h-5 w-5 text-white/40"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"
                            />
                        </svg>
                    </div>
                    <input
                        id="email"
                        type="email"
                        v-model="form.email"
                        required
                        autocomplete="username"
                        class="w-full pl-10 pr-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500/50 transition-all duration-200"
                        :placeholder="$t('auth.register.email_placeholder')"
                    />
                </div>
                <p v-if="form.errors.email" class="mt-2 text-sm text-red-400">
                    {{ form.errors.email }}
                </p>
            </div>

            <!-- Password -->
            <div>
                <label
                    for="password"
                    class="block text-sm font-medium text-white/80 mb-2"
                >
                    {{ $t("auth.register.password_label") }}
                </label>
                <div class="relative">
                    <div
                        class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"
                    >
                        <svg
                            class="h-5 w-5 text-white/40"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                            />
                        </svg>
                    </div>
                    <input
                        id="password"
                        type="password"
                        v-model="form.password"
                        required
                        autocomplete="new-password"
                        class="w-full pl-10 pr-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500/50 transition-all duration-200"
                        :placeholder="$t('auth.register.password_placeholder')"
                    />
                </div>
                <p
                    v-if="form.errors.password"
                    class="mt-2 text-sm text-red-400"
                >
                    {{ form.errors.password }}
                </p>
            </div>

            <!-- Confirm Password -->
            <div>
                <label
                    for="password_confirmation"
                    class="block text-sm font-medium text-white/80 mb-2"
                >
                    {{ $t("auth.register.password_confirmation_label") }}
                </label>
                <div class="relative">
                    <div
                        class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"
                    >
                        <svg
                            class="h-5 w-5 text-white/40"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                            />
                        </svg>
                    </div>
                    <input
                        id="password_confirmation"
                        type="password"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                        class="w-full pl-10 pr-4 py-3 bg-white/10 border border-white/20 rounded-xl text-white placeholder-white/40 focus:outline-none focus:ring-2 focus:ring-purple-500/50 focus:border-purple-500/50 transition-all duration-200"
                        :placeholder="
                            $t(
                                'auth.register.password_confirmation_placeholder',
                            )
                        "
                    />
                </div>
                <p
                    v-if="form.errors.password_confirmation"
                    class="mt-2 text-sm text-red-400"
                >
                    {{ form.errors.password_confirmation }}
                </p>
            </div>

            <!-- Submit button -->
            <button
                type="submit"
                :disabled="form.processing"
                class="w-full relative group mt-2"
            >
                <div
                    class="absolute -inset-0.5 bg-gradient-to-r from-emerald-600 to-teal-600 rounded-xl blur opacity-60 group-hover:opacity-100 transition duration-300"
                    :class="{ 'opacity-30': form.processing }"
                ></div>
                <div
                    class="relative flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-emerald-600 to-teal-600 rounded-xl text-white font-semibold transition-all duration-200"
                    :class="{
                        'opacity-50 cursor-not-allowed': form.processing,
                    }"
                >
                    <svg
                        v-if="form.processing"
                        class="animate-spin h-5 w-5"
                        fill="none"
                        viewBox="0 0 24 24"
                    >
                        <circle
                            class="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            stroke-width="4"
                        ></circle>
                        <path
                            class="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        ></path>
                    </svg>
                    <span>{{
                        form.processing
                            ? $t("auth.register.submitting")
                            : $t("auth.register.submit_button")
                    }}</span>
                </div>
            </button>
        </form>

        <!-- Login link -->
        <div class="mt-6 pt-6 border-t border-white/10 text-center">
            <p class="text-white/50 text-sm">
                {{ $t("auth.register.already_registered") }}
                <Link
                    :href="route('login')"
                    class="text-purple-400 hover:text-purple-300 font-medium transition-colors"
                >
                    {{ $t("auth.register.login_link") }}
                </Link>
            </p>
        </div>
    </GuestLayout>
</template>
