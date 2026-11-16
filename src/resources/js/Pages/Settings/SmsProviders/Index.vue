<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ConfirmModal from "@/Components/ConfirmModal.vue";
import ActionMenu from "@/Components/Ui/ActionMenu.vue";
import ActionMenuItem from "@/Components/Ui/ActionMenuItem.vue";
import CreateLink from "@/Components/Ui/CreateLink.vue";
import EmptyState from "@/Components/Ui/EmptyState.vue";
import FormDialog from "@/Components/Ui/FormDialog.vue";
import PageHeader from "@/Components/Ui/PageHeader.vue";
import Panel from "@/Components/Ui/Panel.vue";
import StatusPill from "@/Components/Ui/StatusPill.vue";
import Switch from "@/Components/Ui/Switch.vue";
import Toast from "@/Components/Ui/Toast.vue";
import { errorClass, fieldClass, hintClass, labelClass } from "@/Components/Ui/fieldClass";
import { useToast } from "@/Composables/useToast";
import { Head, useForm, router } from "@inertiajs/vue3";
import { ref, computed, watch } from "vue";
import { useI18n } from "vue-i18n";

const { t, te, locale } = useI18n();

const number = (value) => new Intl.NumberFormat(locale.value).format(value ?? 0);

const props = defineProps({
    providers: {
        type: Array,
        default: () => [],
    },
    availableProviders: {
        type: Array,
        default: () => [],
    },
});

// Modal state
const showModal = ref(false);
const modalMode = ref("create");
const editingProvider = ref(null);
const testingProvider = ref(null);
const testResult = ref(null);
const showPassword = ref({});
const loadingFields = ref(false);
const credentialFields = ref([]);

// Delete modal state
const showDeleteModal = ref(false);
const deletingProvider = ref(null);

// One message at a time: in the corner of the page, or in the footer of the open dialog
// (a dialog covers the corner). The server's own message is shown only for a failure.
const { toast, showToast, dismiss } = useToast({ flash: "error" });

// Form
const form = useForm({
    name: "",
    provider: "twilio",
    credentials: {},
    from_number: "",
    from_name: "",
    is_active: true,
    is_default: false,
    daily_limit: null,
});

// Provider icons
const providerIcons = {
    twilio: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.381 0 0 5.381 0 12s5.381 12 12 12 12-5.381 12-12S18.619 0 12 0zm0 20.4c-4.637 0-8.4-3.763-8.4-8.4S7.363 3.6 12 3.6s8.4 3.763 8.4 8.4-3.763 8.4-8.4 8.4zm3.6-8.4c0 1.988-1.612 3.6-3.6 3.6s-3.6-1.612-3.6-3.6 1.612-3.6 3.6-3.6 3.6 1.612 3.6 3.6z"/></svg>`,
    smsapi: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12zM7 9h2v2H7zm4 0h2v2h-2zm4 0h2v2h-2z"/></svg>`,
    smsapi_com: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12zM7 9h2v2H7zm4 0h2v2h-2zm4 0h2v2h-2z"/></svg>`,
    vonage: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.18l7.27 3.64L12 11.45 4.73 7.82 12 4.18zM4 9.1l7 3.5v7.3l-7-3.5V9.1zm9 10.8v-7.3l7-3.5v7.3l-7 3.5z"/></svg>`,
    messagebird: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>`,
    plivo: `<svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14l-5-5 1.41-1.41L12 14.17l4.59-4.58L18 11l-6 6z"/></svg>`,
};

// Provider colors
const providerColors = {
    twilio: "#F22F46",
    smsapi: "#00A7E1",
    smsapi_com: "#00A7E1",
    vonage: "#7D4CDB",
    messagebird: "#2481D7",
    plivo: "#00C853",
};

// Load credential fields for provider
const loadCredentialFields = async (provider) => {
    loadingFields.value = true;
    try {
        const response = await fetch(
            route("settings.sms-providers.fields", provider),
            {
                headers: {
                    Accept: "application/json",
                    "X-CSRF-TOKEN": document.querySelector(
                        'meta[name="csrf-token"]'
                    )?.content,
                },
            }
        );
        const data = await response.json();
        credentialFields.value = data.fields || [];
    } catch (error) {
        console.error("Failed to load fields:", error);
        credentialFields.value = [];
    } finally {
        loadingFields.value = false;
    }
};

// Watch provider changes
watch(
    () => form.provider,
    async (newProvider) => {
        if (
            editingProvider.value &&
            newProvider === editingProvider.value.provider
        ) {
            return;
        }
        form.credentials = {};
        showPassword.value = {};
        await loadCredentialFields(newProvider);
    }
);

// Open modal for creating/editing
const openModal = async (provider = null) => {
    if (provider) {
        modalMode.value = "edit";
        editingProvider.value = provider;
        form.name = provider.name;
        form.provider = provider.provider;
        form.from_number = provider.from_number || "";
        form.from_name = provider.from_name || "";
        form.is_active = provider.is_active;
        form.is_default = provider.is_default;
        form.daily_limit = provider.daily_limit;
        form.credentials = {};
    } else {
        modalMode.value = "create";
        editingProvider.value = null;
        form.reset();
        form.provider = "twilio";
        form.is_active = true;
    }
    testResult.value = null;
    showPassword.value = {};
    form.clearErrors();
    dismiss();
    await loadCredentialFields(form.provider);
    showModal.value = true;
};

// Every validation message of the last save, each once: the dialog lists them above its
// buttons, because the login fields show none of their own
const formErrorMessages = computed(() => [...new Set(Object.values(form.errors).filter(Boolean))]);

// Close modal
const closeModal = () => {
    showModal.value = false;
    editingProvider.value = null;
    form.reset();
    form.clearErrors();
    testResult.value = null;
};

// Submit form
const submitForm = () => {
    if (modalMode.value === "edit") {
        form.put(
            route("settings.sms-providers.update", editingProvider.value.id),
            {
                preserveScroll: true,
                onSuccess: () => {
                    closeModal();
                    showToast(t("sms_providers.notifications.updated"));
                },
            }
        );
    } else {
        form.post(route("settings.sms-providers.store"), {
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
                showToast(t("sms_providers.notifications.created"));
            },
        });
    }
};

// Test connection
const testConnection = async (provider) => {
    testingProvider.value = provider.id;
    testResult.value = null;

    try {
        const response = await fetch(
            route("settings.sms-providers.test", provider.id),
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": document.querySelector(
                        'meta[name="csrf-token"]'
                    )?.content,
                },
            }
        );

        const data = await response.json();
        testResult.value = data;
        showToast(data.message, data.success);

        router.reload({ only: ["providers"] });
    } catch (error) {
        showToast(
            t("common.error") + ": " + error.message,
            false
        );
    } finally {
        testingProvider.value = null;
    }
};

// Set as default
const setDefault = (provider) => {
    router.post(
        route("settings.sms-providers.default", provider.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                showToast(t("sms_providers.notifications.set_default"));
            },
        }
    );
};

// Open delete confirmation modal
const confirmDelete = (provider) => {
    deletingProvider.value = provider;
    showDeleteModal.value = true;
};

// Close delete modal
const closeDeleteModal = () => {
    showDeleteModal.value = false;
    deletingProvider.value = null;
};

// Delete provider
const deleteProvider = () => {
    if (!deletingProvider.value) return;

    router.delete(
        route("settings.sms-providers.destroy", deletingProvider.value.id),
        {
            preserveScroll: true,
            onSuccess: () => showToast(t("sms_providers.notifications.deleted")),
            onFinish: () => closeDeleteModal(),
        }
    );
};

// The switch in the row
const toggling = ref(null);

const toggleActive = (provider) => {
    if (toggling.value) return;
    toggling.value = provider.id;
    router.put(
        route("settings.sms-providers.update", provider.id),
        {
            name: provider.name,
            from_number: provider.from_number,
            from_name: provider.from_name,
            is_active: !provider.is_active,
            is_default: provider.is_default,
            daily_limit: provider.daily_limit,
        },
        {
            preserveScroll: true,
            onSuccess: () =>
                showToast(
                    t(
                        provider.is_active
                            ? "sms_providers.notifications.deactivated"
                            : "sms_providers.notifications.activated",
                    ),
                ),
            onError: (errors) => showToast(Object.values(errors).flat().join(" "), false),
            onFinish: () => (toggling.value = null),
        },
    );
};

// Where the provider stands: switched off, tested and fine, failing, or never tested
const statusOf = (provider) => {
    if (!provider.is_active) return { tone: "neutral", label: t("sms_providers.status.inactive") };
    if (provider.last_test_status === "success") return { tone: "success", label: t("sms_providers.status.active") };
    if (provider.last_test_status === "failed") return { tone: "danger", label: t("sms_providers.status.error") };
    return { tone: "info", label: t("sms_providers.status.not_tested") };
};

// The services this installation offers that have a line of explanation
const explainedProviders = computed(() =>
    props.availableProviders.filter((provider) => te(`sms_providers.info.${provider.id}`)),
);
</script>

<template>
    <Head :title="$t('sms_providers.title')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader :title="$t('sms_providers.title')" :subtitle="$t('sms_providers.subtitle')">
                <CreateLink @click="openModal()">{{ $t("sms_providers.add_new") }}</CreateLink>
            </PageHeader>
        </template>

        <div class="mx-auto flex max-w-screen-2xl flex-col gap-4">
            <Panel flush>
                <EmptyState
                    v-if="providers.length === 0"
                    class="!border-t-0"
                    :title="$t('sms_providers.empty.title')"
                    :description="$t('sms_providers.empty.description')"
                >
                    <CreateLink @click="openModal()">{{ $t("sms_providers.add_first") }}</CreateLink>
                </EmptyState>

                <div v-else class="relative -mt-3 overflow-x-auto">
                    <table class="w-full text-sm sm:min-w-[760px]">
                        <thead>
                            <tr
                                class="border-b border-slate-200 text-left text-xs text-slate-600 dark:border-slate-700/60 dark:text-slate-400"
                            >
                                <th scope="col" class="px-5 py-2.5 font-medium">{{ $t("sms_providers.table.provider") }}</th>
                                <th scope="col" class="px-3 py-2.5 font-medium max-sm:hidden">
                                    {{ $t("sms_providers.table.status") }}
                                </th>
                                <th scope="col" class="px-3 py-2.5 text-right font-medium max-sm:hidden">
                                    {{ $t("sms_providers.sent_today") }}
                                </th>
                                <th scope="col" class="px-3 py-2.5 font-medium">{{ $t("sms_providers.table.active") }}</th>
                                <th scope="col" class="py-2.5 pl-3 pr-5 text-right font-medium">
                                    <span class="sr-only">{{ $t("common.actions") }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="provider in providers"
                                :key="provider.id"
                                class="border-b border-slate-100 transition-colors last:border-0 hover:bg-slate-50 dark:border-slate-700/40 dark:hover:bg-slate-700/20"
                            >
                                <!-- Which connection: its name, what the recipient sees as the sender, the service -->
                                <td class="max-w-0 px-5 py-3 max-sm:w-full sm:w-[42%]">
                                    <div class="flex items-center gap-3">
                                        <span
                                            aria-hidden="true"
                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 max-sm:hidden dark:border-slate-700/60"
                                        >
                                            <span
                                                class="h-4 w-4"
                                                :style="{ color: providerColors[provider.provider] }"
                                                v-html="providerIcons[provider.provider] || providerIcons.smsapi"
                                            ></span>
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2">
                                                <button
                                                    type="button"
                                                    class="min-w-0 truncate text-left font-medium text-slate-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-300"
                                                    :title="provider.name"
                                                    @click="openModal(provider)"
                                                >
                                                    {{ provider.name }}
                                                </button>
                                                <StatusPill
                                                    v-if="provider.is_default"
                                                    tone="info"
                                                    class="shrink-0 max-sm:hidden"
                                                >
                                                    {{ $t("sms_providers.default") }}
                                                </StatusPill>
                                            </div>
                                            <p class="truncate text-xs text-slate-600 dark:text-slate-400">
                                                <template v-if="provider.from_number || provider.from_name">
                                                    {{ provider.from_number || provider.from_name }}
                                                    <span aria-hidden="true">·</span>
                                                </template>
                                                {{ provider.provider_label }}
                                            </p>
                                            <!-- On a phone the status has no column of its own and the badge
                                                 would squeeze the name: both sit under it -->
                                            <div class="mt-1.5 flex flex-wrap items-center gap-1.5 sm:hidden">
                                                <StatusPill :tone="statusOf(provider).tone">
                                                    {{ statusOf(provider).label }}
                                                </StatusPill>
                                                <StatusPill v-if="provider.is_default" tone="info">
                                                    {{ $t("sms_providers.default") }}
                                                </StatusPill>
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-3 py-3 max-sm:hidden">
                                    <StatusPill :tone="statusOf(provider).tone">
                                        {{ statusOf(provider).label }}
                                    </StatusPill>
                                    <p
                                        v-if="provider.last_tested_at"
                                        class="mt-1 text-xs tabular-nums text-slate-600 dark:text-slate-400"
                                    >
                                        {{ $t("sms_providers.last_tested", { date: provider.last_tested_at }) }}
                                    </p>
                                </td>

                                <td class="whitespace-nowrap px-3 py-3 text-right tabular-nums max-sm:hidden">
                                    <span class="text-slate-900 dark:text-slate-100">
                                        {{ number(provider.sent_today) }}
                                    </span>
                                    <span class="text-slate-600 dark:text-slate-400">
                                        /
                                        {{ provider.daily_limit ? number(provider.daily_limit) : $t("sms_providers.no_limit") }}
                                    </span>
                                </td>

                                <td class="px-3 py-3">
                                    <Switch
                                        :model-value="provider.is_active"
                                        :label="$t('sms_providers.switch_label', { name: provider.name })"
                                        :disabled="toggling === provider.id"
                                        @update:model-value="toggleActive(provider)"
                                    />
                                </td>

                                <td class="py-3 pl-3 pr-5">
                                    <div class="flex items-center justify-end gap-1">
                                        <button
                                            type="button"
                                            class="inline-flex min-h-9 items-center whitespace-nowrap rounded-lg px-2.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 hover:text-slate-900 max-sm:hidden dark:text-slate-200 dark:hover:bg-slate-700/60 dark:hover:text-white"
                                            @click="openModal(provider)"
                                        >
                                            {{ $t("common.edit") }}
                                        </button>
                                        <ActionMenu :label="$t('sms_providers.actions_for', { name: provider.name })">
                                            <!-- On a phone the button beside the menu has no room: it is the first entry here -->
                                            <ActionMenuItem class="sm:hidden" @click="openModal(provider)">
                                                {{ $t("common.edit") }}
                                            </ActionMenuItem>
                                            <ActionMenuItem
                                                :disabled="testingProvider === provider.id"
                                                @click="testConnection(provider)"
                                            >
                                                {{
                                                    testingProvider === provider.id
                                                        ? $t("sms_providers.testing")
                                                        : $t("sms_providers.test_connection")
                                                }}
                                            </ActionMenuItem>
                                            <ActionMenuItem v-if="!provider.is_default" @click="setDefault(provider)">
                                                {{ $t("sms_providers.set_as_default") }}
                                            </ActionMenuItem>
                                            <ActionMenuItem danger @click="confirmDelete(provider)">
                                                {{ $t("common.delete") }}
                                            </ActionMenuItem>
                                        </ActionMenu>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Panel>

            <!-- Which service is which -->
            <Panel v-if="explainedProviders.length" :title="$t('sms_providers.info.title')">
                <dl class="grid grid-cols-1 gap-x-8 gap-y-4 text-sm md:grid-cols-2 xl:grid-cols-3">
                    <div v-for="provider in explainedProviders" :key="provider.id" class="flex flex-col gap-1">
                        <dt class="font-medium text-slate-900 dark:text-white">{{ provider.name }}</dt>
                        <dd class="text-slate-600 dark:text-slate-400">
                            {{ $t("sms_providers.info." + provider.id) }}
                        </dd>
                    </div>
                </dl>
            </Panel>
        </div>

        <FormDialog
            :show="showModal"
            :title="modalMode === 'edit' ? $t('sms_providers.modal.edit_title') : $t('sms_providers.modal.add_title')"
            :processing="form.processing"
            :submit-label="modalMode === 'edit' ? $t('common.save') : $t('common.add')"
            :errors="formErrorMessages"
            :toast="toast"
            @close="closeModal"
            @submit="submitForm"
        >
            <div class="space-y-4">
                <!-- The service: fixed once the connection exists, its logins belong to it -->
                <div>
                    <span :class="labelClass">{{ $t("sms_providers.modal.provider") }}</span>
                    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <button
                            v-for="availableProvider in availableProviders"
                            :key="availableProvider.id"
                            type="button"
                            :aria-pressed="form.provider === availableProvider.id"
                            :disabled="modalMode === 'edit' && form.provider !== availableProvider.id"
                            class="flex flex-col items-center gap-2 rounded-xl border p-3 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                            :class="
                                form.provider === availableProvider.id
                                    ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500 dark:bg-indigo-500/10'
                                    : 'border-slate-300 hover:border-slate-400 dark:border-slate-600 dark:hover:border-slate-500'
                            "
                            @click="form.provider = availableProvider.id"
                        >
                            <span
                                aria-hidden="true"
                                class="h-6 w-6"
                                :style="{ color: providerColors[availableProvider.id] }"
                                v-html="providerIcons[availableProvider.id] || providerIcons.smsapi"
                            ></span>
                            <span class="text-center text-xs font-medium text-slate-800 dark:text-slate-200">
                                {{ availableProvider.name }}
                            </span>
                        </button>
                    </div>
                </div>

                <div>
                    <label for="sms-provider-name" :class="labelClass">{{ $t("sms_providers.modal.name") }}</label>
                    <input
                        id="sms-provider-name"
                        v-model="form.name"
                        type="text"
                        :placeholder="$t('sms_providers.modal.name_placeholder')"
                        class="mt-1"
                        :class="[fieldClass, { '!border-rose-500': form.errors.name }]"
                    />
                    <p v-if="form.errors.name" :class="errorClass">{{ form.errors.name }}</p>
                </div>

                <!-- Twilio sends from a number, the others from a name -->
                <div v-if="form.provider === 'twilio'">
                    <label for="sms-provider-number" :class="labelClass">
                        {{ $t("sms_providers.modal.from_number") }}
                    </label>
                    <input
                        id="sms-provider-number"
                        v-model="form.from_number"
                        type="text"
                        placeholder="+48123456789"
                        class="mt-1"
                        :class="[fieldClass, { '!border-rose-500': form.errors.from_number }]"
                    />
                    <p v-if="form.errors.from_number" :class="errorClass">{{ form.errors.from_number }}</p>
                </div>
                <div v-else>
                    <label for="sms-provider-sender" :class="labelClass">
                        {{ $t("sms_providers.modal.from_name") }}
                    </label>
                    <input
                        id="sms-provider-sender"
                        v-model="form.from_name"
                        type="text"
                        placeholder="NetSendo"
                        maxlength="11"
                        class="mt-1"
                        :class="[fieldClass, { '!border-rose-500': form.errors.from_name }]"
                    />
                    <p v-if="form.errors.from_name" :class="errorClass">{{ form.errors.from_name }}</p>
                    <p v-else :class="hintClass">{{ $t("sms_providers.modal.from_name_hint") }}</p>
                </div>

                <!-- The logins the chosen service asks for -->
                <p v-if="loadingFields" role="status" class="py-4 text-center text-sm text-slate-600 dark:text-slate-400">
                    {{ $t("common.loading") }}
                </p>
                <template v-else>
                    <div v-for="field in credentialFields" :key="field.name">
                        <template v-if="field.type === 'checkbox'">
                            <label class="flex items-center gap-2.5">
                                <input
                                    v-model="form.credentials[field.name]"
                                    type="checkbox"
                                    class="h-4 w-4 accent-indigo-600"
                                />
                                <span class="text-sm text-slate-800 dark:text-slate-200">{{ field.label }}</span>
                            </label>
                            <p v-if="field.hint" :class="hintClass">{{ field.hint }}</p>
                        </template>
                        <template v-else>
                            <label :for="'sms-credential-' + field.name" :class="labelClass">
                                {{ field.label }}
                                <span v-if="field.required" aria-hidden="true" class="text-rose-600 dark:text-rose-400">*</span>
                            </label>
                            <div class="relative mt-1">
                                <input
                                    :id="'sms-credential-' + field.name"
                                    v-model="form.credentials[field.name]"
                                    :type="field.type === 'password' && !showPassword[field.name] ? 'password' : 'text'"
                                    :placeholder="field.placeholder || ''"
                                    :required="field.required && modalMode === 'create'"
                                    autocomplete="off"
                                    :class="[fieldClass, field.type === 'password' ? 'pr-11' : '']"
                                />
                                <button
                                    v-if="field.type === 'password'"
                                    type="button"
                                    :aria-label="showPassword[field.name] ? $t('common.hide') : $t('common.show')"
                                    :aria-pressed="!!showPassword[field.name]"
                                    class="absolute right-1 top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-700/60 dark:hover:text-white"
                                    @click="showPassword[field.name] = !showPassword[field.name]"
                                >
                                    <svg
                                        aria-hidden="true"
                                        class="h-4 w-4"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        viewBox="0 0 24 24"
                                    >
                                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" />
                                        <circle cx="12" cy="12" r="3" />
                                        <path v-if="showPassword[field.name]" d="M4 4l16 16" />
                                    </svg>
                                </button>
                            </div>
                            <p v-if="field.hint" :class="hintClass">{{ field.hint }}</p>
                        </template>
                    </div>
                </template>

                <div>
                    <label for="sms-provider-limit" :class="labelClass">
                        {{ $t("sms_providers.modal.daily_limit") }}
                    </label>
                    <input
                        id="sms-provider-limit"
                        v-model="form.daily_limit"
                        type="number"
                        min="1"
                        :placeholder="$t('sms_providers.modal.daily_limit_placeholder')"
                        class="mt-1"
                        :class="[fieldClass, { '!border-rose-500': form.errors.daily_limit }]"
                    />
                    <p v-if="form.errors.daily_limit" :class="errorClass">{{ form.errors.daily_limit }}</p>
                </div>

                <div class="flex flex-wrap gap-x-8 gap-y-3">
                    <label class="flex items-center gap-2.5">
                        <input v-model="form.is_active" type="checkbox" class="h-4 w-4 accent-indigo-600" />
                        <span class="text-sm text-slate-800 dark:text-slate-200">
                            {{ $t("sms_providers.modal.is_active") }}
                        </span>
                    </label>
                    <label class="flex items-center gap-2.5">
                        <input v-model="form.is_default" type="checkbox" class="h-4 w-4 accent-indigo-600" />
                        <span class="text-sm text-slate-800 dark:text-slate-200">
                            {{ $t("sms_providers.modal.is_default") }}
                        </span>
                    </label>
                </div>
            </div>
        </FormDialog>

        <ConfirmModal
            :show="showDeleteModal"
            type="danger"
            :title="$t('sms_providers.delete_modal.title')"
            :message="$t('sms_providers.delete_modal.message')"
            :confirm-text="$t('common.delete')"
            @close="closeDeleteModal"
            @confirm="deleteProvider"
        >
            {{ deletingProvider?.name }}
        </ConfirmModal>

        <!-- In the corner of the page; an open dialog shows the same message in its footer -->
        <Toast :toast="showModal ? null : toast" @close="dismiss" />
    </AuthenticatedLayout>
</template>
