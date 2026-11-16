<script setup>
import GuestLayout from "@/Layouts/GuestLayout.vue";
import AuthHeading from "@/Components/Auth/AuthHeading.vue";
import AuthField from "@/Components/Auth/AuthField.vue";
import AuthSubmit from "@/Components/Auth/AuthSubmit.vue";
import AuthNotice from "@/Components/Auth/AuthNotice.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: "",
});

const submit = () => {
    form.post(route("password.email"));
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('auth.forgot_password.title')" />

        <AuthHeading
            :title="$t('auth.forgot_password.title')"
            :subtitle="$t('auth.forgot_password.intro')"
        />

        <AuthNotice v-if="status">{{ status }}</AuthNotice>

        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <AuthField
                id="email"
                v-model="form.email"
                type="email"
                :label="$t('auth.email')"
                :error="form.errors.email"
                required
                autofocus
                autocomplete="username"
            />

            <AuthSubmit :processing="form.processing">
                {{ $t("auth.forgot_password.submit") }}
            </AuthSubmit>
        </form>

        <p class="mt-6 text-center text-sm">
            <Link
                :href="route('login')"
                class="font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
            >
                {{ $t("auth.back_to_login") }}
            </Link>
        </p>
    </GuestLayout>
</template>
