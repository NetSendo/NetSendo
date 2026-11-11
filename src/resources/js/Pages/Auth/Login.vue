<script setup>
import { ref } from "vue";
import GuestLayout from "@/Layouts/GuestLayout.vue";
import Modal from "@/Components/Modal.vue";
import AuthHeading from "@/Components/Auth/AuthHeading.vue";
import AuthField from "@/Components/Auth/AuthField.vue";
import AuthSubmit from "@/Components/Auth/AuthSubmit.vue";
import AuthNotice from "@/Components/Auth/AuthNotice.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    // No account exists yet: there is nobody to sign in, the next step is the administrator account
    isFirstUser: {
        type: Boolean,
        default: false,
    },
});

const form = useForm({
    email: "",
    password: "",
    remember: false,
});

const resetForm = useForm({
    email: "",
});

const showResetModal = ref(false);
const resetStatus = ref(null);

const submitReset = () => {
    resetForm.post(route("password.webhook-reset"), {
        onSuccess: () => {
            resetStatus.value = t("auth.webhook_reset.success");
            setTimeout(() => {
                showResetModal.value = false;
                resetForm.reset();
                resetStatus.value = null;
            }, 3000);
        },
    });
};

const submit = () => {
    form.post(route("login"), {
        onFinish: () => form.reset("password"),
    });
};
</script>

<template>
    <GuestLayout>
        <Head :title="$t('auth.login')" />

        <!-- First run: the installation has no account, so it offers to create one -->
        <template v-if="isFirstUser">
            <AuthHeading
                :eyebrow="$t('auth.first_run.eyebrow')"
                :title="$t('auth.first_run.title')"
                :subtitle="$t('first_run.modal_description')"
            />

            <p class="text-sm font-medium text-slate-800 dark:text-slate-200">
                {{ $t("first_run.features_heading") }}
            </p>
            <ul class="mb-8 mt-3 flex flex-col gap-2 text-sm text-slate-600 dark:text-slate-400">
                <li v-for="n in 3" :key="n" class="flex items-start gap-2.5">
                    <svg
                        aria-hidden="true"
                        class="mt-0.5 h-4 w-4 shrink-0 text-emerald-700 dark:text-emerald-400"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="3"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        viewBox="0 0 24 24"
                    >
                        <path d="M5 13l4 4L19 7" />
                    </svg>
                    {{ $t(`first_run.feature_${n}`) }}
                </li>
            </ul>

            <Link
                :href="route('register')"
                class="flex min-h-11 w-full items-center justify-center rounded-xl bg-indigo-600 px-4 text-sm font-semibold text-white transition-colors hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
            >
                {{ $t("first_run.create_admin_button") }}
            </Link>
        </template>

        <template v-else>
            <AuthHeading :title="$t('auth.welcome_back')" :subtitle="$t('auth.login_to_account')" />

            <AuthNotice v-if="status">{{ status }}</AuthNotice>
            <AuthNotice v-if="$page.props.flash?.error" tone="warning">
                {{ $page.props.flash.error }}
            </AuthNotice>

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
                    placeholder="your@email.com"
                />

                <AuthField
                    id="password"
                    v-model="form.password"
                    type="password"
                    :label="$t('auth.password')"
                    :error="form.errors.password"
                    required
                    autocomplete="current-password"
                />

                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                    <label class="flex min-h-9 cursor-pointer items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                        <input
                            v-model="form.remember"
                            type="checkbox"
                            class="h-4 w-4 accent-indigo-600"
                        />
                        {{ $t("auth.remember_me") }}
                    </label>

                    <button
                        v-if="canResetPassword"
                        type="button"
                        class="min-h-9 text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                        @click="showResetModal = true"
                    >
                        {{ $t("auth.forgot_password_link") }}
                    </button>
                </div>

                <AuthSubmit :processing="form.processing" :processing-label="$t('common.loading')">
                    {{ $t("auth.login") }}
                </AuthSubmit>
            </form>
        </template>

        <!-- Password reset by e-mail -->
        <Modal :show="showResetModal" max-width="md" @close="showResetModal = false">
            <form class="flex flex-col gap-4 p-6" @submit.prevent="submitReset">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                    {{ $t("auth.webhook_reset.title") }}
                </h2>
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    {{ $t("auth.webhook_reset.content") }}
                </p>

                <AuthField
                    id="reset_email"
                    v-model="resetForm.email"
                    type="email"
                    :label="$t('auth.email')"
                    :error="resetForm.errors.email"
                    autocomplete="username"
                    placeholder="your@email.com"
                />
                <p v-if="resetStatus" role="status" class="text-sm text-emerald-700 dark:text-emerald-300">
                    {{ resetStatus }}
                </p>

                <div class="flex justify-end gap-3">
                    <button
                        type="button"
                        class="min-h-10 rounded-xl border border-slate-300 px-4 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700/60"
                        @click="showResetModal = false"
                    >
                        {{ $t("common.cancel") }}
                    </button>
                    <button
                        type="submit"
                        :disabled="resetForm.processing"
                        class="min-h-10 rounded-xl bg-indigo-600 px-4 text-sm font-semibold text-white transition-colors hover:bg-indigo-500 disabled:opacity-60"
                    >
                        {{
                            resetForm.processing
                                ? $t("auth.webhook_reset.processing")
                                : $t("auth.webhook_reset.button")
                        }}
                    </button>
                </div>
            </form>
        </Modal>
    </GuestLayout>
</template>
