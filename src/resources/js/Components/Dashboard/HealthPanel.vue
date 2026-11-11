<script setup>
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Panel from "@/Components/Ui/Panel.vue";
import { useDateTime } from "@/Composables/useDateTime";

const { t, te } = useI18n();
const { formatRelative } = useDateTime();

const props = defineProps({
    // Payload of api.campaign-auditor.dashboard-widget; null while loading
    audit: { type: Object, default: null },
    loading: { type: Boolean, default: false },
});

const score = computed(() => Math.max(0, Math.min(100, Number(props.audit?.score) || 0)));

const label = computed(() => {
    const key = `campaign_auditor.score.${props.audit?.scoreLabel}`;
    return te(key) ? t(key) : "";
});

// The bar's colour repeats what the label says in words; it never carries the meaning alone
const barColor = computed(() => {
    if (score.value >= 60) return "bg-emerald-600 dark:bg-emerald-400";
    if (score.value >= 40) return "bg-amber-500 dark:bg-amber-400";
    return "bg-rose-600 dark:bg-rose-400";
});

const findings = computed(() => [
    { key: "critical", label: t("campaign_auditor.critical"), value: props.audit?.criticalCount ?? 0 },
    { key: "warnings", label: t("campaign_auditor.warnings"), value: props.audit?.warningCount ?? 0 },
    { key: "info", label: t("campaign_auditor.info"), value: props.audit?.infoCount ?? 0 },
]);
</script>

<template>
    <Panel :title="$t('dashboard.health.title')">
        <template v-if="audit?.hasAudit" #actions>
            <span class="tabular-nums text-slate-900 dark:text-white">
                <span class="text-xl font-semibold">{{ score }}</span>
                <span class="text-slate-600 dark:text-slate-400"> / 100</span>
            </span>
        </template>

        <div v-if="loading" class="flex flex-col gap-3">
            <div class="h-2 animate-pulse rounded bg-slate-200 dark:bg-slate-700"></div>
            <div class="h-4 w-2/3 animate-pulse rounded bg-slate-200 dark:bg-slate-700"></div>
        </div>

        <div v-else-if="!audit?.hasAudit" class="flex flex-col items-start gap-2">
            <p class="text-sm text-slate-600 dark:text-slate-400">
                {{ $t("campaign_auditor.no_audit_widget") }}
            </p>
            <Link
                :href="route('campaign-auditor.index')"
                class="text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
            >
                {{ $t("campaign_auditor.run_audit") }}
            </Link>
        </div>

        <div v-else class="flex flex-col gap-3">
            <div
                role="img"
                :aria-label="$t('dashboard.health.score_label', { score })"
                class="h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700"
            >
                <div
                    class="h-full rounded-full"
                    :class="barColor"
                    :style="{ width: score + '%' }"
                ></div>
            </div>

            <p class="text-sm text-slate-600 dark:text-slate-400">
                <span v-if="label" class="font-medium text-slate-900 dark:text-white"
                    >{{ label }} ·
                </span>
                {{ $t("dashboard.health.last_audit", { when: formatRelative(audit.lastAuditAt) }) }}
            </p>

            <dl class="grid grid-cols-3 gap-2 text-center">
                <div
                    v-for="finding in findings"
                    :key="finding.key"
                    class="rounded-xl bg-slate-50 px-2 py-2 dark:bg-slate-700/40"
                >
                    <dd
                        class="text-base font-semibold tabular-nums text-slate-900 dark:text-white"
                    >
                        {{ finding.value }}
                    </dd>
                    <dt class="text-xs text-slate-600 dark:text-slate-400">
                        {{ finding.label }}
                    </dt>
                </div>
            </dl>

            <p
                v-if="audit.isStale"
                class="text-xs text-slate-600 dark:text-slate-400"
            >
                {{ $t("campaign_auditor.audit_stale") }}
            </p>

            <Link
                :href="route('campaign-auditor.index')"
                class="text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
            >
                {{ $t("dashboard.health.open") }}
            </Link>
        </div>
    </Panel>
</template>
