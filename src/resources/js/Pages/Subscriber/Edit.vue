<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import PhoneInput from "@/Components/PhoneInput.vue";

const props = defineProps({
    subscriber: {
        type: Object,
        required: true,
    },
    lists: Array,
    customFields: Array,
    availableLanguages: {
        type: Object,
        default: () => ({}),
    },
    timezones: {
        type: Array,
        default: () => [],
    },
});

// Filter for list type
const listTypeFilter = ref("all");

// Filtered lists based on selected type
const filteredLists = computed(() => {
    if (listTypeFilter.value === "all") return props.lists;
    return props.lists.filter((list) => list.type === listTypeFilter.value);
});

// Check which types are selected
const selectedListTypes = computed(() => {
    const types = new Set();
    form.contact_list_ids.forEach((id) => {
        const list = props.lists.find((l) => l.id === id);
        if (list) types.add(list.type);
    });
    return types;
});

// Dynamic required fields based on selected lists
const isEmailRequired = computed(() => {
    if (form.contact_list_ids.length === 0) return false;
    return selectedListTypes.value.has("email");
});

const isPhoneRequired = computed(() => {
    if (form.contact_list_ids.length === 0) return false;
    return selectedListTypes.value.has("sms");
});

const form = useForm({
    email: props.subscriber.email || "",
    first_name: props.subscriber.first_name || "",
    last_name: props.subscriber.last_name || "",
    phone: props.subscriber.phone || "",
    gender: props.subscriber.gender || "",
    language: props.subscriber.language || "",
    timezone: props.subscriber.timezone || "",
    contact_list_ids: props.subscriber.contact_list_ids || [],
    status: props.subscriber.status || "active",
    custom_fields: props.subscriber.custom_fields || {},
});

const submit = () => {
    form.put(route("subscribers.update", props.subscriber.id));
};
</script>

<template>
    <Head
        :title="`${$t('subscribers.edit_title')}: ${
            subscriber.email || subscriber.phone
        }`"
    />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('subscribers.index')"
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-slate-500 shadow-sm transition-all hover:bg-slate-50 hover:text-slate-700 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700 dark:hover:text-slate-300"
                >
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"
                        />
                    </svg>
                </Link>
                <div>
                    <h1
                        class="text-2xl font-bold text-slate-900 dark:text-white"
                    >
                        {{ $t("subscribers.edit_title") }}
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{
                            $t("subscribers.edit_subtitle", {
                                email: subscriber.email || subscriber.phone,
                            })
                        }}
                    </p>
                </div>
            </div>
        </template>

        <div class="flex justify-center">
            <div
                class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-900 lg:p-8"
            >
                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <label
                            for="contact_list_ids"
                            class="mb-2 block text-sm font-medium text-slate-900 dark:text-white"
                        >
                            {{ $t("subscribers.fields.list") }}
                            <span class="text-red-500">*</span>
                        </label>

                        <!-- List Type Filter Buttons -->
                        <div class="mb-3 flex gap-2">
                            <button
                                type="button"
                                @click="listTypeFilter = 'all'"
                                :class="[
                                    'rounded-lg px-3 py-1.5 text-xs font-medium transition-colors',
                                    listTypeFilter === 'all'
                                        ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700',
                                ]"
                            >
                                {{ $t("common.all") || "Wszystkie" }}
                            </button>
                            <button
                                type="button"
                                @click="listTypeFilter = 'email'"
                                :class="[
                                    'rounded-lg px-3 py-1.5 text-xs font-medium transition-colors',
                                    listTypeFilter === 'email'
                                        ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700',
                                ]"
                            >
                                📧 Email
                            </button>
                            <button
                                type="button"
                                @click="listTypeFilter = 'sms'"
                                :class="[
                                    'rounded-lg px-3 py-1.5 text-xs font-medium transition-colors',
                                    listTypeFilter === 'sms'
                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/50 dark:text-green-300'
                                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700',
                                ]"
                            >
                                📱 SMS
                            </button>
                        </div>

                        <select
                            id="contact_list_ids"
                            v-model="form.contact_list_ids"
                            multiple
                            class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:focus:border-indigo-400 dark:focus:bg-slate-800 min-h-[120px]"
                            required
                        >
                            <option
                                v-for="list in filteredLists"
                                :key="list.id"
                                :value="list.id"
                            >
                                {{ list.name }} ({{
                                    list.type === "sms" ? "SMS" : "Email"
                                }})
                            </option>
                        </select>
                        <p
                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                        >
                            {{
                                $t(
                                    "subscribers.hold_ctrl_to_select_multiple",
                                ) || "Przytrzymaj Ctrl/Cmd aby wybrać wiele"
                            }}
                        </p>
                        <p
                            v-if="form.errors.contact_list_ids"
                            class="mt-2 text-sm text-red-600 dark:text-red-400"
                        >
                            {{ form.errors.contact_list_ids }}
                        </p>
                    </div>

                    <!-- Info about required fields -->
                    <div
                        v-if="form.contact_list_ids.length > 0"
                        class="rounded-lg bg-slate-50 p-3 text-sm dark:bg-slate-800/50"
                    >
                        <p class="text-slate-600 dark:text-slate-400">
                            <span v-if="isEmailRequired && isPhoneRequired">
                                ⚡ Wybrano listy email i SMS - wymagany jest
                                <strong>email</strong> oraz
                                <strong>telefon</strong>
                            </span>
                            <span v-else-if="isEmailRequired">
                                📧 Wybrano listy email - wymagany jest
                                <strong>email</strong>
                            </span>
                            <span v-else-if="isPhoneRequired">
                                📱 Wybrano listy SMS - wymagany jest
                                <strong>telefon</strong>
                            </span>
                        </p>
                    </div>

                    <div>
                        <label
                            for="email"
                            class="mb-2 block text-sm font-medium text-slate-900 dark:text-white"
                        >
                            {{ $t("subscribers.fields.email") }}
                            <span v-if="isEmailRequired" class="text-red-500"
                                >*</span
                            >
                        </label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                            placeholder="example@email.com"
                            :required="isEmailRequired"
                        />
                        <p
                            v-if="form.errors.email"
                            class="mt-2 text-sm text-red-600 dark:text-red-400"
                        >
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <label
                                for="first_name"
                                class="mb-2 block text-sm font-medium text-slate-900 dark:text-white"
                            >
                                {{ $t("subscribers.fields.first_name") }}
                            </label>
                            <input
                                id="first_name"
                                v-model="form.first_name"
                                type="text"
                                class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                            />
                        </div>
                        <div>
                            <label
                                for="last_name"
                                class="mb-2 block text-sm font-medium text-slate-900 dark:text-white"
                            >
                                {{ $t("subscribers.fields.last_name") }}
                            </label>
                            <input
                                id="last_name"
                                v-model="form.last_name"
                                type="text"
                                class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                            />
                        </div>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <label
                                for="phone"
                                class="mb-2 block text-sm font-medium text-slate-900 dark:text-white"
                            >
                                {{
                                    $t("subscribers.fields.phone") || "Telefon"
                                }}
                                <span
                                    v-if="isPhoneRequired"
                                    class="text-red-500"
                                    >*</span
                                >
                            </label>
                            <PhoneInput
                                id="phone"
                                v-model="form.phone"
                                :required="isPhoneRequired"
                            />
                            <p
                                v-if="form.errors.phone"
                                class="mt-2 text-sm text-red-600 dark:text-red-400"
                            >
                                {{ form.errors.phone }}
                            </p>
                        </div>
                        <div>
                            <label
                                for="gender"
                                class="mb-2 block text-sm font-medium text-slate-900 dark:text-white"
                            >
                                {{ $t("subscribers.fields.gender") || "Płeć" }}
                            </label>
                            <select
                                id="gender"
                                v-model="form.gender"
                                class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                            >
                                <option value="">
                                    {{ $t("common.select") || "Wybierz" }}
                                </option>
                                <option value="male">
                                    {{
                                        $t("common.gender.male") || "Mężczyzna"
                                    }}
                                </option>
                                <option value="female">
                                    {{
                                        $t("common.gender.female") || "Kobieta"
                                    }}
                                </option>
                                <option value="other">
                                    {{ $t("common.gender.other") || "Inne" }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Language -->
                    <div
                        v-if="Object.keys(availableLanguages || {}).length > 0"
                    >
                        <label
                            for="language"
                            class="mb-2 block text-sm font-medium text-slate-900 dark:text-white"
                        >
                            🌐 {{ $t("subscribers.fields.language") }}
                        </label>
                        <select
                            id="language"
                            v-model="form.language"
                            class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                        >
                            <option value="">
                                —
                                {{ $t("subscribers.fields.language_default") }}
                                —
                            </option>
                            <option
                                v-for="(name, code) in availableLanguages"
                                :key="code"
                                :value="code"
                            >
                                {{ name }} ({{ code.toUpperCase() }})
                            </option>
                        </select>
                    </div>

                    <!-- Timezone -->
                    <div v-if="timezones.length > 0">
                        <label
                            for="timezone"
                            class="mb-2 block text-sm font-medium text-slate-900 dark:text-white"
                        >
                            🕐 {{ $t("subscribers.fields.timezone") }}
                        </label>
                        <select
                            id="timezone"
                            v-model="form.timezone"
                            class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                        >
                            <option value="">
                                —
                                {{ $t("subscribers.fields.timezone_default") }}
                                —
                            </option>
                            <option
                                v-for="tz in timezones"
                                :key="tz"
                                :value="tz"
                            >
                                {{ tz }}
                            </option>
                        </select>
                        <p
                            class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                        >
                            {{ $t("subscribers.fields.timezone_help") }}
                        </p>
                    </div>

                    <!-- Custom Fields -->
                    <div v-for="field in customFields" :key="field.id">
                        <label
                            :for="'field_' + field.id"
                            class="mb-2 block text-sm font-medium text-slate-900 dark:text-white"
                        >
                            {{ field.label }}
                            <span v-if="field.is_required" class="text-red-500"
                                >*</span
                            >
                        </label>

                        <input
                            v-if="
                                ['text', 'number', 'url', 'email'].includes(
                                    field.type,
                                )
                            "
                            :id="'field_' + field.id"
                            v-model="form.custom_fields[field.id]"
                            :type="field.type"
                            :required="field.is_required"
                            class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:placeholder-slate-500 dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                        />

                        <select
                            v-else-if="field.type === 'select'"
                            :id="'field_' + field.id"
                            v-model="form.custom_fields[field.id]"
                            :required="field.is_required"
                            class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                        >
                            <option value="">{{ $t("common.select") }}</option>
                            <option
                                v-for="opt in field.options || []"
                                :key="opt"
                                :value="opt"
                            >
                                {{ opt }}
                            </option>
                        </select>

                        <input
                            v-else-if="field.type === 'date'"
                            :id="'field_' + field.id"
                            v-model="form.custom_fields[field.id]"
                            type="date"
                            :required="field.is_required"
                            class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                        />
                    </div>

                    <div>
                        <label
                            for="status"
                            class="mb-2 block text-sm font-medium text-slate-900 dark:text-white"
                        >
                            {{ $t("subscribers.fields.status") }}
                        </label>
                        <select
                            id="status"
                            v-model="form.status"
                            class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-slate-900 focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-white dark:focus:border-indigo-400 dark:focus:bg-slate-800"
                        >
                            <option value="active">
                                {{ $t("subscribers.statuses.active") }}
                            </option>
                            <option value="inactive">
                                {{
                                    $t("subscribers.statuses.inactive") ||
                                    "Nieaktywny"
                                }}
                            </option>
                        </select>
                    </div>

                    <div
                        class="flex items-center justify-end gap-4 border-t border-slate-100 pt-6 dark:border-slate-800"
                    >
                        <Link
                            :href="route('subscribers.index')"
                            class="rounded-xl px-6 py-2.5 text-sm font-semibold text-slate-600 transition-colors hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
                        >
                            {{ $t("common.cancel") }}
                        </Link>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg transition-all hover:bg-indigo-500 hover:shadow-indigo-500/25 disabled:opacity-50"
                        >
                            <svg
                                v-if="form.processing"
                                class="h-4 w-4 animate-spin text-white"
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
                                    ? $t("common.saving")
                                    : $t("common.save")
                            }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
