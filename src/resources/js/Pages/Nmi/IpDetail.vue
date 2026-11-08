<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router, usePage } from "@inertiajs/vue3";
import { ref, computed, watch, onMounted } from "vue";
import { useI18n } from "vue-i18n";
import axios from "axios";

const { t } = useI18n();
const page = usePage();

const props = defineProps({
    ip: { type: Object, required: true },
    warming: { type: Object, default: () => ({}) },
    blacklist: { type: Object, default: () => ({}) },
    // null until DKIM keys exist: { record_type, name, value, ttl }
    dkim: { type: Object, default: null },
    // Domains of the user (Deliverability Shield) the IP can send for
    domains: { type: Array, default: () => [] },
    // Hosting providers (App\Services\Nmi\HostingProviders): the IP's own has instructions
    hostingProviders: { type: Array, default: () => [] },
});

// Warming status colors (an IP starts as "new")
const warmingColors = {
    new: { bg: "bg-slate-500/20", text: "text-slate-400" },
    warming: { bg: "bg-amber-500/20", text: "text-amber-500" },
    warmed: { bg: "bg-emerald-500/20", text: "text-emerald-500" },
    paused: { bg: "bg-rose-500/20", text: "text-rose-500" },
};

const getWarmingColor = (status) => warmingColors[status] || warmingColors.new;
const warmingLabel = (status) => t(`nmi.warming.${warmingColors[status] ? status : "new"}`);

// Sending domain of the IP
const selectedDomainId = ref(props.ip.domain?.id ?? "");
watch(
    () => props.ip.domain?.id,
    (id) => (selectedDomainId.value = id ?? ""),
);
const domainProcessing = ref(false);
const domainError = computed(() => page.props.errors?.domain_configuration_id);
const ipInUse = computed(() => (props.ip.mailboxes_count || 0) > 0);

const assignDomain = () => {
    if (!selectedDomainId.value) return;
    domainProcessing.value = true;
    router.post(
        route("settings.nmi.ips.domain.assign", props.ip.id),
        { domain_configuration_id: selectedDomainId.value },
        {
            preserveScroll: true,
            onFinish: () => (domainProcessing.value = false),
        },
    );
};

const unassignDomain = () => {
    domainProcessing.value = true;
    router.delete(route("settings.nmi.ips.domain.unassign", props.ip.id), {
        preserveScroll: true,
        onFinish: () => (domainProcessing.value = false),
    });
};

// The name of the IP: the name the mail server gives itself when it sends from this IP,
// and the one the IP's PTR record has to point to
const hostnameInput = ref(props.ip.hostname || "");
watch(
    () => props.ip.hostname,
    (hostname) => (hostnameInput.value = hostname || ""),
);
const savingHostname = ref(false);
const hostnameError = computed(() => page.props.errors?.hostname);
const hostnameChanged = computed(() => hostnameInput.value.trim().toLowerCase() !== (props.ip.hostname || ""));

const saveHostname = () => {
    savingHostname.value = true;
    router.patch(
        route("settings.nmi.ips.update", props.ip.id),
        { hostname: hostnameInput.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => (ptrCheck.value = null),
            onFinish: () => (savingHostname.value = false),
        },
    );
};

// The hosting provider of the server the IP belongs to, with its instructions
const providerInput = ref(props.ip.provider || "manual");
watch(
    () => props.ip.provider,
    (provider) => (providerInput.value = provider || "manual"),
);
const hostingProvider = computed(() => props.hostingProviders.find((provider) => provider.key === props.ip.provider) || null);
const saveProvider = () => {
    router.patch(route("settings.nmi.ips.update", props.ip.id), { provider: providerInput.value }, { preserveScroll: true });
};

// PTR record of the IP (set at the provider of the server): checked on request
const checkingPtr = ref(false);
const ptrCheck = ref(null);
const ptrOk = computed(() => (ptrCheck.value ? ptrCheck.value.ok : props.ip.ptr_verified));

const verifyPtr = async () => {
    checkingPtr.value = true;
    ptrCheck.value = null;
    try {
        const response = await axios.post(route("settings.nmi.ips.ptr.verify", props.ip.id));
        ptrCheck.value = response.data.data;
    } catch (error) {
        ptrCheck.value = {
            ok: false,
            message: error.response?.data?.message || t("nmi.sending_ip.ptr_check_failed"),
        };
    } finally {
        checkingPtr.value = false;
    }
};

// Whether the mail server has this address (it reports its addresses): undefined while
// asking, null when not known
const mtaAddresses = ref(undefined);
const isIpv6 = computed(() => (props.ip.ip_address || "").includes(":"));
const onServer = computed(() =>
    Array.isArray(mtaAddresses.value) ? mtaAddresses.value.includes(props.ip.normalized_ip) : null,
);

onMounted(async () => {
    try {
        const response = await axios.get(route("settings.nmi.mta.status"));
        mtaAddresses.value = response.data.data.addresses ?? null;
    } catch (error) {
        mtaAddresses.value = null;
    }
});

const serverBoxClass = computed(() => {
    if (mtaAddresses.value === undefined) return "border-gray-200 bg-gray-50 text-gray-600 dark:border-slate-700 dark:bg-slate-900/40 dark:text-gray-300";
    if (!isIpv6.value && onServer.value === true) return "border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-200";
    return "border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200";
});

// Reputation score color
const getReputationColor = (score) => {
    if (score >= 80) return { text: "text-emerald-500", bg: "bg-emerald-500" };
    if (score >= 60) return { text: "text-amber-500", bg: "bg-amber-500" };
    return { text: "text-rose-500", bg: "bg-rose-500" };
};

// Actions
const startingWarming = ref(false);
const generatingDkim = ref(false);
const checkingBlacklist = ref(false);

const startWarming = () => {
    startingWarming.value = true;
    router.post(route("settings.nmi.ips.warming.start", props.ip.id), {}, {
        onFinish: () => startingWarming.value = false,
    });
};

const generateDkim = () => {
    generatingDkim.value = true;
    router.post(route("settings.nmi.ips.dkim.generate", props.ip.id), {}, {
        onFinish: () => generatingDkim.value = false,
    });
};

const checkBlacklist = () => {
    checkingBlacklist.value = true;
    router.post(route("settings.nmi.ips.blacklist.check", props.ip.id), {}, {
        onFinish: () => checkingBlacklist.value = false,
    });
};

// The DKIM check answers JSON (an Inertia visit would show it raw in a dialog)
const verifyingDkim = ref(false);
const dkimCheck = ref(null);
const verifyDkim = async () => {
    verifyingDkim.value = true;
    dkimCheck.value = null;
    try {
        const response = await axios.get(route("settings.nmi.ips.dkim.verify", props.ip.id));
        dkimCheck.value = response.data.data;
    } catch (error) {
        dkimCheck.value = {
            verified: false,
            message: error.response?.data?.message || t("nmi.dkim_check_failed"),
        };
    } finally {
        verifyingDkim.value = false;
    }
};

// Copy to clipboard
const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text);
};
</script>

<template>
    <Head :title="`IP: ${ip.ip_address}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('settings.nmi.dashboard')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200 font-mono">
                        {{ ip.ip_address }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ ip.hostname || $t("nmi.no_hostname") }}
                    </p>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Status Cards Row -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Warming Status -->
                <div class="rounded-xl border bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $t("nmi.warming_status") }}</span>
                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium"
                            :class="getWarmingColor(ip.warming_status).bg + ' ' + getWarmingColor(ip.warming_status).text">
                            {{ warmingLabel(ip.warming_status) }}
                        </span>
                    </div>
                    <div v-if="ip.warming_status === 'warming'" class="mt-4">
                        <div class="flex items-center justify-between text-sm mb-2">
                            <span class="text-gray-600 dark:text-gray-400">{{ $t("nmi.day") }} {{ warming.current_day || 1 }} / {{ warming.total_days || 28 }}</span>
                            <span class="font-medium" :class="getWarmingColor('warming').text">{{ ip.warming_progress }}%</span>
                        </div>
                        <div class="h-2 rounded-full bg-gray-200 dark:bg-slate-600">
                            <div class="h-2 rounded-full bg-amber-500 transition-all" :style="{ width: ip.warming_progress + '%' }"></div>
                        </div>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            {{ $t("nmi.daily_limit") }}: {{ warming.daily_limit?.toLocaleString() || 0 }} {{ $t("nmi.emails") }}
                        </p>
                    </div>
                    <button
                        v-else-if="ip.warming_status === 'new'"
                        @click="startWarming"
                        :disabled="startingWarming"
                        class="mt-4 w-full rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-50"
                    >
                        {{ startingWarming ? $t("nmi.starting") : $t("nmi.start_warming") }}
                    </button>
                </div>

                <!-- Reputation -->
                <div class="rounded-xl border bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $t("nmi.reputation") }}</span>
                    <div class="mt-4 flex items-end gap-2">
                        <span class="text-4xl font-bold" :class="getReputationColor(ip.reputation_score).text">
                            {{ ip.reputation_score }}
                        </span>
                        <span class="text-lg text-gray-400 mb-1">/ 100</span>
                    </div>
                    <div class="mt-3 h-2 rounded-full bg-gray-200 dark:bg-slate-600">
                        <div class="h-2 rounded-full transition-all"
                            :class="getReputationColor(ip.reputation_score).bg"
                            :style="{ width: ip.reputation_score + '%' }"></div>
                    </div>
                </div>

                <!-- Delivery Rate -->
                <div class="rounded-xl border bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $t("nmi.delivery_rate") }}</span>
                    <div class="mt-4 flex items-end gap-2">
                        <span class="text-4xl font-bold text-gray-900 dark:text-white">
                            {{ ip.delivery_rate || 0 }}%
                        </span>
                    </div>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        {{ ip.total_sent?.toLocaleString() || 0 }} {{ $t("nmi.emails_sent") }}
                    </p>
                </div>

                <!-- Blacklist Status -->
                <div class="rounded-xl border bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $t("nmi.blacklist_status") }}</span>
                        <span v-if="ip.is_blacklisted" class="inline-flex items-center gap-1 text-xs text-rose-500">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                            {{ $t("nmi.listed") }}
                        </span>
                        <span v-else class="inline-flex items-center gap-1 text-xs text-emerald-500">
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            {{ $t("nmi.clean") }}
                        </span>
                    </div>
                    <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                        {{ blacklist.checked_count || 0 }} / {{ blacklist.total_lists || 8 }} {{ $t("nmi.lists_checked") }}
                    </p>
                    <button
                        @click="checkBlacklist"
                        :disabled="checkingBlacklist"
                        class="mt-3 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-slate-600 dark:text-gray-300 dark:hover:bg-slate-700 disabled:opacity-50"
                    >
                        {{ checkingBlacklist ? $t("nmi.checking") : $t("nmi.check_now") }}
                    </button>
                </div>
            </div>

            <!-- Sending domain -->
            <div class="rounded-xl border bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-slate-700">
                    <h3 class="font-semibold text-gray-900 dark:text-white">{{ $t("nmi.ip_domain.title") }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t("nmi.ip_domain.description") }}</p>
                </div>
                <div class="space-y-4 p-6">
                    <div v-if="ip.domain" class="flex flex-wrap items-center gap-3">
                        <span class="text-lg font-semibold text-gray-900 dark:text-white">{{ ip.domain.domain }}</span>
                        <span
                            v-if="!ip.domain.cname_verified"
                            class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-400"
                        >
                            {{ $t("nmi.ip_domain.not_verified") }}
                        </span>
                        <span
                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="
                                ip.domain.nmi_enabled
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                    : 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-gray-300'
                            "
                        >
                            {{ ip.domain.nmi_enabled ? $t("nmi.ip_domain.nmi_on") : $t("nmi.ip_domain.nmi_off") }}
                        </span>
                        <Link
                            :href="route('deliverability.domains.show', ip.domain.id)"
                            class="text-sm text-indigo-600 hover:underline dark:text-indigo-400"
                        >
                            {{ $t("nmi.ip_domain.open_domain") }}
                        </Link>
                    </div>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400">{{ $t("nmi.ip_domain.none") }}</p>

                    <p
                        v-if="ipInUse"
                        class="rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-900/20 dark:text-amber-200"
                    >
                        {{ $t("nmi.ip_domain.in_use", { count: ip.mailboxes_count }) }}
                    </p>

                    <div v-if="domains.length" class="flex flex-wrap items-center gap-3">
                        <label for="ip-domain" class="sr-only">{{ $t("nmi.ip_domain.select") }}</label>
                        <select
                            id="ip-domain"
                            v-model="selectedDomainId"
                            :disabled="ipInUse || domainProcessing"
                            class="min-w-[14rem] rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-50 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                        >
                            <option value="" disabled>{{ $t("nmi.ip_domain.select") }}</option>
                            <option v-for="domain in domains" :key="domain.id" :value="domain.id">
                                {{ domain.domain }}{{ domain.cname_verified ? "" : " (" + $t("nmi.ip_domain.not_verified") + ")" }}
                            </option>
                        </select>
                        <button
                            type="button"
                            @click="assignDomain"
                            :disabled="ipInUse || domainProcessing || !selectedDomainId || selectedDomainId === ip.domain?.id"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{ ip.domain ? $t("nmi.ip_domain.move") : $t("nmi.ip_domain.assign") }}
                        </button>
                        <button
                            v-if="ip.domain"
                            type="button"
                            @click="unassignDomain"
                            :disabled="ipInUse || domainProcessing"
                            class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-gray-300 dark:hover:bg-slate-700"
                        >
                            {{ $t("nmi.ip_domain.unassign") }}
                        </button>
                    </div>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $t("nmi.ip_domain.no_domains") }}
                        <Link :href="route('deliverability.index')" class="text-indigo-600 hover:underline dark:text-indigo-400">
                            {{ $t("deliverability.title") }}
                        </Link>
                    </p>

                    <p v-if="domainError" class="text-sm text-rose-600">{{ domainError }}</p>
                    <p v-if="ip.domain && ip.dkim_configured" class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $t("nmi.ip_domain.dkim_note") }}
                    </p>
                </div>
            </div>

            <!-- Sending from this IP: its name, PTR record, and whether the mail server has it -->
            <div class="rounded-xl border bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-slate-700">
                    <h3 class="font-semibold text-gray-900 dark:text-white">{{ $t("nmi.sending_ip.title") }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t("nmi.sending_ip.description") }}</p>
                </div>
                <div class="space-y-6 p-6">
                    <div>
                        <label for="ip-hostname" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $t("nmi.sending_ip.name_label") }}
                        </label>
                        <div class="mt-1 flex flex-wrap gap-2">
                            <input
                                id="ip-hostname"
                                v-model="hostnameInput"
                                type="text"
                                autocomplete="off"
                                placeholder="mail1.example.com"
                                class="min-w-0 flex-1 rounded-lg border-gray-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                                @keydown.enter.prevent="hostnameChanged && saveHostname()"
                            />
                            <button
                                type="button"
                                @click="saveHostname"
                                :disabled="savingHostname || !hostnameChanged"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {{ savingHostname ? $t("common.saving") : $t("common.save") }}
                            </button>
                        </div>
                        <p v-if="hostnameError" class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ hostnameError }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t("nmi.sending_ip.name_help") }}</p>
                    </div>

                    <div>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t("nmi.sending_ip.ptr_label") }}</span>
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="
                                        ptrOk
                                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                            : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                                    "
                                >
                                    {{ ptrOk ? $t("nmi.sending_ip.ptr_ok") : $t("nmi.sending_ip.ptr_not_ok") }}
                                </span>
                            </div>
                            <button
                                type="button"
                                @click="verifyPtr"
                                :disabled="checkingPtr || !ip.hostname"
                                class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-slate-600 dark:text-gray-300 dark:hover:bg-slate-700 disabled:opacity-50"
                            >
                                {{ checkingPtr ? $t("nmi.checking") : $t("nmi.sending_ip.verify_ptr") }}
                            </button>
                        </div>
                        <p
                            v-if="ptrCheck?.message"
                            class="mt-2 text-sm"
                            :class="ptrCheck.ok ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400'"
                        >
                            {{ ptrCheck.message }}
                        </p>
                        <p v-if="ptrCheck && !ptrCheck.ok && ptrCheck.ptr" class="mt-1 font-mono text-xs text-gray-500 dark:text-gray-400">
                            {{ $t("nmi.sending_ip.ptr_now", { ptr: ptrCheck.ptr }) }}
                        </p>
                        <ol v-if="!ptrOk && ip.hostname" class="mt-3 list-decimal space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-400">
                            <li>{{ $t("nmi.sending_ip.step_a_record", { name: ip.hostname, ip: ip.ip_address }) }}</li>
                            <li>{{ $t("nmi.sending_ip.step_ptr", { name: ip.hostname, ip: ip.ip_address }) }}</li>
                        </ol>
                    </div>

                    <div>
                        <label for="ip-provider" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $t("nmi.add_ip.provider") }}
                        </label>
                        <select
                            id="ip-provider"
                            v-model="providerInput"
                            @change="saveProvider"
                            class="mt-1 block w-full max-w-sm rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                        >
                            <option v-for="provider in hostingProviders" :key="provider.key" :value="provider.key">{{ provider.name }}</option>
                            <option value="manual">{{ $t("nmi.add_ip.provider_other") }}</option>
                        </select>
                        <div v-if="hostingProvider" class="mt-3 rounded-lg border border-gray-200 p-4 text-sm dark:border-slate-700">
                            <p class="font-medium text-gray-900 dark:text-white">{{ $t("nmi.sending_ip.at_provider", { provider: hostingProvider.name }) }}</p>
                            <ul class="mt-2 space-y-1">
                                <li v-if="hostingProvider.links.extra_ips">
                                    <a :href="hostingProvider.links.extra_ips" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline dark:text-indigo-400">
                                        {{ $t("nmi.hosting.link_extra_ips") }}
                                    </a>
                                </li>
                                <li v-if="hostingProvider.links.configure">
                                    <a :href="hostingProvider.links.configure" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline dark:text-indigo-400">
                                        {{ $t("nmi.hosting.link_configure") }}
                                    </a>
                                </li>
                                <li v-if="hostingProvider.links.ptr">
                                    <a :href="hostingProvider.links.ptr" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline dark:text-indigo-400">
                                        {{ $t("nmi.hosting.link_ptr") }}
                                    </a>
                                </li>
                                <li v-if="hostingProvider.links.port25">
                                    <a :href="hostingProvider.links.port25" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline dark:text-indigo-400">
                                        {{ $t("nmi.hosting.link_port25") }}
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="rounded-lg border p-4 text-sm" :class="serverBoxClass">
                        <template v-if="mtaAddresses === undefined">{{ $t("nmi.mta_status.checking") }}</template>
                        <template v-else-if="isIpv6">{{ $t("nmi.sending_ip.ipv6") }}</template>
                        <template v-else-if="onServer === true">{{ $t("nmi.sending_ip.on_server") }}</template>
                        <template v-else-if="onServer === false">{{ $t("nmi.sending_ip.not_on_server") }}</template>
                        <template v-else>{{ $t("nmi.sending_ip.server_unknown") }}</template>
                    </div>
                </div>
            </div>

            <!-- DKIM Configuration -->
            <div class="rounded-xl border bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-slate-700">
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ $t("nmi.dkim_config") }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t("nmi.dkim_description") }}</p>
                    </div>
                    <span v-if="ip.dkim_configured" class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-3 py-1 text-sm font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        {{ $t("nmi.configured") }}
                    </span>
                </div>

                <div class="p-6">
                    <div v-if="ip.dkim_configured && dkim" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t("nmi.dns_name") }}</label>
                            <div class="mt-1 flex items-center gap-2">
                                <code class="flex-1 rounded-lg bg-gray-100 px-3 py-2 text-sm dark:bg-slate-700">{{ dkim.name }}</code>
                                <button @click="copyToClipboard(dkim.name)" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-slate-700 dark:hover:text-gray-200">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t("nmi.dns_value") }}</label>
                            <div class="mt-1 flex items-start gap-2">
                                <code class="flex-1 rounded-lg bg-gray-100 px-3 py-2 text-xs break-all dark:bg-slate-700">{{ dkim.value }}</code>
                                <button @click="copyToClipboard(dkim.value)" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-slate-700 dark:hover:text-gray-200">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 pt-2">
                            <button type="button" @click="verifyDkim" :disabled="verifyingDkim"
                                class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-slate-600 dark:text-gray-300 dark:hover:bg-slate-700">
                                {{ verifyingDkim ? $t("nmi.checking") : $t("nmi.verify_dkim") }}
                            </button>
                            <button @click="generateDkim" :disabled="generatingDkim"
                                class="inline-flex items-center gap-2 rounded-lg border border-amber-500 px-4 py-2 text-sm font-medium text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 disabled:opacity-50">
                                {{ generatingDkim ? $t("nmi.generating") : $t("nmi.rotate_keys") }}
                            </button>
                        </div>
                        <p
                            v-if="dkimCheck"
                            class="text-sm"
                            :class="dkimCheck.verified ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600'"
                        >
                            {{ dkimCheck.message }}
                        </p>
                    </div>
                    <div v-else class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                        <p class="mt-4 text-gray-500 dark:text-gray-400">{{ $t("nmi.dkim_not_configured") }}</p>
                        <p v-if="!ip.domain" class="mt-2 text-sm text-amber-700 dark:text-amber-400">{{ $t("nmi.dkim_needs_domain") }}</p>
                        <button @click="generateDkim" :disabled="generatingDkim || !ip.domain"
                            class="mt-4 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50">
                            {{ generatingDkim ? $t("nmi.generating") : $t("nmi.generate_dkim") }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Sending Statistics -->
            <div class="rounded-xl border bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-slate-700">
                    <h3 class="font-semibold text-gray-900 dark:text-white">{{ $t("nmi.sending_stats") }}</h3>
                </div>
                <div class="grid gap-4 p-6 sm:grid-cols-4">
                    <div class="text-center">
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ ip.total_sent?.toLocaleString() || 0 }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t("nmi.total_sent") }}</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ ip.total_delivered?.toLocaleString() || 0 }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t("nmi.delivered") }}</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-rose-600 dark:text-rose-400">{{ ip.total_bounced?.toLocaleString() || 0 }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t("nmi.bounced") }}</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ ip.total_complaints?.toLocaleString() || 0 }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t("nmi.complaints") }}</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
