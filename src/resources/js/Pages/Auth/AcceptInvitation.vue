<script setup>
import GuestLayout from "@/Layouts/GuestLayout.vue";
import AuthHeading from "@/Components/Auth/AuthHeading.vue";
import AuthField from "@/Components/Auth/AuthField.vue";
import AuthSubmit from "@/Components/Auth/AuthSubmit.vue";
import AuthNotice from "@/Components/Auth/AuthNotice.vue";
import { Head, useForm } from "@inertiajs/vue3";

const props = defineProps({
    invitation: Object,
    token: String,
});

const form = useForm({
    password: "",
    password_confirmation: "",
});

const submit = () => {
    form.post(route("invitation.complete", props.token), {
        onFinish: () => form.reset("password", "password_confirmation"),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('auth.invitation.title')" />

        <AuthHeading
            :title="$t('auth.invitation.title')"
            :subtitle="$t('auth.invitation.intro', { name: invitation.admin_name })"
        />

        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <AuthField
                id="invitation_name"
                :model-value="invitation.name"
                :label="$t('auth.register.name_label')"
                disabled
            />

            <AuthField
                id="invitation_email"
                :model-value="invitation.email"
                type="email"
                :label="$t('auth.register.email_label')"
                autocomplete="username"
                disabled
            />

            <AuthField
                id="password"
                v-model="form.password"
                type="password"
                :label="$t('auth.invitation.set_password')"
                :error="form.errors.password"
                :hint="$t('auth.register.password_placeholder')"
                required
                autofocus
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
                {{ $t("auth.invitation.submit") }}
            </AuthSubmit>
        </form>
    </GuestLayout>
</template>
