<script setup>
import GuestLayout from "@/Layouts/GuestLayout.vue";
import AuthHeading from "@/Components/Auth/AuthHeading.vue";
import AuthField from "@/Components/Auth/AuthField.vue";
import AuthSubmit from "@/Components/Auth/AuthSubmit.vue";
import AuthNotice from "@/Components/Auth/AuthNotice.vue";
import { Head, useForm } from "@inertiajs/vue3";

const form = useForm({
    code: "",
});

const submit = () => {
    form.post(route("2fa.verify"), {
        onFinish: () => form.reset("code"),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('auth.two_factor_verification')" />

        <AuthHeading
            :title="$t('auth.two_factor_verification')"
            :subtitle="$t('auth.enter_code_from_app')"
        />

        <form class="flex flex-col gap-5" @submit.prevent="submit">
            <AuthField
                id="code"
                v-model="form.code"
                :label="$t('auth.code')"
                :error="form.errors.code"
                required
                autofocus
                inputmode="numeric"
                autocomplete="one-time-code"
                placeholder="000000"
                class="text-center text-lg tracking-[0.4em] tabular-nums"
            />

            <AuthSubmit :processing="form.processing" :processing-label="$t('auth.verifying')">
                {{ $t("auth.confirm") }}
            </AuthSubmit>
        </form>
    </GuestLayout>
</template>
