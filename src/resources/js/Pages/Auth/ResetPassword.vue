<script setup>
import GuestLayout from "@/Layouts/GuestLayout.vue";
import AuthHeading from "@/Components/Auth/AuthHeading.vue";
import AuthField from "@/Components/Auth/AuthField.vue";
import AuthSubmit from "@/Components/Auth/AuthSubmit.vue";
import AuthNotice from "@/Components/Auth/AuthNotice.vue";
import { Head, useForm } from "@inertiajs/vue3";

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: "",
    password_confirmation: "",
});

const submit = () => {
    form.post(route("password.store"), {
        onFinish: () => form.reset("password", "password_confirmation"),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('auth.reset_password.title')" />

        <AuthHeading :title="$t('auth.reset_password.title')" />

        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <AuthField
                id="email"
                v-model="form.email"
                type="email"
                :label="$t('auth.email')"
                :error="form.errors.email"
                required
                autocomplete="username"
            />

            <AuthField
                id="password"
                v-model="form.password"
                type="password"
                :label="$t('auth.password')"
                :error="form.errors.password"
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

            <AuthSubmit :processing="form.processing">
                {{ $t("auth.reset_password.submit") }}
            </AuthSubmit>
        </form>
    </GuestLayout>
</template>
