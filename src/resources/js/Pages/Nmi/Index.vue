<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { ref, computed } from "vue";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    pools: { type: Array, default: () => [] },
    ips: { type: Array, default: () => [] },
    nmiEnabled: { type: Boolean, default: false },
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
    </AuthenticatedLayout>
</template>
