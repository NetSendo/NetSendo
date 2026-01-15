<script setup>
import { ref } from "vue";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";

const props = defineProps({
    companies: Array,
    owners: Array,
    subscriber: Object,
});

const form = useForm({
    subscriber_id: props.subscriber?.id || "",
    email: props.subscriber?.email || "",
    first_name: props.subscriber?.first_name || "",
    last_name: props.subscriber?.last_name || "",
    phone: props.subscriber?.phone || "",
    crm_company_id: "",
    owner_id: "",
    status: "lead",
    source: "manual",
    position: "",
});

const submit = () => {
    form.post("/crm/contacts", {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="$t('crm.contacts.create_title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link href="/crm/contacts" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $t('crm.contacts.create_title') }}</h1>
            </div>
        </template>

        <div class="mx-auto max-w-2xl">
            <form @submit.prevent="submit" class="rounded-2xl bg-white p-6 shadow-sm dark:bg-slate-800">
                <div class="space-y-6">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.contacts.fields.email_required') }}</label>
                            <input v-model="form.email" type="email" required :disabled="!!subscriber"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white disabled:bg-slate-50" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.contacts.fields.phone') }}</label>
                            <input v-model="form.phone" type="tel"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                        </div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.contacts.fields.first_name') }}</label>
                            <input v-model="form.first_name" type="text"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.contacts.fields.last_name') }}</label>
                            <input v-model="form.last_name" type="text"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                        </div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.contacts.fields.status') }}</label>
                            <select v-model="form.status"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                <option value="lead">{{ $t('crm.contacts.status.lead') }}</option>
                                <option value="prospect">{{ $t('crm.contacts.status.prospect') }}</option>
                                <option value="client">{{ $t('crm.contacts.status.client') }}</option>
                                <option value="dormant">{{ $t('crm.contacts.status.dormant') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.contacts.fields.source') }}</label>
                            <input v-model="form.source" type="text" :placeholder="$t('crm.contacts.placeholders.source')"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                        </div>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.contacts.fields.company') }}</label>
                            <select v-model="form.crm_company_id"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                <option value="">{{ $t('common.none') }}</option>
                                <option v-for="company in companies" :key="company.id" :value="company.id">
                                    {{ company.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.contacts.fields.position') }}</label>
                            <input v-model="form.position" type="text" :placeholder="$t('crm.contacts.placeholders.position')"
                                class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white" />
                        </div>
                    </div>
                    <div v-if="owners?.length > 1">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $t('crm.contacts.fields.owner') }}</label>
                        <select v-model="form.owner_id"
                            class="mt-1 w-full rounded-xl border-slate-200 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                            <option value="">{{ $t('common.select_option') }}</option>
                            <option v-for="owner in owners" :key="owner.id" :value="owner.id">
                                {{ owner.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <Link href="/crm/contacts" class="rounded-xl bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300">
                        {{ $t('common.cancel') }}
                    </Link>
                    <button type="submit" :disabled="form.processing"
                        class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                        {{ $t('crm.contacts.create_button') }}
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
