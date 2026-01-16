<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    timezones: {
        type: Array,
        default: () => [],
    },
    currencies: {
        type: Object,
        default: () => ({}),
    }
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
    timezone: user.timezone || 'UTC',
    default_currency: user.settings?.default_currency || 'EUR',
    time_format: user.settings?.time_format || '24',
});

const timeFormats = [
    { value: '24', label: '24h (14:30)' },
    { value: '12', label: '12h (2:30 PM)' },
];

const detectTimezone = () => {
    const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
    if (tz) {
        form.timezone = tz;
    }
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ $t('profile.information.title') }}
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ $t('profile.information.description') }}
            </p>
        </header>

        <form
            @submit.prevent="form.patch(route('profile.update'))"
            class="mt-6 space-y-6"
        >
            <div>
                <InputLabel for="name" :value="$t('profile.information.name')" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" :value="$t('profile.information.email')" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="timezone" :value="$t('profile.information.timezone')" />
                <div class="flex gap-2">
                    <select
                        id="timezone"
                        v-model="form.timezone"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
                    >
                        <option v-for="tz in timezones" :key="tz" :value="tz">
                            {{ tz }}
                        </option>
                    </select>
                    <button
                        type="button"
                        @click="detectTimezone"
                        class="mt-1 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        :title="$t('profile.information.detect_timezone_title')"
                    >
                        {{ $t('profile.information.detect_timezone') }}
                    </button>
                </div>
                <InputError class="mt-2" :message="form.errors.timezone" />
            </div>

            <div>
                <InputLabel for="default_currency" :value="$t('profile.information.default_currency')" />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('profile.information.default_currency_description') }}</p>
                <select
                    id="default_currency"
                    v-model="form.default_currency"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
                >
                    <option v-for="(name, code) in currencies" :key="code" :value="code">
                        {{ code }} - {{ name }}
                    </option>
                </select>
                <InputError class="mt-2" :message="form.errors.default_currency" />
            </div>

            <div>
                <InputLabel for="time_format" :value="$t('profile.information.time_format')" />
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t('profile.information.time_format_description') }}</p>
                <select
                    id="time_format"
                    v-model="form.time_format"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
                >
                    <option v-for="tf in timeFormats" :key="tf.value" :value="tf.value">
                        {{ tf.label }}
                    </option>
                </select>
                <InputError class="mt-2" :message="form.errors.time_format" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-gray-800 dark:text-gray-200">
                    {{ $t('profile.information.email_unverified') }}
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-100 dark:focus:ring-offset-gray-800"
                    >
                        {{ $t('profile.information.resend_verification') }}
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600 dark:text-green-400"
                >
                    {{ $t('profile.information.verification_link_sent') }}
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">{{ $t('profile.information.saved').replace('.', '') }}</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-gray-600 dark:text-gray-400"
                    >
                        {{ $t('profile.information.saved') }}
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
