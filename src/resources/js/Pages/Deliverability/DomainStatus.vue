<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ConfirmModal from "@/Components/ConfirmModal.vue";
import DnsRecordGenerator from "@/Components/Deliverability/DnsRecordGenerator.vue";
import { Head, Link, router, useForm, usePage } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import { useI18n } from "vue-i18n";
import { useLicense } from "@/Composables/useLicense";

const { t } = useI18n();
const { can } = useLicense();
const page = usePage();

const props = defineProps({
    domain: { type: Object, required: true },
    cnameInstruction: { type: Object, required: true },
    humanStatus: { type: Object, required: true },
    simulations: { type: Array, default: () => [] },
    isLocalhost: { type: Boolean, default: false },
    // NetSendo MTA (NMI, GOLD) for this domain
    nmi: {
        type: Object,
        default: () => ({ available: false, enabled: false, ips: [], mailboxes_count: 0 }),
    },
});

// NMI card: shown with GOLD, or when NMI was switched on before (it can still be switched off)
const showNmiCard = computed(() => can("mail_infrastructure") || props.nmi.enabled);
const nmiProcessing = ref(false);
const nmiError = computed(() => page.props.errors?.domain);

const toggleNmi = () => {
    nmiProcessing.value = true;
    router.post(
        route(props.nmi.enabled ? "settings.nmi.domains.disable" : "settings.nmi.domains.enable", props.domain.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => (nmiProcessing.value = false),
        },
    );
};

const refreshing = ref(false);
const verifying = ref(false);
const showDeleteModal = ref(false);
const deleteProcessing = ref(false);
const copiedField = ref(null);
const showDnsGenerator = ref(false);

// Show DNS Generator if there are issues with DMARC or SPF
const needsDnsHelp = computed(() => {
    return props.domain.dmarc_status !== 'valid' ||
           props.domain.spf_status !== 'valid' ||
           props.domain.dmarc_policy === 'none';
});

// DKIM selector entered by the user (needed for Amazon SES and other token selectors)
const dkimSelectorForm = useForm({
    dkim_selector: props.domain.dkim_selector || "",
});
const saveDkimSelector = () => {
    dkimSelectorForm.put(
        route("deliverability.domains.dkim-selector", props.domain.id),
        { preserveScroll: true },
    );
};
const dkimSelectorsChecked = computed(
    () => props.domain.dns_records?.dkim_selectors_checked || [],
);
const dkimSelectorFound = computed(
    () => props.domain.dns_records?.dkim_selector_found || null,
);

// Copy to clipboard
const copyToClipboard = async (text, fieldName) => {
    try {
        await navigator.clipboard.writeText(text);
        copiedField.value = fieldName;
        setTimeout(() => {
            copiedField.value = null;
        }, 2000);
    } catch (err) {
        console.error("Failed to copy:", err);
    }
};

// Status colors: records are valid/warning/critical/pending, the overall status safe/warning/critical/pending
const green = {
    bg: "bg-emerald-100 dark:bg-emerald-900/30",
    text: "text-emerald-700 dark:text-emerald-400",
    icon: "check",
};
const statusColors = {
    valid: green,
    safe: green,
    warning: {
        bg: "bg-amber-100 dark:bg-amber-900/30",
        text: "text-amber-700 dark:text-amber-400",
        icon: "warning",
    },
    critical: {
        bg: "bg-rose-100 dark:bg-rose-900/30",
        text: "text-rose-700 dark:text-rose-400",
        icon: "x",
    },
    missing: {
        bg: "bg-gray-100 dark:bg-slate-700",
        text: "text-gray-600 dark:text-gray-400",
        icon: "minus",
    },
};

const getStatusInfo = (status) => statusColors[status] || statusColors.missing;

// Verify CNAME
const verifyCname = () => {
    verifying.value = true;
    router.post(
        route("deliverability.domains.verify", props.domain.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                verifying.value = false;
            },
        },
    );
};

// Refresh status
const refreshStatus = () => {
    refreshing.value = true;
    router.post(
        route("deliverability.domains.refresh", props.domain.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                refreshing.value = false;
            },
        },
    );
};

// Toggle alerts
const toggleAlerts = () => {
    router.post(
        route("deliverability.domains.alerts", props.domain.id),
        {},
        {
            preserveScroll: true,
        },
    );
};

// Delete domain
const deleteDomain = () => {
    deleteProcessing.value = true;
    router.delete(route("deliverability.domains.destroy", props.domain.id), {
        onFinish: () => {
            deleteProcessing.value = false;
            showDeleteModal.value = false;
        },
    });
};
</script>

<template>
    <Head :title="domain.domain + ' - ' + $t('deliverability.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link
                    :href="route('deliverability.index')"
                    class="rounded-lg p-2 text-gray-500 transition-colors hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-slate-700"
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
                            d="M15 19l-7-7 7-7"
                        />
                    </svg>
                </Link>
                <div class="flex-1">
                    <h2
                        class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
                    >
                        {{ domain.domain }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{
                            domain.last_check_at
                                ? $t("deliverability.last_check") +
                                  domain.last_check_at
                                : $t("deliverability.never_checked")
                        }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        @click="refreshStatus"
                        :disabled="refreshing"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-slate-600 dark:text-gray-300 dark:hover:bg-slate-700"
                    >
                        <svg
                            class="h-4 w-4"
                            :class="{ 'animate-spin': refreshing }"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                            />
                        </svg>
                        {{ $t("deliverability.refresh") }}
                    </button>
                    <button
                        @click="showDeleteModal = true"
                        class="rounded-lg p-2 text-rose-600 transition-colors hover:bg-rose-50 dark:hover:bg-rose-900/20"
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
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                            />
                        </svg>
                    </button>
                </div>
            </div>
        </template>

        <!-- Flash Messages -->
        <div
            v-if="$page.props.flash?.success"
            class="mb-4 rounded-lg bg-emerald-100 border border-emerald-300 p-4 dark:bg-emerald-900/30 dark:border-emerald-700"
        >
            <div class="flex items-center gap-3">
                <svg
                    class="h-5 w-5 text-emerald-600 dark:text-emerald-400"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                >
                    <path
                        fill-rule="evenodd"
                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                        clip-rule="evenodd"
                    />
                </svg>
                <span class="text-emerald-700 dark:text-emerald-300">{{
                    $page.props.flash.success
                }}</span>
            </div>
        </div>

        <div
            v-if="$page.props.flash?.error"
            class="mb-4 rounded-lg bg-rose-100 border border-rose-300 p-4 dark:bg-rose-900/30 dark:border-rose-700"
        >
            <div class="flex items-center gap-3">
                <svg
                    class="h-5 w-5 text-rose-600 dark:text-rose-400"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                >
                    <path
                        fill-rule="evenodd"
                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                        clip-rule="evenodd"
                    />
                </svg>
                <span class="text-rose-700 dark:text-rose-300">{{
                    $page.props.flash.error
                }}</span>
            </div>
        </div>

        <div class="space-y-6">
            <!-- Verification Status -->
            <div
                v-if="!domain.cname_verified"
                class="rounded-xl border-2 border-dashed border-amber-300 bg-amber-50 p-6 dark:border-amber-700 dark:bg-amber-900/20"
            >
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0">
                        <svg
                            class="h-8 w-8 text-amber-500"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                            />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3
                            class="font-semibold text-amber-800 dark:text-amber-200"
                        >
                            {{ $t("deliverability.verification_required") }}
                        </h3>
                        <p
                            class="mt-1 text-sm text-amber-700 dark:text-amber-300"
                        >
                            {{ $t("deliverability.verification_description") }}
                        </p>

                        <!-- Localhost Warning -->
                        <div
                            v-if="isLocalhost"
                            class="mt-4 rounded-lg border border-rose-300 bg-rose-50 p-3 dark:border-rose-700 dark:bg-rose-900/20"
                        >
                            <div class="flex gap-2">
                                <svg
                                    class="h-5 w-5 flex-shrink-0 text-rose-600 dark:text-rose-400"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                                <div>
                                    <h4
                                        class="text-sm font-medium text-rose-800 dark:text-rose-200"
                                    >
                                        {{
                                            $t(
                                                "deliverability.localhost_warning.title",
                                            )
                                        }}
                                    </h4>
                                    <p
                                        class="mt-1 text-xs text-rose-700 dark:text-rose-300"
                                    >
                                        {{
                                            $t(
                                                "deliverability.localhost_warning.description",
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- CNAME Instructions -->
                        <div
                            class="mt-4 rounded-lg bg-white p-4 dark:bg-slate-800"
                        >
                            <p
                                class="mb-3 text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                {{ $t("deliverability.add_this_record") }}:
                            </p>
                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <span
                                        class="text-gray-500 dark:text-gray-400"
                                        >{{ $t("deliverability.type") }}:</span
                                    >
                                    <span
                                        class="ml-2 font-mono font-semibold text-gray-900 dark:text-white"
                                        >{{
                                            cnameInstruction.record_type
                                        }}</span
                                    >
                                </div>
                                <div>
                                    <span
                                        class="text-gray-500 dark:text-gray-400"
                                        >TTL:</span
                                    >
                                    <span
                                        class="ml-2 font-mono font-semibold text-gray-900 dark:text-white"
                                        >{{ cnameInstruction.ttl }}</span
                                    >
                                </div>
                                <div
                                    class="col-span-2 flex items-center justify-between"
                                >
                                    <div>
                                        <span
                                            class="text-gray-500 dark:text-gray-400"
                                            >{{
                                                $t("deliverability.host")
                                            }}:</span
                                        >
                                        <span
                                            class="ml-2 font-mono font-semibold text-indigo-600 dark:text-indigo-400"
                                            >{{ cnameInstruction.host }}</span
                                        >
                                    </div>
                                    <button
                                        @click="
                                            copyToClipboard(
                                                cnameInstruction.host,
                                                'host',
                                            )
                                        "
                                        class="flex items-center gap-1 rounded px-2 py-1 text-xs text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-slate-700 dark:hover:text-gray-200"
                                    >
                                        <svg
                                            v-if="copiedField === 'host'"
                                            class="h-4 w-4 text-emerald-500"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M5 13l4 4L19 7"
                                            />
                                        </svg>
                                        <svg
                                            v-else
                                            class="h-4 w-4"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                                            />
                                        </svg>
                                        {{
                                            copiedField === "host"
                                                ? $t("common.copied")
                                                : $t("common.copy")
                                        }}
                                    </button>
                                </div>
                                <div
                                    class="col-span-2 flex items-center justify-between"
                                >
                                    <div>
                                        <span
                                            class="text-gray-500 dark:text-gray-400"
                                            >{{
                                                $t("deliverability.target")
                                            }}:</span
                                        >
                                        <span
                                            class="ml-2 font-mono font-semibold text-gray-900 dark:text-white"
                                            >{{ cnameInstruction.target }}</span
                                        >
                                    </div>
                                    <button
                                        @click="
                                            copyToClipboard(
                                                cnameInstruction.target,
                                                'target',
                                            )
                                        "
                                        class="flex items-center gap-1 rounded px-2 py-1 text-xs text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-slate-700 dark:hover:text-gray-200"
                                    >
                                        <svg
                                            v-if="copiedField === 'target'"
                                            class="h-4 w-4 text-emerald-500"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M5 13l4 4L19 7"
                                            />
                                        </svg>
                                        <svg
                                            v-else
                                            class="h-4 w-4"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                                            />
                                        </svg>
                                        {{
                                            copiedField === "target"
                                                ? $t("common.copied")
                                                : $t("common.copy")
                                        }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button
                            @click="verifyCname"
                            :disabled="verifying"
                            class="mt-4 inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-700 disabled:opacity-50"
                        >
                            <svg
                                v-if="verifying"
                                class="h-4 w-4 animate-spin"
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
                            {{ $t("deliverability.verify_now") }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Overall Status -->
            <div
                class="rounded-xl border bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800"
            >
                <div class="flex items-center justify-between mb-6">
                    <h3 class="font-semibold text-gray-900 dark:text-white">
                        {{ $t("deliverability.status_overview") }}
                    </h3>
                    <span
                        class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-sm font-medium"
                        :class="
                            getStatusInfo(domain.overall_status).bg +
                            ' ' +
                            getStatusInfo(domain.overall_status).text
                        "
                    >
                        {{ humanStatus.summary }}
                    </span>
                </div>

                <!-- Records Grid -->
                <div class="grid gap-4 md:grid-cols-3">
                    <!-- SPF -->
                    <div
                        class="rounded-lg border p-4 dark:border-slate-600"
                        :class="getStatusInfo(domain.spf_status).bg"
                    >
                        <div class="flex items-center justify-between">
                            <span
                                class="font-semibold text-gray-900 dark:text-white"
                                >SPF</span
                            >
                            <span
                                :class="getStatusInfo(domain.spf_status).text"
                            >
                                <svg
                                    v-if="domain.spf_status === 'valid'"
                                    class="h-5 w-5"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                                <svg
                                    v-else
                                    class="h-5 w-5"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </span>
                        </div>
                        <p
                            class="mt-2 text-xs"
                            :class="getStatusInfo(domain.spf_status).text"
                        >
                            {{ humanStatus.spf }}
                        </p>
                    </div>

                    <!-- DKIM -->
                    <div
                        class="rounded-lg border p-4 dark:border-slate-600"
                        :class="getStatusInfo(domain.dkim_status).bg"
                    >
                        <div class="flex items-center justify-between">
                            <span
                                class="font-semibold text-gray-900 dark:text-white"
                                >DKIM</span
                            >
                            <span
                                :class="getStatusInfo(domain.dkim_status).text"
                            >
                                <svg
                                    v-if="domain.dkim_status === 'valid'"
                                    class="h-5 w-5"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                                <svg
                                    v-else
                                    class="h-5 w-5"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </span>
                        </div>
                        <p
                            class="mt-2 text-xs"
                            :class="getStatusInfo(domain.dkim_status).text"
                        >
                            {{ humanStatus.dkim }}
                        </p>
                        <p
                            v-if="dkimSelectorFound"
                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                        >
                            {{ $t("deliverability.dkim_selector.found") }}:
                            <code>{{ dkimSelectorFound }}</code>
                        </p>
                        <p
                            v-else-if="dkimSelectorsChecked.length"
                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                        >
                            {{ $t("deliverability.dkim_selector.checked") }}:
                            {{ dkimSelectorsChecked.join(", ") }}
                        </p>
                        <form
                            v-if="domain.cname_verified"
                            class="mt-3 space-y-1"
                            @submit.prevent="saveDkimSelector"
                        >
                            <label
                                for="dkim_selector"
                                class="block text-xs font-medium text-gray-700 dark:text-gray-300"
                            >
                                {{ $t("deliverability.dkim_selector.label") }}
                            </label>
                            <div class="flex gap-2">
                                <input
                                    id="dkim_selector"
                                    v-model="dkimSelectorForm.dkim_selector"
                                    type="text"
                                    maxlength="255"
                                    :placeholder="$t('deliverability.dkim_selector.placeholder')"
                                    class="min-w-0 flex-1 rounded-md border-gray-300 px-2 py-1 text-xs dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                                />
                                <button
                                    type="submit"
                                    :disabled="dkimSelectorForm.processing"
                                    class="rounded-md bg-indigo-600 px-2 py-1 text-xs font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    {{ $t("deliverability.dkim_selector.save") }}
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $t("deliverability.dkim_selector.help") }}
                            </p>
                            <p
                                v-if="dkimSelectorForm.errors.dkim_selector"
                                class="text-xs text-rose-600"
                            >
                                {{ dkimSelectorForm.errors.dkim_selector }}
                            </p>
                        </form>
                    </div>

                    <!-- DMARC -->
                    <div
                        class="rounded-lg border p-4 dark:border-slate-600"
                        :class="getStatusInfo(domain.dmarc_status).bg"
                    >
                        <div class="flex items-center justify-between">
                            <span
                                class="font-semibold text-gray-900 dark:text-white"
                                >DMARC</span
                            >
                            <span
                                :class="getStatusInfo(domain.dmarc_status).text"
                            >
                                <svg
                                    v-if="domain.dmarc_status === 'valid'"
                                    class="h-5 w-5"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                                <svg
                                    v-else
                                    class="h-5 w-5"
                                    fill="currentColor"
                                    viewBox="0 0 20 20"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </span>
                        </div>
                        <p
                            class="mt-2 text-xs"
                            :class="getStatusInfo(domain.dmarc_status).text"
                        >
                            {{ humanStatus.dmarc }}
                        </p>
                        <p
                            v-if="domain.dmarc_policy"
                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                        >
                            {{ $t("deliverability.policy_label") }}: {{ domain.dmarc_policy }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- DNS Record Generator Section -->
            <div
                v-if="needsDnsHelp && domain.cname_verified"
                class="rounded-xl border bg-gradient-to-br from-indigo-50 to-purple-50 dark:from-indigo-900/20 dark:to-purple-900/20 p-6 shadow-sm dark:border-slate-700"
            >
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="flex-shrink-0 p-2 rounded-lg bg-indigo-100 dark:bg-indigo-900/40">
                            <svg class="h-6 w-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">
                                {{ $t('deliverability.dns_generator.one_click_fix') }}
                            </h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $t('deliverability.dmarc_generator.subtitle') }}
                            </p>
                        </div>
                    </div>
                    <button
                        @click="showDnsGenerator = !showDnsGenerator"
                        class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                        :class="showDnsGenerator
                            ? 'bg-gray-200 text-gray-700 dark:bg-slate-700 dark:text-gray-300'
                            : 'bg-indigo-600 text-white hover:bg-indigo-700'"
                    >
                        <svg v-if="!showDnsGenerator" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                        <svg v-else class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                        </svg>
                        {{ showDnsGenerator ? $t('deliverability.dns_generator.hide_generator') : $t('deliverability.dns_generator.show_generator') }}
                    </button>
                </div>

                <Transition name="expand">
                    <DnsRecordGenerator
                        v-if="showDnsGenerator"
                        :domain-id="domain.id"
                        :show-dmarc="domain.dmarc_status !== 'valid' || domain.dmarc_policy === 'none'"
                        :show-spf="domain.spf_status !== 'valid'"
                        class="mt-4"
                    />
                </Transition>
            </div>

            <!-- NetSendo MTA (NMI) -->
            <div
                v-if="showNmiCard"
                class="rounded-xl border bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <h3 class="font-semibold text-gray-900 dark:text-white">
                                {{ $t("nmi.domain_card.title") }}
                            </h3>
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="
                                    nmi.enabled
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                        : 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-gray-300'
                                "
                            >
                                {{ nmi.enabled ? $t("nmi.domain_card.enabled") : $t("nmi.domain_card.disabled") }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $t("nmi.domain_card.description") }}
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="toggleNmi"
                        :disabled="
                            nmiProcessing ||
                            (!nmi.enabled && (!domain.cname_verified || !nmi.available)) ||
                            (nmi.enabled && nmi.mailboxes_count > 0)
                        "
                        class="rounded-lg px-4 py-2 text-sm font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                        :class="
                            nmi.enabled
                                ? 'border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-slate-600 dark:text-gray-300 dark:hover:bg-slate-700'
                                : 'bg-indigo-600 text-white hover:bg-indigo-700'
                        "
                    >
                        {{ nmi.enabled ? $t("nmi.domain_card.disable") : $t("nmi.domain_card.enable") }}
                    </button>
                </div>

                <p v-if="nmiError" class="mt-3 text-sm text-rose-600">{{ nmiError }}</p>
                <p
                    v-if="!nmi.enabled && !nmi.available"
                    class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-200"
                >
                    {{ $t("nmi.domain_card.not_available") }}
                </p>
                <p
                    v-else-if="!nmi.enabled && !domain.cname_verified"
                    class="mt-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-200"
                >
                    {{ $t("nmi.domain_card.needs_verification") }}
                </p>

                <div v-if="nmi.enabled" class="mt-4 space-y-4">
                    <div>
                        <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $t("nmi.domain_card.smtp_username") }}
                        </span>
                        <code class="mt-1 inline-block rounded bg-gray-100 px-2 py-1 text-sm dark:bg-slate-700 dark:text-gray-200">
                            {{ nmi.smtp_username }}
                        </code>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ $t("nmi.domain_card.smtp_username_hint") }}
                        </p>
                    </div>

                    <div>
                        <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $t("nmi.domain_card.ips") }}
                        </span>
                        <ul v-if="nmi.ips.length" class="mt-2 divide-y divide-gray-100 rounded-lg border border-gray-200 dark:divide-slate-700 dark:border-slate-700">
                            <li
                                v-for="ip in nmi.ips"
                                :key="ip.id"
                                class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-sm"
                            >
                                <Link
                                    :href="route('settings.nmi.ips.show', ip.id)"
                                    class="font-mono text-indigo-600 hover:underline dark:text-indigo-400"
                                >
                                    {{ ip.ip_address }}
                                </Link>
                                <span class="text-gray-500 dark:text-gray-400">{{ ip.hostname }}</span>
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs"
                                    :class="
                                        ip.dkim_ready
                                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                            : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                                    "
                                >
                                    {{ ip.dkim_ready ? $t("nmi.domain_card.dkim_ready") : $t("nmi.domain_card.dkim_missing") }}
                                </span>
                            </li>
                        </ul>
                        <p v-else class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $t("nmi.domain_card.no_ips") }}
                            <Link
                                :href="route('settings.nmi.dashboard')"
                                class="text-indigo-600 hover:underline dark:text-indigo-400"
                            >
                                {{ $t("navigation.nmi") }}
                            </Link>
                        </p>
                    </div>

                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ $t("nmi.domain_card.mailboxes", { count: nmi.mailboxes_count }) }}
                        <span v-if="nmi.mailboxes_count > 0" class="block text-xs text-gray-500 dark:text-gray-400">
                            {{ $t("nmi.domain_card.in_use") }}
                        </span>
                    </p>
                    <p
                        v-if="nmi.mailboxes_count === 0"
                        class="rounded-lg bg-indigo-50 p-3 text-sm text-indigo-800 dark:bg-indigo-900/20 dark:text-indigo-200"
                    >
                        {{ $t("nmi.domain_card.next_steps") }}
                        <Link
                            :href="route('settings.mailboxes.index')"
                            class="font-medium underline"
                        >
                            {{ $t("mailboxes.title") }}
                        </Link>
                    </p>
                </div>
            </div>

            <!-- Alerts Toggle -->
            <div
                class="rounded-xl border bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            {{ $t("deliverability.alerts.title") }}
                        </h3>
                        <p
                            class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                        >
                            {{ $t("deliverability.alerts.description") }}
                        </p>
                    </div>
                    <button
                        @click="toggleAlerts"
                        class="relative h-6 w-11 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        :class="
                            domain.alerts_enabled
                                ? 'bg-indigo-600'
                                : 'bg-gray-300 dark:bg-slate-600'
                        "
                    >
                        <span
                            class="absolute left-0.5 top-0.5 h-5 w-5 transform rounded-full bg-white shadow transition-transform"
                            :class="
                                domain.alerts_enabled
                                    ? 'translate-x-5'
                                    : 'translate-x-0'
                            "
                        ></span>
                    </button>
                </div>
            </div>

            <!-- Test Email Section -->
            <div
                class="rounded-xl border bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">
                            {{ $t("deliverability.test_email") }}
                        </h3>
                        <p
                            class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                        >
                            {{ $t("deliverability.test_email_description") }}
                        </p>
                    </div>
                    <Link
                        :href="
                            route('deliverability.simulator') +
                            '?domain=' +
                            domain.id
                        "
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700"
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"
                            />
                        </svg>
                        InboxPassport AI
                    </Link>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <ConfirmModal
            :show="showDeleteModal"
            :title="$t('deliverability.delete_domain')"
            :message="
                $t('deliverability.confirm_delete_message', {
                    domain: domain.domain,
                })
            "
            :confirm-text="$t('common.delete')"
            type="danger"
            :processing="deleteProcessing"
            @close="showDeleteModal = false"
            @confirm="deleteDomain"
        />
    </AuthenticatedLayout>
</template>
