<script setup>
import { Head, Link } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

const props = defineProps({
    pool: Object,
});
</script>

<template>
    <Head :title="`${$t('nmi.pool_details')} - ${pool.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link
                        :href="route('settings.nmi.dashboard')"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
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
                    <h2
                        class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
                    >
                        {{ pool.name }}
                    </h2>
                    <span
                        :class="[
                            'rounded-full px-2 py-1 text-xs font-medium',
                            pool.type === 'dedicated'
                                ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300'
                                : 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                        ]"
                    >
                        {{
                            pool.type === "dedicated"
                                ? $t("nmi.pool_dedicated")
                                : $t("nmi.pool_shared")
                        }}
                    </span>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Pool Info Card -->
                <div
                    class="mb-6 rounded-xl border border-slate-700/50 bg-slate-800/50 p-6"
                >
                    <h3
                        class="mb-4 text-lg font-semibold text-white"
                    >
                        {{ $t("nmi.pool_information") }}
                    </h3>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <span class="text-sm text-gray-400">{{
                                $t("nmi.pool_name")
                            }}</span>
                            <p class="font-medium text-white">
                                {{ pool.name }}
                            </p>
                        </div>
                        <div>
                            <span class="text-sm text-gray-400">{{
                                $t("nmi.pool_type")
                            }}</span>
                            <p class="font-medium text-white">
                                {{
                                    pool.type === "dedicated"
                                        ? $t("nmi.pool_dedicated")
                                        : $t("nmi.pool_shared")
                                }}
                            </p>
                        </div>
                        <div>
                            <span class="text-sm text-gray-400">{{
                                $t("nmi.max_ips")
                            }}</span>
                            <p class="font-medium text-white">
                                {{ pool.max_ips }}
                            </p>
                        </div>
                    </div>
                    <div v-if="pool.description" class="mt-4">
                        <span class="text-sm text-gray-400">{{
                            $t("nmi.description")
                        }}</span>
                        <p class="font-medium text-white">
                            {{ pool.description }}
                        </p>
                    </div>
                </div>

                <!-- IP Addresses List -->
                <div
                    class="rounded-xl border border-slate-700/50 bg-slate-800/50 p-6"
                >
                    <h3
                        class="mb-4 text-lg font-semibold text-white"
                    >
                        {{ $t("nmi.dedicated_ips_list") }} ({{
                            pool.ip_addresses?.length || 0
                        }})
                    </h3>

                    <div
                        v-if="!pool.ip_addresses?.length"
                        class="py-8 text-center text-gray-400"
                    >
                        <svg
                            class="mx-auto h-12 w-12 text-gray-500"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"
                            />
                        </svg>
                        <p class="mt-2">{{ $t("nmi.no_ips_in_pool") }}</p>
                    </div>

                    <div v-else class="space-y-3">
                        <Link
                            v-for="ip in pool.ip_addresses"
                            :key="ip.id"
                            :href="route('settings.nmi.ips.show', ip.id)"
                            class="block rounded-lg border border-slate-600/50 bg-slate-700/50 p-4 transition-all hover:border-violet-500/50 hover:bg-slate-700"
                        >
                            <div
                                class="flex items-center justify-between"
                            >
                                <div>
                                    <span
                                        class="font-mono text-white"
                                        >{{ ip.ip_address }}</span
                                    >
                                    <span
                                        v-if="ip.hostname"
                                        class="ml-2 text-sm text-gray-400"
                                        >({{ ip.hostname }})</span
                                    >
                                </div>
                                <div class="flex items-center gap-2">
                                    <span
                                        :class="[
                                            'rounded-full px-2 py-1 text-xs',
                                            ip.warming_status === 'warmed'
                                                ? 'bg-green-900/30 text-green-400'
                                                : ip.warming_status ===
                                                    'warming'
                                                  ? 'bg-yellow-900/30 text-yellow-400'
                                                  : 'bg-gray-600/30 text-gray-400',
                                        ]"
                                    >
                                        {{ ip.warming_status }}
                                    </span>
                                </div>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
