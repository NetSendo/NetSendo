<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import { useI18n } from "vue-i18n";
import axios from "axios";
import { requireGold } from "@/Composables/useLicense";

const { t } = useI18n();

const props = defineProps({
    ip: { type: Object, required: true },
    warming: { type: Object, default: () => ({}) },
    blacklist: { type: Object, default: () => ({}) },
    dkim: { type: Object, default: () => ({}) },
    // Where the address came from and its reverse DNS (NmiController::formatIpSetup)
    setup: { type: Object, default: () => ({}) },
});

// ---- Address setup: reverse DNS, and giving the address back ----
const PROVIDER_NAMES = { vultr: "Vultr", linode: "Linode", digitalocean: "DigitalOcean" };

const setupState = ref({ ...props.setup });
const ptrBusy = ref(null);
const ptrMessage = ref(null);

// "check" looks the record up; "set" writes it at the provider first (ordered addresses)
const ptrAction = async (action) => {
    if (ptrBusy.value) return;

    ptrBusy.value = action;
    ptrMessage.value = null;
    try {
        const response = await axios.post(route(`settings.nmi.ips.ptr.${action}`, props.ip.id));
        setupState.value = response.data.data.setup;
        ptrMessage.value = { text: response.data.message, ok: response.data.data.check.verified };
    } catch (error) {
        if (error.response?.status === 402) {
            requireGold("mail_infrastructure");
        } else {
            ptrMessage.value = { text: error.response?.data?.message || t("common.error"), ok: false };
        }
    } finally {
        ptrBusy.value = null;
    }
};

const showDelete = ref(false);
const deleting = ref(false);
const deleteError = ref(null);

const deleteIp = async (keepAtProvider = false) => {
    deleting.value = true;
    try {
        await axios.delete(route("settings.nmi.ips.destroy", props.ip.id), {
            data: { keep_at_provider: keepAtProvider },
        });
        router.visit(route("settings.nmi.dashboard"));
    } catch (error) {
        deleteError.value = {
            text: error.response?.data?.message || t("common.error"),
            // The provider refused to take the address back: it can still be removed here
            releaseFailed: error.response?.data?.release_failed === true,
        };
    } finally {
        deleting.value = false;
    }
};

// Warming status colors
const warmingColors = {
    new: { bg: "bg-slate-500/20", text: "text-slate-400", label: "New" },
    cold: { bg: "bg-slate-500/20", text: "text-slate-400", label: "Cold" },
    warming: { bg: "bg-amber-500/20", text: "text-amber-500", label: "Warming" },
    warmed: { bg: "bg-emerald-500/20", text: "text-emerald-500", label: "Warmed" },
    paused: { bg: "bg-rose-500/20", text: "text-rose-500", label: "Paused" },
};

const getWarmingColor = (status) => warmingColors[status] || warmingColors.cold;

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
            <!-- Address setup: where it came from, reverse DNS -->
            <div class="rounded-xl border bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800" data-test="nmi-ip-setup">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $t("nmi.setup.title") }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $t("nmi.setup.source") }}:
                            {{ setupState.managed ? $t("nmi.setup.source_provider", { provider: PROVIDER_NAMES[setupState.provider] || setupState.provider }) : $t("nmi.setup.source_manual") }}
                            <template v-if="setupState.region"> · {{ $t("nmi.setup.region") }}: {{ setupState.region }}</template>
                            <template v-if="setupState.bind_address"> · {{ $t("nmi.setup.bind_address") }}: <span class="font-mono">{{ setupState.bind_address }}</span></template>
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="showDelete = true; deleteError = null"
                        class="rounded-lg border border-rose-300 px-3 py-1.5 text-sm font-medium text-rose-700 hover:bg-rose-50 dark:border-rose-800 dark:text-rose-300 dark:hover:bg-rose-900/20"
                    >
                        {{ $t("nmi.delete_ip.button") }}
                    </button>
                </div>

                <div class="mt-4 rounded-lg border border-gray-200 p-4 dark:border-slate-700">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $t("nmi.setup.ptr_title") }}</span>
                        <span
                            class="rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="setupState.ptr_verified
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                                : setupState.ptr_record
                                  ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200'
                                  : 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-gray-300'"
                        >
                            {{ setupState.ptr_verified ? $t("nmi.setup.ptr_ok") : setupState.ptr_record ? $t("nmi.setup.ptr_wrong") : $t("nmi.setup.ptr_unchecked") }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t("nmi.setup.ptr_help") }}</p>

                    <dl class="mt-3 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">{{ $t("nmi.setup.ptr_expected") }}</dt>
                            <dd class="font-mono text-gray-900 dark:text-white">{{ setupState.hostname || $t("nmi.no_hostname") }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">{{ $t("nmi.setup.ptr_found") }}</dt>
                            <dd class="font-mono text-gray-900 dark:text-white">{{ setupState.ptr_record || $t("nmi.setup.ptr_none") }}</dd>
                        </div>
                    </dl>

                    <p v-if="!setupState.can_set_ptr && !setupState.ptr_verified" class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        {{ $t("nmi.setup.ptr_manual") }}
                    </p>
                    <p
                        v-if="ptrMessage"
                        class="mt-3 text-sm"
                        :class="ptrMessage.ok ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-300'"
                        role="status"
                    >
                        {{ ptrMessage.text }}
                    </p>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-if="setupState.can_set_ptr"
                            type="button"
                            :disabled="ptrBusy !== null"
                            @click="ptrAction('set')"
                            class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                        >
                            {{ ptrBusy === 'set' ? $t("nmi.setup.checking") : $t("nmi.setup.set_ptr") }}
                        </button>
                        <button
                            type="button"
                            :disabled="ptrBusy !== null"
                            @click="ptrAction('check')"
                            class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-slate-600 dark:text-gray-200 dark:hover:bg-slate-700"
                        >
                            {{ ptrBusy === 'check' ? $t("nmi.setup.checking") : $t("nmi.setup.check_ptr") }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Delete the address -->
            <Teleport to="body">
                <div v-if="showDelete" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
                    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800" data-test="nmi-ip-delete">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $t("nmi.delete_ip.title") }}</h3>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                            {{ $t("nmi.delete_ip.confirm") }} <span class="font-mono">{{ ip.ip_address }}</span>
                        </p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ setupState.managed ? $t("nmi.delete_ip.release_note") : $t("nmi.delete_ip.warning") }}
                        </p>
                        <p v-if="deleteError" class="mt-3 rounded-lg bg-rose-50 p-3 text-sm text-rose-700 dark:bg-rose-900/30 dark:text-rose-300" role="alert">
                            {{ deleteError.text }}
                        </p>
                        <div class="mt-6 flex flex-wrap justify-end gap-3">
                            <button
                                type="button"
                                @click="showDelete = false"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-slate-600 dark:text-gray-300 dark:hover:bg-slate-700"
                            >
                                {{ $t("common.cancel") }}
                            </button>
                            <button
                                v-if="deleteError?.releaseFailed"
                                type="button"
                                :disabled="deleting"
                                @click="deleteIp(true)"
                                class="rounded-lg border border-rose-300 px-4 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50 disabled:opacity-50 dark:border-rose-800 dark:text-rose-300"
                            >
                                {{ $t("nmi.delete_ip.keep_at_provider") }}
                            </button>
                            <button
                                type="button"
                                :disabled="deleting"
                                @click="deleteIp(false)"
                                class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700 disabled:opacity-50"
                            >
                                {{ deleting ? $t("nmi.delete_ip.deleting") : $t("nmi.delete_ip.button") }}
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>

            <!-- Status Cards Row -->
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Warming Status -->
                <div class="rounded-xl border bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ $t("nmi.warming_status") }}</span>
                        <span class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium"
                            :class="getWarmingColor(ip.warming_status).bg + ' ' + getWarmingColor(ip.warming_status).text">
                            {{ getWarmingColor(ip.warming_status).label }}
                        </span>
                    </div>
                    <div v-if="ip.warming_status === 'warming'" class="mt-4">
                        <div class="flex items-center justify-between text-sm mb-2">
                            <span class="text-gray-600 dark:text-gray-400">{{ $t("nmi.day") }} {{ warming.current_day || 1 }} / 28</span>
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
                        v-else-if="ip.warming_status === 'new' || ip.warming_status === 'cold'"
                        @click="startWarming"
                        :disabled="startingWarming"
                        class="mt-4 w-full rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:opacity-50"
                    >
                        {{ startingWarming ? $t("common.starting") : $t("nmi.start_warming") }}
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
                        {{ checkingBlacklist ? $t("common.checking") : $t("nmi.check_now") }}
                    </button>
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
                    <div v-if="ip.dkim_configured && dkim.dns_record" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t("nmi.dns_name") }}</label>
                            <div class="mt-1 flex items-center gap-2">
                                <code class="flex-1 rounded-lg bg-gray-100 px-3 py-2 text-sm dark:bg-slate-700">{{ dkim.dns_name }}</code>
                                <button @click="copyToClipboard(dkim.dns_name)" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-slate-700 dark:hover:text-gray-200">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t("nmi.dns_value") }}</label>
                            <div class="mt-1 flex items-start gap-2">
                                <code class="flex-1 rounded-lg bg-gray-100 px-3 py-2 text-xs break-all dark:bg-slate-700">{{ dkim.dns_record }}</code>
                                <button @click="copyToClipboard(dkim.dns_record)" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-slate-700 dark:hover:text-gray-200">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 pt-2">
                            <Link :href="route('settings.nmi.ips.dkim.verify', ip.id)"
                                class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-slate-600 dark:text-gray-300 dark:hover:bg-slate-700">
                                {{ $t("nmi.verify_dkim") }}
                            </Link>
                            <button @click="generateDkim" :disabled="generatingDkim"
                                class="inline-flex items-center gap-2 rounded-lg border border-amber-500 px-4 py-2 text-sm font-medium text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 disabled:opacity-50">
                                {{ generatingDkim ? $t("common.generating") : $t("nmi.rotate_keys") }}
                            </button>
                        </div>
                    </div>
                    <div v-else class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                        <p class="mt-4 text-gray-500 dark:text-gray-400">{{ $t("nmi.dkim_not_configured") }}</p>
                        <button @click="generateDkim" :disabled="generatingDkim"
                            class="mt-4 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                            {{ generatingDkim ? $t("common.generating") : $t("nmi.generate_dkim") }}
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
