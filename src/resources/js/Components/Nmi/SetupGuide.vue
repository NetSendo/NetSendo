<script setup>
import { Link, router } from "@inertiajs/vue3";
import { ref, computed, onMounted } from "vue";
import { useI18n } from "vue-i18n";
import axios from "axios";

// The steps to a working own sending server, each checked live (GET settings.nmi.guide)
const { t } = useI18n();

const steps = ref(null);
const loading = ref(false);
const loadError = ref(false);
const toggling = ref(false);
const toggleError = ref("");

const load = async () => {
    loading.value = true;
    loadError.value = false;
    try {
        const response = await axios.get(route("settings.nmi.guide"));
        steps.value = response.data.data.steps;
    } catch (error) {
        loadError.value = true;
    } finally {
        loading.value = false;
    }
};

onMounted(load);

const byKey = computed(() => Object.fromEntries((steps.value || []).map((step) => [step.key, step])));
const counted = computed(() => (steps.value || []).filter((step) => step.status !== "info"));
const done = computed(() => counted.value.filter((step) => step.status === "ok").length);

// Switch the sending server on or off for the installation (admin)
const toggle = async (enabled) => {
    toggling.value = true;
    toggleError.value = "";
    try {
        await axios.post(route(enabled ? "settings.nmi.installation.enable" : "settings.nmi.installation.disable"));
        router.reload();
        await load();
    } catch (error) {
        toggleError.value =
            error.response?.data?.errors?.nmi?.[0] || error.response?.data?.message || t("common.notifications.error");
    } finally {
        toggling.value = false;
    }
};

const statusStyle = {
    ok: { dot: "bg-emerald-500", badge: "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400" },
    todo: { dot: "bg-indigo-400", badge: "bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300" },
    warning: { dot: "bg-amber-500", badge: "bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400" },
    error: { dot: "bg-rose-500", badge: "bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400" },
    unknown: { dot: "bg-gray-400", badge: "bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-gray-300" },
    info: { dot: "bg-sky-500", badge: "bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300" },
};
const style = (status) => statusStyle[status] || statusStyle.unknown;

const statusLabel = (status) =>
    ({
        ok: t("nmi.guide.status.ok"),
        todo: t("nmi.guide.status.todo"),
        warning: t("nmi.guide.status.warning"),
        error: t("nmi.guide.status.error"),
        unknown: t("nmi.guide.status.unknown"),
        info: t("nmi.guide.status.info"),
    })[status] || status;

const stepTitle = (key) =>
    ({
        switch: t("nmi.guide.switch.title"),
        mta: t("nmi.guide.mta.title"),
        port25: t("nmi.guide.port25.title"),
        server_name: t("nmi.guide.server_name.title"),
        domain: t("nmi.guide.domain.title"),
        ip: t("nmi.guide.ip.title"),
        dkim: t("nmi.guide.dkim.title"),
        ip_on_server: t("nmi.guide.ip_on_server.title"),
        ptr: t("nmi.guide.ptr.title"),
        mailbox: t("nmi.guide.mailbox.title"),
        test: t("nmi.guide.test.title"),
    })[key] || key;

const nmiDomains = computed(() => (byKey.value.domain?.data?.domains || []).filter((d) => d.verified && d.nmi_enabled));
const pendingDomains = computed(() => (byKey.value.domain?.data?.domains || []).filter((d) => !(d.verified && d.nmi_enabled)));
const ipsWhere = (key, test) => (byKey.value[key]?.data?.ips || []).filter(test);

const ufwCommand = "ufw allow from 10.58.0.0/16 to 10.58.0.1 port 2525 proto tcp";
const updateCommand = "docker compose pull && docker compose up -d";
</script>

<template>
    <div class="rounded-xl border bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-6 py-4 dark:border-slate-700">
            <div>
                <h3 class="font-semibold text-gray-900 dark:text-white">{{ $t("nmi.guide.title") }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t("nmi.guide.subtitle") }}</p>
            </div>
            <div class="flex items-center gap-3">
                <span v-if="steps" class="text-sm font-medium text-gray-600 dark:text-gray-300">
                    {{ $t("nmi.guide.progress", { done, total: counted.length }) }}
                </span>
                <button
                    type="button"
                    @click="load"
                    :disabled="loading"
                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-slate-600 dark:text-gray-300 dark:hover:bg-slate-700"
                >
                    {{ loading ? $t("nmi.checking") : $t("nmi.guide.refresh") }}
                </button>
            </div>
        </div>

        <div v-if="!steps && loading" class="p-6 text-sm text-gray-500 dark:text-gray-400">{{ $t("nmi.checking") }}</div>
        <div v-else-if="loadError && !steps" class="p-6 text-sm text-rose-600 dark:text-rose-400">{{ $t("nmi.guide.load_failed") }}</div>

        <ol v-else-if="steps" class="divide-y divide-gray-100 dark:divide-slate-700">
            <li v-for="(step, index) in steps" :key="step.key" class="flex gap-4 px-6 py-4">
                <div class="flex flex-col items-center">
                    <span
                        class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold text-white"
                        :class="style(step.status).dot"
                    >
                        {{ index + 1 }}
                    </span>
                </div>
                <div class="min-w-0 flex-1 space-y-2 text-sm text-gray-600 dark:text-gray-300">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold text-gray-900 dark:text-white">{{ stepTitle(step.key) }}</span>
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="style(step.status).badge">
                            {{ statusLabel(step.status) }}
                        </span>
                    </div>

                    <!-- 1. The switch -->
                    <template v-if="step.key === 'switch'">
                        <p v-if="step.data.forced_by_env">{{ $t("nmi.guide.switch.forced") }}</p>
                        <p v-else-if="step.status === 'ok'">{{ $t("nmi.guide.switch.ok") }}</p>
                        <p v-else>{{ $t("nmi.guide.switch.todo") }}</p>
                        <p v-if="!step.data.can_toggle && !step.data.forced_by_env" class="text-gray-500 dark:text-gray-400">
                            {{ $t("nmi.guide.switch.admin_only") }}
                        </p>
                        <div v-if="step.data.can_toggle && !step.data.forced_by_env">
                            <button
                                v-if="step.status !== 'ok'"
                                type="button"
                                @click="toggle(true)"
                                :disabled="toggling"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                            >
                                {{ $t("nmi.guide.switch.enable") }}
                            </button>
                            <button
                                v-else
                                type="button"
                                @click="toggle(false)"
                                :disabled="toggling"
                                class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-slate-600 dark:text-gray-300 dark:hover:bg-slate-700"
                            >
                                {{ $t("nmi.guide.switch.disable") }}
                            </button>
                        </div>
                        <p v-if="toggleError" class="text-rose-600 dark:text-rose-400">{{ toggleError }}</p>
                    </template>

                    <!-- 2. The sending server answers -->
                    <template v-else-if="step.key === 'mta'">
                        <p v-if="step.status === 'ok'">{{ $t("nmi.guide.mta.ok", { address: `${step.data.host}:${step.data.port}` }) }}</p>
                        <template v-else>
                            <p>{{ $t("nmi.guide.mta.error", { address: `${step.data.host}:${step.data.port}` }) }}</p>
                            <code class="block rounded bg-gray-100 px-3 py-2 font-mono text-xs dark:bg-slate-900">{{ updateCommand }}</code>
                            <p>{{ $t("nmi.guide.mta.firewall") }}</p>
                            <code class="block rounded bg-gray-100 px-3 py-2 font-mono text-xs dark:bg-slate-900">{{ ufwCommand }}</code>
                        </template>
                    </template>

                    <!-- 3. Port 25 -->
                    <template v-else-if="step.key === 'port25'">
                        <p v-if="step.status === 'ok'">{{ $t("nmi.guide.port25.ok") }}</p>
                        <p v-else-if="step.status === 'error'">
                            {{ $t("nmi.guide.port25.error") }}
                            <a href="#hosting-providers" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ $t("nmi.guide.see_providers") }}</a>
                        </p>
                        <p v-else>{{ $t("nmi.guide.port25.unknown") }}</p>
                    </template>

                    <!-- 4. The server's name and its PTR record -->
                    <template v-else-if="step.key === 'server_name'">
                        <p v-if="step.status === 'ok'">{{ $t("nmi.guide.server_name.ok", { name: step.data.name }) }}</p>
                        <p v-else-if="step.status === 'error'">{{ $t("nmi.guide.server_name.error") }}</p>
                        <template v-else>
                            <p>{{ $t("nmi.guide.server_name.warning", { name: step.data.name }) }}</p>
                            <p v-if="!step.data.addresses?.length">{{ $t("nmi.guide.server_name.no_address") }}</p>
                            <p v-else>{{ $t("nmi.guide.server_name.now") }}</p>
                            <ul v-if="step.data.addresses?.length" class="space-y-1 font-mono text-xs">
                                <li v-for="address in step.data.addresses" :key="address.ip">
                                    {{ $t("nmi.server_name.address_ptr", { ip: address.ip, ptr: address.ptr || $t("nmi.server_name.no_ptr") }) }}
                                </li>
                            </ul>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t("nmi.server_name.cloudflare") }}</p>
                        </template>
                    </template>

                    <!-- 5. The sender's domain -->
                    <template v-else-if="step.key === 'domain'">
                        <p v-if="step.status === 'ok'">
                            {{ $t("nmi.guide.domain.ok") }}
                            <span class="font-medium text-gray-900 dark:text-white">{{ nmiDomains.map((d) => d.domain).join(", ") }}</span>
                        </p>
                        <p v-else>{{ $t("nmi.guide.domain.todo") }}</p>
                        <ul v-if="pendingDomains.length" class="space-y-1">
                            <li v-for="domain in pendingDomains" :key="domain.id">
                                <Link :href="route('deliverability.domains.show', domain.id)" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                                    {{ domain.domain }}
                                </Link>
                                — {{ domain.verified ? $t("nmi.guide.domain.switch_on") : $t("nmi.guide.domain.verify") }}
                            </li>
                        </ul>
                        <Link
                            v-if="step.status !== 'ok'"
                            :href="route('deliverability.index')"
                            class="inline-block font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                        >
                            {{ $t("nmi.guide.domain.open") }}
                        </Link>
                    </template>

                    <!-- 6. An IP address -->
                    <template v-else-if="step.key === 'ip'">
                        <template v-if="step.status === 'ok'">
                            <p>{{ $t("nmi.guide.ip.ok") }}</p>
                            <ul class="space-y-1">
                                <li v-for="ip in step.data.ips" :key="ip.id">
                                    <Link :href="route('settings.nmi.ips.show', ip.id)" class="font-mono font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ ip.ip_address }}</Link>
                                    <span class="text-gray-500 dark:text-gray-400"> — {{ ip.hostname }} ({{ ip.domain }})</span>
                                </li>
                            </ul>
                        </template>
                        <template v-else>
                            <p>{{ $t("nmi.guide.ip.todo") }}</p>
                            <p v-if="step.data.server_addresses?.length">
                                {{ $t("nmi.guide.ip.server_addresses") }}
                                <span class="font-mono font-medium text-gray-900 dark:text-white">{{ step.data.server_addresses.join(", ") }}</span>
                            </p>
                            <a href="#hosting-providers" class="inline-block font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ $t("nmi.guide.see_providers") }}</a>
                        </template>
                    </template>

                    <!-- 7. DKIM -->
                    <template v-else-if="step.key === 'dkim'">
                        <p v-if="step.status === 'ok'">{{ $t("nmi.guide.dkim.ok") }}</p>
                        <p v-else-if="!step.data.ips?.length">{{ $t("nmi.guide.needs_ip") }}</p>
                        <template v-else>
                            <p>{{ $t("nmi.guide.dkim.todo") }}</p>
                            <ul class="space-y-1">
                                <li v-for="ip in ipsWhere('dkim', (i) => i.dkim !== 'published')" :key="ip.id">
                                    <Link :href="route('settings.nmi.ips.show', ip.id)" class="font-mono font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ ip.ip_address }}</Link>
                                    — {{ ip.dkim === "no_keys" ? $t("nmi.guide.dkim.no_keys") : $t("nmi.guide.dkim.not_published") }}
                                </li>
                            </ul>
                        </template>
                    </template>

                    <!-- 8. The IP is on the server -->
                    <template v-else-if="step.key === 'ip_on_server'">
                        <p v-if="step.status === 'ok'">{{ $t("nmi.guide.ip_on_server.ok") }}</p>
                        <p v-else-if="step.status === 'unknown'">{{ $t("nmi.guide.ip_on_server.unknown") }}</p>
                        <p v-else-if="!step.data.ips?.length">{{ $t("nmi.guide.needs_ip") }}</p>
                        <template v-else>
                            <p>{{ $t("nmi.guide.ip_on_server.warning") }}</p>
                            <ul class="space-y-1">
                                <li v-for="ip in ipsWhere('ip_on_server', (i) => !i.on_server || i.ipv6)" :key="ip.id">
                                    <Link :href="route('settings.nmi.ips.show', ip.id)" class="font-mono font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ ip.ip_address }}</Link>
                                    — {{ ip.ipv6 ? $t("nmi.sending_ip.ipv6") : $t("nmi.guide.ip_on_server.missing") }}
                                </li>
                            </ul>
                        </template>
                    </template>

                    <!-- 9. PTR records of the IPs -->
                    <template v-else-if="step.key === 'ptr'">
                        <p v-if="step.status === 'ok'">{{ $t("nmi.guide.ptr.ok") }}</p>
                        <p v-else-if="!step.data.ips?.length">{{ $t("nmi.guide.needs_ip") }}</p>
                        <template v-else>
                            <p>{{ $t("nmi.guide.ptr.warning") }}</p>
                            <ul class="space-y-1">
                                <li v-for="ip in ipsWhere('ptr', (i) => !i.ok)" :key="ip.id">
                                    <Link :href="route('settings.nmi.ips.show', ip.id)" class="font-mono font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ ip.ip_address }}</Link>
                                    — {{ $t("nmi.guide.ptr.item", { name: ip.name || ip.hostname, ptr: ip.ptr || $t("nmi.server_name.no_ptr") }) }}
                                </li>
                            </ul>
                        </template>
                    </template>

                    <!-- 10. A mailbox -->
                    <template v-else-if="step.key === 'mailbox'">
                        <p v-if="step.status === 'ok'">{{ $t("nmi.guide.mailbox.ok", { count: step.data.count }) }}</p>
                        <p v-else>{{ $t("nmi.guide.mailbox.todo") }}</p>
                        <Link :href="route('settings.mailboxes.index')" class="inline-block font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                            {{ $t("nmi.guide.mailbox.open") }}
                        </Link>
                    </template>

                    <!-- 11. A test -->
                    <template v-else-if="step.key === 'test'">
                        <p>{{ $t("nmi.guide.test.info") }}</p>
                        <a
                            href="https://www.mail-tester.com/"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-block font-medium text-indigo-600 hover:underline dark:text-indigo-400"
                        >
                            mail-tester.com
                        </a>
                    </template>
                </div>
            </li>
        </ol>
    </div>
</template>
