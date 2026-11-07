<script setup>
import { computed } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";

// Settings → Telemetry: the opt-in switch, the installation id and the exact report this
// installation sends (docs/TELEMETRY.md, https://netsendo.com/en/docs/telemetry).
const props = defineProps({
    telemetry: Object,
});

const { t } = useI18n();
const page = usePage();

const enabled = computed(() => props.telemetry?.enabled === true);
const locked = computed(() => props.telemetry?.locked === true);
const preview = computed(() => JSON.stringify(props.telemetry?.preview ?? {}, null, 2));
const lastSent = computed(() =>
    props.telemetry?.last_sent_at
        ? new Date(props.telemetry.last_sent_at).toLocaleString()
        : t("telemetry.never"),
);

const toggle = () => {
    if (locked.value) return;
    router.post(
        route("settings.telemetry.update"),
        { enabled: !enabled.value },
        { preserveScroll: true },
    );
};
</script>

<template>
    <Head :title="t('telemetry.title')" />

    <AuthenticatedLayout :title="t('telemetry.title')">
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {{ t("telemetry.title") }}
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ t("telemetry.subtitle") }}
                </p>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
                <div
                    v-if="page.props.flash?.success"
                    class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900/60 dark:bg-green-950/40 dark:text-green-300"
                >
                    {{ page.props.flash.success }}
                </div>
                <div
                    v-if="page.props.flash?.error"
                    class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300"
                >
                    {{ page.props.flash.error }}
                </div>

                <div class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                    <div class="space-y-5 p-6">
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            {{ t("telemetry.intro") }}
                        </p>

                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ t("telemetry.toggle") }}
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ enabled ? t("telemetry.status_on") : t("telemetry.status_off") }}
                                </p>
                                <p v-if="locked" class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                                    {{ t("telemetry.env_locked") }}
                                </p>
                            </div>
                            <button
                                type="button"
                                role="switch"
                                :aria-checked="enabled"
                                :aria-label="t('telemetry.toggle')"
                                :disabled="locked"
                                @click="toggle"
                                :class="[
                                    'relative inline-flex h-6 w-11 flex-shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
                                    enabled ? 'bg-green-500' : 'bg-gray-300 dark:bg-gray-600',
                                    locked ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
                                ]"
                            >
                                <span
                                    :class="[
                                        'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                                        enabled ? 'translate-x-5' : 'translate-x-0',
                                    ]"
                                />
                            </button>
                        </div>

                        <dl class="grid gap-4 border-t border-gray-100 pt-5 text-sm dark:border-gray-700 sm:grid-cols-2">
                            <div>
                                <dt class="font-medium text-gray-700 dark:text-gray-300">
                                    {{ t("telemetry.instance_id") }}
                                </dt>
                                <dd class="mt-1 break-all font-mono text-xs text-gray-600 dark:text-gray-400">
                                    {{ props.telemetry?.instance_id || "—" }}
                                </dd>
                                <dd class="mt-1 text-xs text-gray-500 dark:text-gray-500">
                                    {{ t("telemetry.instance_id_help") }}
                                </dd>
                            </div>
                            <div>
                                <dt class="font-medium text-gray-700 dark:text-gray-300">
                                    {{ t("telemetry.last_sent") }}
                                </dt>
                                <dd class="mt-1 text-gray-600 dark:text-gray-400">{{ lastSent }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <div class="overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800">
                    <div class="space-y-3 p-6">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                                {{ t("telemetry.preview") }}
                            </h3>
                            <a
                                :href="props.telemetry?.docs_url"
                                target="_blank"
                                rel="noopener"
                                class="text-sm text-indigo-600 hover:underline dark:text-indigo-400"
                            >
                                {{ t("telemetry.docs_link") }} →
                            </a>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ t("telemetry.preview_help") }}
                            <span class="font-mono text-xs">{{ props.telemetry?.endpoint }}</span>
                        </p>
                        <pre class="max-h-96 overflow-auto rounded-lg bg-gray-50 p-4 text-xs text-gray-800 dark:bg-gray-900 dark:text-gray-200"><code>{{ preview }}</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
