<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { ref, computed, onMounted } from "vue";
import { useI18n } from "vue-i18n";
import axios from "axios";
import GoldNotice from "@/Components/License/GoldNotice.vue";
import MtaReadiness from "@/Components/Nmi/MtaReadiness.vue";
import { useLicense } from "@/Composables/useLicense";

// NMI is GOLD: without it the setup stays readable, changes answer 402 (upgrade dialog)
const { can } = useLicense();

const { t } = useI18n();

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    pools: { type: Array, default: () => [] },
    ips: { type: Array, default: () => [] },
    nmiEnabled: { type: Boolean, default: false },
});

// MTA Status state
const mtaStatus = ref({
    online: null,
    checking: true,
    message: "",
});

const checkMtaStatus = async () => {
    mtaStatus.value.checking = true;
    try {
        const response = await axios.get(route("settings.nmi.mta.status"));
        mtaStatus.value = {
            online: response.data.data.online,
            message: response.data.data.message,
            checking: false,
            // Port 25, public address, PTR (MtaReadiness)
            data: response.data.data,
        };
    } catch (error) {
        mtaStatus.value = {
            online: false,
            message: "Failed to check MTA status",
            checking: false,
        };
    }
};

onMounted(() => {
    checkMtaStatus();
});

// Warming status colors
const warmingColors = {
    cold: { bg: "bg-slate-500/20", text: "text-slate-400", dot: "bg-slate-400" },
    warming: { bg: "bg-amber-500/20", text: "text-amber-500", dot: "bg-amber-500" },
    warmed: { bg: "bg-emerald-500/20", text: "text-emerald-500", dot: "bg-emerald-500" },
    paused: { bg: "bg-rose-500/20", text: "text-rose-500", dot: "bg-rose-500" },
};

const getWarmingColor = (status) => warmingColors[status] || warmingColors.cold;

// Reputation score color
const getReputationColor = (score) => {
    if (score >= 80) return "text-emerald-500";
    if (score >= 60) return "text-amber-500";
    return "text-rose-500";
};

// Create pool modal
const showCreatePool = ref(false);
const poolForm = ref({
    name: "",
    type: "dedicated",
    description: "",
});
const creating = ref(false);

const createPool = async () => {
    creating.value = true;
    router.post(route("settings.nmi.pools.store"), poolForm.value, {
        onSuccess: () => {
            showCreatePool.value = false;
            poolForm.value = { name: "", type: "dedicated", description: "" };
        },
        onFinish: () => {
            creating.value = false;
        },
    });
};

// Add IP modal
const showAddIp = ref(false);
const selectedPoolId = ref(null);
const addIpForm = ref({
    ip_address: "",
    hostname: "",
    description: "",
});
const addingIp = ref(false);
const addIpError = ref("");

const openAddIpModal = (poolId) => {
    selectedPoolId.value = poolId;
    addIpForm.value = { ip_address: "", hostname: "", description: "" };
    addIpError.value = "";
    showAddIp.value = true;
};

const addIpToPool = async () => {
    addingIp.value = true;
    addIpError.value = "";

    try {
        await axios.post(route("settings.nmi.pools.ips.store", selectedPoolId.value), addIpForm.value);
        showAddIp.value = false;
        router.reload({ only: ["pools", "ips", "stats"] });
    } catch (error) {
        addIpError.value = error.response?.data?.message || t("common.error");
    } finally {
        addingIp.value = false;
    }
};

// ---- IP providers: companies whose API can order an address for the server ----
// vultr and linode can order; digitalocean cannot be used for mail (it is listed so an
// old connection can be removed); "other" is the manual route.
const ORDERABLE_PROVIDERS = ["vultr", "linode"];
const GUIDE_STEPS = { vultr: 5, linode: 6, digitalocean: 1, other: 5 };

// Order an address (modal)
const showProvision = ref(false);
const provisionPoolId = ref(null);
const providers = ref([]);
const servers = ref([]);
const provisionForm = ref({ provider: "", server_id: "", hostname: "" });
const provisioning = ref(false);
const provisionError = ref("");
const loadingProviders = ref(false);
const loadingServers = ref(false);
const provisioned = ref(null);

// Connected providers that can order an address
const loadProviders = async () => {
    loadingProviders.value = true;
    try {
        const response = await axios.get(route("settings.nmi.providers.index"));
        providers.value = Object.entries(response.data.data.providers)
            .filter(([id, p]) => p.configured && ORDERABLE_PROVIDERS.includes(id))
            .map(([id, p]) => ({ id, name: p.name }));
    } catch (error) {
        providers.value = [];
    } finally {
        loadingProviders.value = false;
    }
};

const loadServers = async (provider) => {
    servers.value = [];
    provisionForm.value.server_id = "";
    if (!provider) return;

    loadingServers.value = true;
    provisionError.value = "";
    try {
        const response = await axios.get(route("settings.nmi.providers.servers", provider));
        servers.value = response.data.data.servers;
        // One server in the account: that is the one
        if (servers.value.length === 1) {
            provisionForm.value.server_id = servers.value[0].id;
        }
    } catch (error) {
        provisionError.value = error.response?.data?.message || t("common.error");
    } finally {
        loadingServers.value = false;
    }
};

const serverLabel = (server) =>
    [
        server.label,
        server.region,
        server.addresses?.[0],
        server.smtp_restricted ? t("nmi.provision.smtp_restricted") : null,
    ]
        .filter(Boolean)
        .join(" · ");

const openProvisionModal = (poolId) => {
    provisionPoolId.value = poolId;
    provisionForm.value = { provider: "", server_id: "", hostname: "" };
    provisionError.value = "";
    provisioned.value = null;
    servers.value = [];
    showProvision.value = true;
    loadProviders();
};

const closeProvisionModal = () => {
    showProvision.value = false;
    if (provisioned.value) {
        router.reload({ only: ["pools", "ips", "stats"] });
    }
};

const provisionIp = async () => {
    provisioning.value = true;
    provisionError.value = "";

    try {
        const response = await axios.post(
            route("settings.nmi.pools.provision", provisionPoolId.value),
            provisionForm.value,
        );
        provisioned.value = {
            ...response.data.data,
            provider: provisionForm.value.provider,
            hostname: provisionForm.value.hostname.trim().toLowerCase(),
        };
    } catch (error) {
        const data = error.response?.data;
        provisionError.value =
            Object.values(data?.errors || {})[0]?.[0] || data?.message || t("common.error");
    } finally {
        provisioning.value = false;
    }
};

// What is left to do after an address was ordered, in order
const nextSteps = computed(() => {
    const ip = provisioned.value;
    if (!ip) return [];

    const values = { ip: ip.ip_address, hostname: ip.hostname };
    const keys = ["a_record"];
    if (ip.provider === "vultr") keys.push("vultr_os");
    if (ip.provider === "linode") {
        if (!ip.reverse_dns?.set) keys.push("linode_ptr");
        keys.push("linode_os");
    }
    keys.push("host_network", "finish");

    return keys.map((key) => t(`nmi.provision.next.${key}`, values));
});

const onProviderChange = (provider) => loadServers(provider);

// Configure providers (modal)
const showProviderSettings = ref(false);
const providerSettings = ref({});
const loadingSettings = ref(false);
const savingProvider = ref(null);
const providerApiKeys = ref({ vultr: "", linode: "" });
const providerErrors = ref({});
const providerAccounts = ref({});

const openProviderSettings = async () => {
    showProviderSettings.value = true;
    loadingSettings.value = true;
    providerApiKeys.value = { vultr: "", linode: "" };
    providerErrors.value = {};

    try {
        const response = await axios.get(route("settings.nmi.providers.index"));
        providerSettings.value = response.data.data.providers;
    } catch (error) {
        providerSettings.value = {};
    } finally {
        loadingSettings.value = false;
    }
};

// The key is checked with the provider before it is stored: a refusal comes back here
const saveProviderApiKey = async (provider) => {
    savingProvider.value = provider;
    providerErrors.value = { ...providerErrors.value, [provider]: "" };

    try {
        const response = await axios.post(route("settings.nmi.providers.store"), {
            provider: provider,
            api_key: providerApiKeys.value[provider],
            enabled: true,
        });
        providerSettings.value = response.data.data.providers;
        providerAccounts.value = {
            ...providerAccounts.value,
            [provider]: response.data.data.account?.name || response.data.data.account?.email || "",
        };
        providerApiKeys.value[provider] = "";
    } catch (error) {
        providerErrors.value = {
            ...providerErrors.value,
            [provider]: error.response?.data?.message || t("common.error"),
        };
    } finally {
        savingProvider.value = null;
    }
};

const removeProvider = async (provider) => {
    savingProvider.value = provider;

    try {
        const response = await axios.post(route("settings.nmi.providers.store"), {
            provider: provider,
            api_key: "",
            enabled: false,
        });
        providerSettings.value = response.data.data.providers;
        providerAccounts.value = { ...providerAccounts.value, [provider]: "" };
    } catch (error) {
        providerErrors.value = {
            ...providerErrors.value,
            [provider]: error.response?.data?.message || t("common.error"),
        };
    } finally {
        savingProvider.value = null;
    }
};
</script>

<template>
    <Head :title="$t('nmi.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {{ $t("nmi.title") }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $t("nmi.subtitle") }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <!-- MTA Status Indicator -->
                    <div class="flex items-center gap-2">
                        <template v-if="mtaStatus.checking">
                            <div class="h-2.5 w-2.5 animate-pulse rounded-full bg-gray-400"></div>
                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $t("nmi.mta_status.checking") }}</span>
                        </template>
                        <template v-else-if="mtaStatus.online">
                            <div class="h-2.5 w-2.5 rounded-full bg-emerald-500 animate-pulse"></div>
                            <span class="text-sm font-medium text-emerald-600 dark:text-emerald-400">{{ $t("nmi.mta_status.online") }}</span>
                        </template>
                        <template v-else>
                            <div class="h-2.5 w-2.5 rounded-full bg-rose-500"></div>
                            <span class="text-sm font-medium text-rose-600 dark:text-rose-400">{{ $t("nmi.mta_status.offline") }}</span>
                        </template>
                    </div>

                    <!-- Configure Providers Button -->
                    <button
                        @click="openProviderSettings"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-slate-600 dark:text-gray-300 dark:hover:bg-slate-700"
                        :title="$t('nmi.providers.configure')"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        {{ $t("nmi.providers.configure") }}
                    </button>

                    <button
                        @click="showCreatePool = true"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ $t("nmi.create_pool") }}
                    </button>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <GoldNotice
                v-if="!can('mail_infrastructure')"
                feature="mail_infrastructure"
                :message="$t('license.gating.nmi_locked')"
            />

            <!-- Can this server deliver: port 25, PTR, the SPF entry -->
            <MtaReadiness
                v-if="nmiEnabled && mtaStatus.online"
                :status="mtaStatus.data"
                @recheck="checkMtaStatus"
            />

            <!-- NMI Disabled Banner -->
            <div
                v-if="!nmiEnabled"
                class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-600 via-slate-700 to-slate-800 p-8 text-white shadow-xl"
            >
                <div class="absolute -right-10 -top-10 h-40 w-40 rounded-full bg-white/10"></div>
                <div class="relative z-10">
                    <div class="flex items-start justify-between gap-6">
                        <div class="flex-1">
                            <span class="inline-flex items-center gap-1 rounded-full bg-white/20 px-3 py-1 text-xs font-bold tracking-wide backdrop-blur-sm">
                                ⚙️ {{ $t("nmi.setup_required") }}
                            </span>
                            <h2 class="mt-4 text-3xl font-bold">{{ $t("nmi.banner.title") }}</h2>
                            <p class="mt-2 max-w-xl text-lg text-white/90">{{ $t("nmi.banner.description") }}</p>
                            <div class="mt-6 grid gap-3 sm:grid-cols-2">
                                <div class="flex items-center gap-2">
                                    <svg class="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    <span class="text-sm font-medium">{{ $t("nmi.features.dedicated_ips") }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <svg class="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    <span class="text-sm font-medium">{{ $t("nmi.features.ip_warming") }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <svg class="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    <span class="text-sm font-medium">{{ $t("nmi.features.dkim_signing") }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <svg class="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                    </svg>
                                    <span class="text-sm font-medium">{{ $t("nmi.features.blacklist_monitoring") }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="hidden lg:flex h-32 w-32 flex-shrink-0 items-center justify-center rounded-2xl bg-white/20 backdrop-blur-sm">
                            <svg class="h-20 w-20 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div v-if="nmiEnabled" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Total Pools -->
                <div class="rounded-xl border bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-100 dark:bg-indigo-900/30">
                            <svg class="h-6 w-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t("nmi.stats.pools") }}</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_pools || 0 }}</p>
                        </div>
                    </div>
                </div>

                <!-- Total IPs -->
                <div class="rounded-xl border bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-900/30">
                            <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t("nmi.stats.total_ips") }}</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ stats.total_ips || 0 }}</p>
                        </div>
                    </div>
                </div>

                <!-- Warming IPs -->
                <div class="rounded-xl border bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-900/30">
                            <svg class="h-6 w-6 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t("nmi.stats.warming") }}</p>
                            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ stats.warming_ips || 0 }}</p>
                        </div>
                    </div>
                </div>

                <!-- Avg Reputation -->
                <div class="rounded-xl border bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="flex items-center gap-4">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-900/30">
                            <svg class="h-6 w-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t("nmi.stats.reputation") }}</p>
                            <p class="text-2xl font-bold" :class="getReputationColor(stats.avg_reputation)">
                                {{ stats.avg_reputation || 100 }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- IP Pools -->
            <div class="rounded-xl border bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-slate-700">
                    <h3 class="font-semibold text-gray-900 dark:text-white">{{ $t("nmi.pools.title") }}</h3>
                </div>

                <div v-if="pools.length === 0" class="p-12 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <h3 class="mt-4 font-medium text-gray-900 dark:text-white">{{ $t("nmi.pools.empty.title") }}</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $t("nmi.pools.empty.description") }}</p>
                    <button
                        @click="showCreatePool = true"
                        class="mt-6 inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-700"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ $t("nmi.create_pool") }}
                    </button>
                </div>

                <div v-else class="divide-y divide-gray-200 dark:divide-slate-700">
                    <Link
                        v-for="pool in pools"
                        :key="pool.id"
                        :href="route('settings.nmi.pools.show', pool.id)"
                        class="flex items-center gap-4 px-6 py-4 transition-colors hover:bg-gray-50 dark:hover:bg-slate-700/50"
                    >
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/30">
                            <svg class="h-5 w-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-gray-900 dark:text-white truncate">{{ pool.name }}</p>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                    :class="pool.type === 'dedicated'
                                        ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                                        : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                                >
                                    {{ pool.type }}
                                </span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ pool.ip_addresses_count || 0 }} {{ $t("nmi.ips") }}
                                </span>
                            </div>
                        </div>
                        <!-- Add IP Button -->
                        <button
                            @click.prevent.stop="openAddIpModal(pool.id)"
                            class="hidden sm:inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 transition-colors hover:bg-gray-50 dark:border-slate-600 dark:text-gray-400 dark:hover:bg-slate-700"
                            :title="$t('nmi.add_ip.title')"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            {{ $t("nmi.add_ip.submit") }}
                        </button>
                        <!-- Provision IP Button -->
                        <button
                            @click.prevent.stop="openProvisionModal(pool.id)"
                            class="hidden sm:inline-flex items-center gap-1 rounded-lg bg-indigo-100 px-3 py-1.5 text-xs font-medium text-indigo-700 transition-colors hover:bg-indigo-200 dark:bg-indigo-900/30 dark:text-indigo-400 dark:hover:bg-indigo-900/50"
                            :title="$t('nmi.provision.title')"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z" />
                            </svg>
                            {{ $t("nmi.provision.submit") }}
                        </button>
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                </div>
            </div>

            <!-- Dedicated IPs -->
            <div class="rounded-xl border bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-slate-700">
                    <h3 class="font-semibold text-gray-900 dark:text-white">{{ $t("nmi.dedicated_ips.title") }}</h3>
                </div>

                <div v-if="ips.length === 0" class="p-8 text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t("nmi.dedicated_ips.empty") }}</p>
                </div>

                <div v-else class="divide-y divide-gray-200 dark:divide-slate-700">
                    <Link
                        v-for="ip in ips"
                        :key="ip.id"
                        :href="route('settings.nmi.ips.show', ip.id)"
                        class="flex items-center gap-4 px-6 py-4 transition-colors hover:bg-gray-50 dark:hover:bg-slate-700/50"
                    >
                        <!-- Warming status -->
                        <div class="flex h-10 w-10 items-center justify-center rounded-full" :class="getWarmingColor(ip.warming_status).bg">
                            <span class="h-3 w-3 rounded-full" :class="getWarmingColor(ip.warming_status).dot"></span>
                        </div>

                        <!-- IP info -->
                        <div class="flex-1 min-w-0">
                            <p class="font-mono font-medium text-gray-900 dark:text-white">{{ ip.ip_address }}</p>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ ip.hostname }}</span>
                                <span v-if="ip.is_blacklisted" class="inline-flex items-center gap-1 text-xs text-rose-500">
                                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                    Blacklisted
                                </span>
                            </div>
                        </div>

                        <!-- Warming progress -->
                        <div v-if="ip.warming_status === 'warming'" class="hidden sm:block w-32">
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="text-gray-500 dark:text-gray-400">Warming</span>
                                <span class="font-medium text-amber-600 dark:text-amber-400">{{ ip.warming_progress }}%</span>
                            </div>
                            <div class="h-1.5 rounded-full bg-gray-200 dark:bg-slate-600">
                                <div class="h-1.5 rounded-full bg-amber-500" :style="{ width: ip.warming_progress + '%' }"></div>
                            </div>
                        </div>

                        <!-- Reputation -->
                        <div class="hidden sm:flex flex-col items-end">
                            <span class="text-xs text-gray-500 dark:text-gray-400">Reputation</span>
                            <span class="font-bold" :class="getReputationColor(ip.reputation_score)">{{ ip.reputation_score }}</span>
                        </div>

                        <!-- DKIM status -->
                        <span v-if="ip.dkim_configured"
                            class="hidden sm:inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            DKIM
                        </span>

                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                </div>
            </div>
        </div>

        <!-- Create Pool Modal -->
        <Teleport to="body">
            <div v-if="showCreatePool" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
                <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800">
                    <button @click="showCreatePool = false" class="absolute right-4 top-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $t("nmi.create_pool") }}</h3>

                    <form @submit.prevent="createPool" class="mt-6 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t("nmi.pool_name") }}</label>
                            <input
                                v-model="poolForm.name"
                                type="text"
                                required
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t("nmi.pool_type") }}</label>
                            <select
                                v-model="poolForm.type"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            >
                                <option value="dedicated">{{ $t("nmi.type_dedicated") }}</option>
                                <option value="shared">{{ $t("nmi.type_shared") }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t("nmi.description") }}</label>
                            <textarea
                                v-model="poolForm.description"
                                rows="2"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            ></textarea>
                        </div>

                        <div class="flex justify-end gap-3 pt-4">
                            <button
                                type="button"
                                @click="showCreatePool = false"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-slate-600 dark:text-gray-300 dark:hover:bg-slate-700"
                            >
                                {{ $t("common.cancel") }}
                            </button>
                            <button
                                type="submit"
                                :disabled="creating"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {{ creating ? $t("common.creating") : $t("common.create") }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Add IP Modal -->
        <Teleport to="body">
            <div v-if="showAddIp" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
                <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800">
                    <button @click="showAddIp = false" class="absolute right-4 top-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $t("nmi.add_ip.title") }}</h3>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $t("nmi.add_ip.manual_hint") }}</p>

                    <form @submit.prevent="addIpToPool" class="mt-6 space-y-4">
                        <!-- Error Alert -->
                        <div v-if="addIpError" class="rounded-lg bg-rose-50 p-4 text-sm text-rose-700 dark:bg-rose-900/30 dark:text-rose-400">
                            {{ addIpError }}
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t("nmi.add_ip.ip_address") }}</label>
                            <input
                                v-model="addIpForm.ip_address"
                                type="text"
                                required
                                :placeholder="$t('nmi.add_ip.ip_address_placeholder')"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t("nmi.add_ip.hostname") }}</label>
                            <input
                                v-model="addIpForm.hostname"
                                type="text"
                                required
                                :placeholder="$t('nmi.add_ip.hostname_placeholder')"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t("nmi.add_ip.description") }}</label>
                            <textarea
                                v-model="addIpForm.description"
                                rows="2"
                                :placeholder="$t('nmi.add_ip.description_placeholder')"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                            ></textarea>
                        </div>

                        <div class="flex justify-end gap-3 pt-4">
                            <button
                                type="button"
                                @click="showAddIp = false"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-slate-600 dark:text-gray-300 dark:hover:bg-slate-700"
                            >
                                {{ $t("nmi.add_ip.cancel") }}
                            </button>
                            <button
                                type="submit"
                                :disabled="addingIp"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {{ addingIp ? $t("common.adding") : $t("nmi.add_ip.submit") }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Order an address from a provider -->
        <Teleport to="body">
            <div v-if="showProvision" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
                <div class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800" data-test="nmi-provision">
                    <button @click="closeProvisionModal" class="absolute right-4 top-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/30">
                            <svg class="h-5 w-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $t("nmi.provision.title") }}</h3>
                    </div>

                    <!-- Ordered: what is left to do -->
                    <div v-if="provisioned" class="mt-6 space-y-4" data-test="nmi-provisioned">
                        <div class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
                            <p class="font-medium">{{ $t("nmi.provision.done_title", { ip: provisioned.ip_address }) }}</p>
                            <p v-if="provisioned.reverse_dns?.set" class="mt-1">
                                {{ $t("nmi.provision.ptr_set", { hostname: provisioned.hostname }) }}
                            </p>
                        </div>
                        <div
                            v-if="provisioned.reverse_dns?.supported && !provisioned.reverse_dns?.set"
                            class="rounded-lg bg-amber-50 p-4 text-sm text-amber-800 dark:bg-amber-900/30 dark:text-amber-300"
                        >
                            {{ $t("nmi.provision.ptr_not_set", { reason: provisioned.reverse_dns.message }) }}
                        </div>

                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $t("nmi.provision.next_title") }}</p>
                            <ol class="mt-2 list-decimal space-y-2 pl-5 text-sm text-gray-700 dark:text-gray-300">
                                <li v-for="step in nextSteps" :key="step">{{ step }}</li>
                            </ol>
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <Link
                                :href="route('settings.nmi.ips.show', provisioned.id)"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-slate-600 dark:text-gray-300 dark:hover:bg-slate-700"
                            >
                                {{ $t("nmi.provision.open_ip") }}
                            </Link>
                            <button
                                type="button"
                                @click="closeProvisionModal"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                            >
                                {{ $t("nmi.provision.done") }}
                            </button>
                        </div>
                    </div>

                    <form v-else @submit.prevent="provisionIp" class="mt-6 space-y-4">
                        <!-- Error Alert -->
                        <div v-if="provisionError" class="rounded-lg bg-rose-50 p-4 text-sm text-rose-700 dark:bg-rose-900/30 dark:text-rose-400" role="alert">
                            {{ provisionError }}
                        </div>

                        <!-- No provider connected -->
                        <div v-if="!loadingProviders && providers.length === 0" class="rounded-lg bg-amber-50 p-4 text-sm text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                            {{ $t("nmi.provision.connect_first") }}
                        </div>

                        <template v-else>
                            <div>
                                <label for="provision-provider" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t("nmi.provision.provider") }}</label>
                                <select
                                    id="provision-provider"
                                    v-model="provisionForm.provider"
                                    @change="onProviderChange(provisionForm.provider)"
                                    required
                                    :disabled="loadingProviders"
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                >
                                    <option value="">{{ $t("nmi.provision.select_provider") }}</option>
                                    <option v-for="provider in providers" :key="provider.id" :value="provider.id">
                                        {{ provider.name }}
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label for="provision-server" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t("nmi.provision.server") }}</label>
                                <select
                                    id="provision-server"
                                    v-model="provisionForm.server_id"
                                    required
                                    :disabled="!provisionForm.provider || loadingServers"
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-60 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                >
                                    <option value="">
                                        {{ loadingServers ? $t("nmi.provision.loading_servers") : $t("nmi.provision.select_server") }}
                                    </option>
                                    <option v-for="server in servers" :key="server.id" :value="server.id">
                                        {{ serverLabel(server) }}
                                    </option>
                                </select>
                                <p
                                    v-if="provisionForm.provider && !loadingServers && !provisionError && servers.length === 0"
                                    class="mt-1 text-sm text-amber-700 dark:text-amber-300"
                                >
                                    {{ $t("nmi.provision.no_servers") }}
                                </p>
                            </div>

                            <div>
                                <label for="provision-hostname" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ $t("nmi.provision.hostname") }}</label>
                                <input
                                    id="provision-hostname"
                                    v-model="provisionForm.hostname"
                                    type="text"
                                    required
                                    autocomplete="off"
                                    :placeholder="$t('nmi.provision.hostname_placeholder')"
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $t("nmi.provision.hostname_help") }}</p>
                            </div>

                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t("nmi.provision.cost_note") }}</p>
                        </template>

                        <div class="flex justify-end gap-3 pt-4">
                            <button
                                type="button"
                                @click="closeProvisionModal"
                                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-slate-600 dark:text-gray-300 dark:hover:bg-slate-700"
                            >
                                {{ $t("nmi.provision.cancel") }}
                            </button>
                            <button
                                type="submit"
                                :disabled="provisioning || !provisionForm.provider || !provisionForm.server_id || !provisionForm.hostname"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {{ provisioning ? $t("nmi.provision.provisioning") : $t("nmi.provision.submit") }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Configure providers: connect an API key, with how to get one -->
        <Teleport to="body">
            <div v-if="showProviderSettings" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm">
                <div class="relative max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800" data-test="nmi-providers">
                    <button @click="showProviderSettings = false" class="absolute right-4 top-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/30">
                            <svg class="h-5 w-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $t("nmi.providers.settings_title") }}</h3>
                    </div>

                    <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">{{ $t("nmi.providers.intro") }}</p>

                    <div v-if="loadingSettings" class="mt-6 flex justify-center py-8">
                        <div class="h-8 w-8 animate-spin rounded-full border-4 border-gray-200 border-t-indigo-600"></div>
                    </div>

                    <div v-else class="mt-6 space-y-4">
                        <div
                            v-for="(settings, provider) in providerSettings"
                            :key="provider"
                            class="rounded-lg border border-gray-200 p-4 dark:border-slate-700"
                            :data-test="`nmi-provider-${provider}`"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ settings.name }}</span>
                                    <span v-if="!ORDERABLE_PROVIDERS.includes(provider)" class="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">
                                        {{ $t("nmi.providers.unavailable") }}
                                    </span>
                                    <span v-else-if="settings.configured" class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                                        ✓ {{ providerAccounts[provider] ? $t("nmi.providers.connected_as", { name: providerAccounts[provider] }) : $t("nmi.providers.connected") }}
                                    </span>
                                    <span v-else class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-slate-700 dark:text-gray-400">
                                        {{ $t("nmi.providers.not_connected") }}
                                    </span>
                                </div>
                                <button
                                    v-if="settings.configured"
                                    @click="removeProvider(provider)"
                                    :disabled="savingProvider === provider"
                                    class="shrink-0 text-sm text-rose-600 hover:text-rose-700 disabled:opacity-50"
                                >
                                    {{ $t("common.remove") }}
                                </button>
                            </div>

                            <div v-if="ORDERABLE_PROVIDERS.includes(provider) && !settings.configured" class="mt-3">
                                <label :for="`provider-key-${provider}`" class="sr-only">{{ $t("nmi.providers.api_key_label") }}</label>
                                <div class="flex gap-2">
                                    <input
                                        :id="`provider-key-${provider}`"
                                        v-model="providerApiKeys[provider]"
                                        type="password"
                                        autocomplete="off"
                                        :placeholder="$t('nmi.providers.api_key_placeholder')"
                                        class="min-w-0 flex-1 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700 dark:text-white"
                                    />
                                    <button
                                        @click="saveProviderApiKey(provider)"
                                        :disabled="!providerApiKeys[provider] || savingProvider === provider"
                                        class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                                    >
                                        {{ savingProvider === provider ? $t("common.saving") : $t("nmi.providers.connect") }}
                                    </button>
                                </div>
                            </div>

                            <p v-if="providerErrors[provider]" class="mt-2 text-sm text-rose-600 dark:text-rose-400" role="alert">
                                {{ providerErrors[provider] }}
                            </p>

                            <!-- How to connect this provider -->
                            <details class="mt-3" :open="!settings.configured && ORDERABLE_PROVIDERS.includes(provider)">
                                <summary class="cursor-pointer text-sm font-medium text-indigo-600 dark:text-indigo-400">
                                    {{ $t("nmi.providers.guide_title") }}
                                </summary>
                                <ol class="mt-2 space-y-1.5 pl-5 text-sm text-gray-700 dark:text-gray-300" :class="GUIDE_STEPS[provider] > 1 ? 'list-decimal' : 'list-none pl-0'">
                                    <li v-for="n in GUIDE_STEPS[provider] || 0" :key="n">
                                        {{ $t(`nmi.providers.guide.${provider}.s${n}`) }}
                                    </li>
                                </ol>
                            </details>
                        </div>

                        <!-- Any other company: the manual route -->
                        <div class="rounded-lg border border-gray-200 p-4 dark:border-slate-700" data-test="nmi-provider-other">
                            <span class="font-medium text-gray-900 dark:text-white">{{ $t("nmi.providers.other_title") }}</span>
                            <ol class="mt-2 list-decimal space-y-1.5 pl-5 text-sm text-gray-700 dark:text-gray-300">
                                <li v-for="n in GUIDE_STEPS.other" :key="n">
                                    {{ $t(`nmi.providers.guide.other.s${n}`) }}
                                </li>
                            </ol>
                        </div>

                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $t("nmi.providers.info") }}
                        </p>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button
                            @click="showProviderSettings = false"
                            class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-slate-700 dark:text-gray-300 dark:hover:bg-slate-600"
                        >
                            {{ $t("common.close") }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AuthenticatedLayout>
</template>
