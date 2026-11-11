<script setup>
import { computed, ref } from "vue";
import { Head, router } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import PageHeader from "@/Components/Ui/PageHeader.vue";
import Panel from "@/Components/Ui/Panel.vue";
import StatusPill from "@/Components/Ui/StatusPill.vue";
import Switch from "@/Components/Ui/Switch.vue";
import Toast from "@/Components/Ui/Toast.vue";
import { useToast } from "@/Composables/useToast";

// Settings → Telemetry: the opt-in switch, the installation id and the exact report this
// installation sends (docs/TELEMETRY.md, https://netsendo.com/en/docs/telemetry).
const props = defineProps({
    telemetry: Object,
});

const { t, locale } = useI18n();
// The server says what the switch did, in the user's language
const { toast, dismiss } = useToast({ flash: true });

const enabled = computed(() => props.telemetry?.enabled === true);
const locked = computed(() => props.telemetry?.locked === true);
const preview = computed(() => JSON.stringify(props.telemetry?.preview ?? {}, null, 2));
const lastSent = computed(() =>
    props.telemetry?.last_sent_at
        ? new Date(props.telemetry.last_sent_at).toLocaleString(locale.value)
        : t("telemetry.never"),
);

const switching = ref(false);

const toggle = () => {
    if (locked.value || switching.value) return;
    switching.value = true;
    router.post(
        route("settings.telemetry.update"),
        { enabled: !enabled.value },
        { preserveScroll: true, onFinish: () => (switching.value = false) },
    );
};
</script>

<template>
    <Head :title="t('telemetry.title')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader :title="t('telemetry.title')" :subtitle="t('telemetry.subtitle')">
                <StatusPill :tone="enabled ? 'success' : 'neutral'">
                    {{ enabled ? t("telemetry.status_on") : t("telemetry.status_off") }}
                </StatusPill>
            </PageHeader>
        </template>

        <div class="mx-auto flex max-w-3xl flex-col gap-4">
            <Panel>
                <p class="text-sm text-slate-600 dark:text-slate-400">{{ t("telemetry.intro") }}</p>

                <!-- The one decision on this page -->
                <div
                    class="mt-4 flex items-start justify-between gap-4 rounded-xl border border-slate-200 px-4 py-3 dark:border-slate-700/60"
                >
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-900 dark:text-white">{{ t("telemetry.toggle") }}</p>
                        <p class="text-xs text-slate-600 dark:text-slate-400">
                            {{ enabled ? t("telemetry.status_on") : t("telemetry.status_off") }}
                        </p>
                        <p v-if="locked" class="mt-1 text-xs text-amber-800 dark:text-amber-300">
                            {{ t("telemetry.env_locked") }}
                        </p>
                    </div>
                    <Switch
                        :model-value="enabled"
                        :label="t('telemetry.toggle')"
                        :disabled="locked || switching"
                        @update:model-value="toggle"
                    />
                </div>

                <dl class="mt-4 grid grid-cols-1 gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    <div class="min-w-0">
                        <dt class="text-slate-600 dark:text-slate-400">{{ t("telemetry.instance_id") }}</dt>
                        <dd class="break-all font-mono text-xs text-slate-900 dark:text-white">
                            {{ props.telemetry?.instance_id || "—" }}
                        </dd>
                        <dd class="mt-1 text-xs text-slate-600 dark:text-slate-400">{{ t("telemetry.instance_id_help") }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-600 dark:text-slate-400">{{ t("telemetry.last_sent") }}</dt>
                        <dd class="tabular-nums text-slate-900 dark:text-white">{{ lastSent }}</dd>
                    </div>
                </dl>
            </Panel>

            <!-- Exactly what goes out, and where to -->
            <Panel :title="t('telemetry.preview')">
                <template #actions>
                    <a
                        :href="props.telemetry?.docs_url"
                        target="_blank"
                        rel="noopener"
                        class="font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                    >
                        {{ t("telemetry.docs_link") }}
                    </a>
                </template>
                <p class="-mt-3 text-sm text-slate-600 dark:text-slate-400">
                    {{ t("telemetry.preview_help") }}
                    <span class="break-all font-mono text-xs text-slate-900 dark:text-white">{{ props.telemetry?.endpoint }}</span>
                </p>
                <pre
                    class="mt-3 max-h-[28rem] overflow-auto rounded-xl border border-slate-200 bg-slate-50 p-4 font-mono text-xs leading-relaxed text-slate-800 dark:border-slate-700/60 dark:bg-slate-900/60 dark:text-slate-200"
                ><code>{{ preview }}</code></pre>
            </Panel>
        </div>

        <Toast :toast="toast" @close="dismiss" />
    </AuthenticatedLayout>
</template>
