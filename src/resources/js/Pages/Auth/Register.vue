<script setup>
import GuestLayout from "@/Layouts/GuestLayout.vue";
import AuthHeading from "@/Components/Auth/AuthHeading.vue";
import AuthField from "@/Components/Auth/AuthField.vue";
import AuthSubmit from "@/Components/Auth/AuthSubmit.vue";
import AuthNotice from "@/Components/Auth/AuthNotice.vue";
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

        <AuthHeading
            :eyebrow="isFirstUser ? $t('auth.first_run.eyebrow') : ''"
            :title="isFirstUser ? $t('auth.register.admin_title') : $t('auth.register.user_title')"
            :subtitle="
                isFirstUser ? $t('auth.register.admin_subtitle') : $t('auth.register.user_subtitle')
            "
        />

        <AuthNotice v-if="hasReferral">
            {{ $t("affiliate.referred_by") }}: <strong>{{ referralAffiliateName }}</strong>
        </AuthNotice>

        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <input v-model="form.referral_code" type="hidden" />

            <AuthField
                id="name"
                v-model="form.name"
                :label="$t('auth.register.name_label')"
                :error="form.errors.name"
                required
                autofocus
                autocomplete="name"
                :placeholder="$t('auth.register.name_placeholder')"
            />

            <AuthField
                id="email"
                v-model="form.email"
                type="email"
                :label="$t('auth.register.email_label')"
                :error="form.errors.email"
                :hint="isFirstUser ? $t('auth.first_run.email_hint') : ''"
                required
                autocomplete="username"
                :placeholder="$t('auth.register.email_placeholder')"
            />

            <AuthField
                id="password"
                v-model="form.password"
                type="password"
                :label="$t('auth.register.password_label')"
                :error="form.errors.password"
                :hint="$t('auth.register.password_placeholder')"
                required
                autocomplete="new-password"
            />

            <AuthField
                id="password_confirmation"
                v-model="form.password_confirmation"
                type="password"
                :label="$t('auth.register.password_confirmation_label')"
                :error="form.errors.password_confirmation"
                required
                autocomplete="new-password"
            />

            <AuthSubmit :processing="form.processing" :processing-label="$t('auth.register.submitting')">
                {{ $t("auth.register.submit_button") }}
            </AuthSubmit>
        </form>

        <!-- With no account yet there is nothing to sign in to -->
        <p v-if="!isFirstUser" class="mt-6 text-center text-sm text-slate-600 dark:text-slate-400">
            {{ $t("auth.register.already_registered") }}
            <Link
                :href="route('login')"
                class="font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
            >
                {{ $t("auth.register.login_link") }}
            </Link>
        </p>
    </GuestLayout>
</template>
