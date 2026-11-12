<script setup>
// Whether this server can deliver mail sent through the NetSendo MTA, from what the MTA
// found out itself (XNMI-EGRESS, MtaStatusService::egress()): port 25 to the outside,
// the public address receivers see, its PTR record, and the SPF entry the sending
// domain needs. Each item that is not in order says what to do about it.
import { computed, ref } from "vue";
import { useI18n } from "vue-i18n";

const props = defineProps({
    // settings.nmi.mta.status data (MtaStatusService::probe()); null while not asked
    status: { type: Object, default: null },
    // The sending domain, for the SPF hint
    domain: { type: String, default: null },
    // A dedicated IP the mailbox sends from instead of the server's own address
    ip: { type: String, default: null },
});

const emit = defineEmits(["recheck"]);

const { t } = useI18n();

const GUIDE_URL = "https://github.com/NetSendo/NetSendo/blob/main/docs/NMI.md#port-25-ptr-and-spf";

const egress = computed(() => props.status?.egress ?? null);
const address = computed(() => props.ip || egress.value?.public_ip || null);
const mechanism = computed(() =>
    address.value ? `${address.value.includes(":") ? "ip6" : "ip4"}:${address.value}` : null,
);

// ok | problem | waiting, per item
const portState = computed(() => {
    const port = egress.value?.port25;
    if (port === "open") return "ok";
    if (port === "blocked") return "problem";
    return "waiting";
});
const ptrState = computed(() => {
    if (props.ip) return null; // a dedicated IP has its own PTR check (NMI → IP)
    if (!egress.value?.public_ip) return "waiting";
    return egress.value.ptr === "confirmed" ? "ok" : "problem";
});

const portText = computed(() => {
    const port = egress.value?.port25 ?? "pending";
    return t(`mailboxes.nmi.readiness.port25_${["open", "blocked", "unknown", "off"].includes(port) ? port : "pending"}`);
});

const copied = ref(false);
const copy = async () => {
    try {
        await navigator.clipboard.writeText(mechanism.value);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch (error) {
        // the value is selectable on the page
    }
};

const dot = (state) =>
    ({
        ok: "bg-emerald-500",
        problem: "bg-amber-500",
        waiting: "bg-slate-400 dark:bg-slate-500",
    })[state];
</script>

<template>
    <div
        class="rounded-xl border border-slate-200 p-3 text-sm dark:border-slate-700/60"
        data-test="nmi-readiness"
    >
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="font-medium text-slate-900 dark:text-white">
                {{ $t("mailboxes.nmi.readiness.title") }}
            </p>
            <button
                type="button"
                class="text-xs font-medium text-indigo-600 underline dark:text-indigo-400"
                @click="emit('recheck')"
            >
                {{ $t("mailboxes.nmi.readiness.recheck") }}
            </button>
        </div>

        <p
            v-if="status && status.online && !egress"
            class="mt-2 text-xs text-amber-700 dark:text-amber-300"
        >
            {{ $t("mailboxes.nmi.readiness.old_mta") }}
        </p>

        <ul v-else class="mt-2 space-y-2">
            <!-- Port 25 -->
            <li class="flex gap-2">
                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="dot(portState)" />
                <div class="min-w-0">
                    <p class="text-slate-700 dark:text-slate-300">
                        <span class="font-medium">{{ $t("mailboxes.nmi.readiness.port25") }}:</span>
                        {{ portText }}
                    </p>
                    <p
                        v-if="portState === 'problem'"
                        class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"
                    >
                        {{ $t("mailboxes.nmi.readiness.port25_blocked_help") }}
                    </p>
                </div>
            </li>

            <!-- Address and PTR -->
            <li v-if="address" class="flex gap-2">
                <span
                    class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                    :class="dot(ptrState ?? 'ok')"
                />
                <div class="min-w-0">
                    <p class="break-words text-slate-700 dark:text-slate-300">
                        <span class="font-medium">{{ $t("mailboxes.nmi.readiness.address") }}:</span>
                        {{ " " }}<span class="font-mono tabular-nums">{{ address }}</span>
                        <template v-if="ptrState === 'ok'">
                            · {{ $t("mailboxes.nmi.readiness.ptr_ok", { name: egress.helo }) }}
                        </template>
                    </p>
                    <p
                        v-if="ptrState === 'problem'"
                        class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"
                    >
                        {{ $t("mailboxes.nmi.readiness.ptr_missing", { address }) }}
                    </p>
                    <p
                        v-else-if="ip"
                        class="mt-0.5 text-xs text-slate-500 dark:text-slate-400"
                    >
                        {{ $t("mailboxes.nmi.readiness.ptr_dedicated") }}
                    </p>
                </div>
            </li>

            <!-- SPF -->
            <li v-if="mechanism" class="flex gap-2">
                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-indigo-500" />
                <div class="min-w-0">
                    <p class="text-slate-700 dark:text-slate-300">
                        <span class="font-medium">SPF:</span>
                        {{
                            domain
                                ? $t("mailboxes.nmi.readiness.spf_domain", { domain })
                                : $t("mailboxes.nmi.readiness.spf")
                        }}
                    </p>
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <code
                            class="rounded-lg bg-slate-100 px-2 py-1 font-mono text-xs text-slate-800 dark:bg-slate-900/60 dark:text-slate-200"
                        >{{ mechanism }}</code>
                        <button
                            type="button"
                            class="text-xs font-medium text-indigo-600 underline dark:text-indigo-400"
                            @click="copy"
                        >
                            {{ copied ? $t("mailboxes.nmi.readiness.copied") : $t("mailboxes.nmi.dkim.copy") }}
                        </button>
                    </div>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        {{ $t("mailboxes.nmi.readiness.spf_help") }}
                    </p>
                </div>
            </li>
        </ul>

        <a
            :href="GUIDE_URL"
            target="_blank"
            rel="noopener"
            class="mt-3 inline-block text-xs font-medium text-indigo-600 underline dark:text-indigo-400"
        >
            {{ $t("mailboxes.nmi.readiness.guide") }}
        </a>
    </div>
</template>
