<script setup>
import GuestLayout from "@/Layouts/GuestLayout.vue";
import AuthHeading from "@/Components/Auth/AuthHeading.vue";
import AuthField from "@/Components/Auth/AuthField.vue";
import AuthSubmit from "@/Components/Auth/AuthSubmit.vue";
import AuthNotice from "@/Components/Auth/AuthNotice.vue";
import { computed } from "vue";
import { Head, Link, useForm } from "@inertiajs/vue3";

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route("verification.send"));
};

const verificationLinkSent = computed(() => props.status === "verification-link-sent");
</script>

<template>
    <GuestLayout>
        <Head :title="$t('auth.verify_email.title')" />

        <AuthHeading
            :title="$t('auth.verify_email.title')"
            :subtitle="$t('auth.verify_email.intro')"
        />

        <AuthNotice v-if="verificationLinkSent">
            {{ $t("auth.verify_email.link_sent") }}
        </AuthNotice>

        <form class="flex flex-col gap-4" @submit.prevent="submit">
            <AuthSubmit :processing="form.processing">
                {{ $t("auth.verify_email.submit") }}
            </AuthSubmit>

            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="min-h-10 text-sm font-semibold text-slate-700 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white"
            >
                {{ $t("auth.verify_email.logout") }}
            </Link>
        </form>
    </GuestLayout>
</template>
