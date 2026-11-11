<script setup>
import GuestLayout from "@/Layouts/GuestLayout.vue";
import AuthHeading from "@/Components/Auth/AuthHeading.vue";
import AuthField from "@/Components/Auth/AuthField.vue";
import AuthSubmit from "@/Components/Auth/AuthSubmit.vue";
import AuthNotice from "@/Components/Auth/AuthNotice.vue";
import { Head, useForm } from "@inertiajs/vue3";

const form = useForm({
    password: "",
});

const submit = () => {
    form.post(route("password.confirm"), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('auth.confirm_password.title')" />

        <AuthHeading
            :title="$t('auth.confirm_password.title')"
            :subtitle="$t('auth.confirm_password.intro')"
        />

        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <AuthField
                id="password"
                v-model="form.password"
                type="password"
                :label="$t('auth.password')"
                :error="form.errors.password"
                required
                autofocus
                autocomplete="current-password"
            />

            <AuthSubmit :processing="form.processing">
                {{ $t("auth.confirm_password.submit") }}
            </AuthSubmit>
        </form>
    </GuestLayout>
</template>
