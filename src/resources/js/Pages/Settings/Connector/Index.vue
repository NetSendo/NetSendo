<script setup>
import { computed, ref } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import ConfirmModal from "@/Components/ConfirmModal.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import CodeBlock from "@/Components/Ui/CodeBlock.vue";
import CopyField from "@/Components/Ui/CopyField.vue";
import EmptyState from "@/Components/Ui/EmptyState.vue";
import Notice from "@/Components/Ui/Notice.vue";
import PageHeader from "@/Components/Ui/PageHeader.vue";
import Panel from "@/Components/Ui/Panel.vue";
import SegmentedControl from "@/Components/Ui/SegmentedControl.vue";
import StatusPill from "@/Components/Ui/StatusPill.vue";
import Toast from "@/Components/Ui/Toast.vue";
import { useToast } from "@/Composables/useToast";

/**
 * Settings → Connector. The address AI apps connect to (remote MCP with sign-in), the apps
 * that are connected, and how to connect Claude, Hermes and other hosts.
 */
const props = defineProps({
    enabled: Boolean,
    connectorUrl: { type: String, required: true },
    isHttps: Boolean,
    isLocalHost: Boolean,
    toolCount: { type: Number, default: 0 },
    connections: { type: Array, default: () => [] },
    scopeGroups: { type: Array, default: () => [] },
    isAdmin: Boolean,
});

const { t } = useI18n();
const { toast, dismiss } = useToast({ flash: true });

const linkClass = "font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300";

// --- Connected apps -------------------------------------------------------------------
const revoking = ref(null);
const processing = ref(false);
const expanded = ref(null);

const scopeLabel = (scope) => t(`api_keys.permissions.${scope.replace(":", "_")}`);

const revoke = () => {
    processing.value = true;
    router.delete(route("settings.connector.connections.destroy", revoking.value.id), {
        preserveScroll: true,
        onFinish: () => {
            processing.value = false;
            revoking.value = null;
        },
    });
};

// --- Instructions ---------------------------------------------------------------------
const host = ref("claude");
const hosts = computed(() => [
    { value: "claude", label: "Claude" },
    { value: "claude_code", label: "Claude Code" },
    { value: "hermes", label: "Hermes" },
    { value: "chatgpt", label: "ChatGPT" },
    { value: "other", label: t("connector.guide.other") },
]);

// Written here, not in the translations: commands and config are the same in every language
const snippets = computed(() => ({
    claudeCode: `claude mcp add --transport http netsendo ${props.connectorUrl}`,
    hermesCli: `hermes mcp add netsendo --url ${props.connectorUrl} --auth oauth\nhermes mcp login netsendo`,
    hermesYaml: `mcp_servers:\n  netsendo:\n    url: "${props.connectorUrl}"\n    auth: oauth`,
    cursor: JSON.stringify({ mcpServers: { netsendo: { url: props.connectorUrl } } }, null, 2),
    vscode: JSON.stringify({ servers: { netsendo: { type: "http", url: props.connectorUrl } } }, null, 2),
    gemini: `gemini mcp add --transport http netsendo ${props.connectorUrl}`,
    mcpRemote: JSON.stringify({ mcpServers: { netsendo: { command: "npx", args: ["-y", "mcp-remote", props.connectorUrl] } } }, null, 2),
    header: `Authorization: Bearer ns_live_…`,
}));

const steps = (name, count) => Array.from({ length: count }, (_, index) => t(`connector.guide.${name}.step_${index + 1}`));
</script>

<template>
    <Head :title="t('connector.title')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader :title="t('connector.title')" :subtitle="t('connector.subtitle')">
                <StatusPill :tone="enabled && toolCount > 0 ? 'success' : 'neutral'">
                    {{ enabled ? t("connector.status_on") : t("connector.status_off") }}
                </StatusPill>
            </PageHeader>
        </template>

        <div class="mx-auto flex max-w-3xl flex-col gap-4">
            <Notice v-if="!enabled" tone="warning" :title="t('connector.off_title')">
                {{ t("connector.off_text") }}
            </Notice>

            <Notice v-else-if="toolCount === 0" tone="danger" :title="t('connector.engine_missing_title')">
                {{ t("connector.engine_missing_text") }}
            </Notice>

            <Notice v-if="enabled && (isLocalHost || !isHttps)" tone="warning" :title="t('connector.not_public_title')">
                {{ t("connector.not_public_text") }}
            </Notice>

            <!-- The one thing to paste into an AI app -->
            <Panel :title="t('connector.address_title')">
                <p class="-mt-3 mb-4 text-sm text-slate-600 dark:text-slate-400">{{ t("connector.address_text") }}</p>
                <CopyField
                    :label="t('connector.address_label')"
                    :value="connectorUrl"
                    :hint="toolCount > 0 ? t('connector.address_hint', { count: toolCount }) : ''"
                />
            </Panel>

            <!-- Who is connected -->
            <Panel :title="t('connector.apps_title')" flush>
                <p class="px-5 pb-4 text-sm text-slate-600 dark:text-slate-400">
                    {{ isAdmin ? t("connector.apps_text_admin") : t("connector.apps_text") }}
                </p>

                <EmptyState
                    v-if="connections.length === 0"
                    :title="t('connector.apps_empty_title')"
                    :description="t('connector.apps_empty_text')"
                />

                <ul v-else class="divide-y divide-slate-200 border-t border-slate-200 dark:divide-slate-700/60 dark:border-slate-700/60">
                    <li v-for="connection in connections" :key="connection.id" class="flex flex-col gap-3 px-5 py-4">
                        <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-3">
                            <div class="min-w-0">
                                <p class="flex flex-wrap items-center gap-2">
                                    <span class="break-words text-sm font-semibold text-slate-900 dark:text-white">{{ connection.name }}</span>
                                    <StatusPill v-if="connection.verified_host" tone="success">{{ connection.verified_host }}</StatusPill>
                                    <StatusPill v-else tone="neutral">{{ t("connector.apps_self_registered") }}</StatusPill>
                                </p>
                                <p v-if="!connection.is_own" class="mt-1 break-all text-sm text-slate-600 dark:text-slate-400">
                                    {{ t("connector.apps_user", { name: connection.user.name, email: connection.user.email }) }}
                                </p>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                                    {{ t("connector.apps_connected", { date: connection.created_at }) }}
                                    ·
                                    {{
                                        connection.last_used_at
                                            ? t("connector.apps_last_used", { date: connection.last_used_at })
                                            : t("connector.apps_never_used")
                                    }}
                                </p>
                            </div>
                            <SecondaryButton type="button" class="shrink-0" @click="revoking = connection">
                                {{ t("connector.revoke") }}
                            </SecondaryButton>
                        </div>

                        <div>
                            <button
                                type="button"
                                :aria-expanded="expanded === connection.id"
                                class="text-sm"
                                :class="linkClass"
                                @click="expanded = expanded === connection.id ? null : connection.id"
                            >
                                {{
                                    connection.full_access
                                        ? t("connector.apps_full_access")
                                        : t("connector.apps_permissions", { count: connection.scopes.length })
                                }}
                            </button>
                            <ul v-if="expanded === connection.id" class="mt-2 flex flex-wrap gap-1.5">
                                <li
                                    v-for="scope in connection.scopes"
                                    :key="scope"
                                    class="rounded-lg bg-slate-100 px-2 py-1 text-xs text-slate-900 dark:bg-slate-700/60 dark:text-slate-100"
                                >
                                    {{ scopeLabel(scope) }}
                                </li>
                            </ul>
                        </div>
                    </li>
                </ul>
            </Panel>

            <!-- How to connect -->
            <Panel :title="t('connector.guide.title')">
                <p class="-mt-3 mb-4 text-sm text-slate-600 dark:text-slate-400">{{ t("connector.guide.intro") }}</p>

                <SegmentedControl v-model="host" :options="hosts" :label="t('connector.guide.title')" />

                <div class="mt-5 flex flex-col gap-4 text-sm text-slate-700 dark:text-slate-300">
                    <template v-if="host === 'claude'">
                        <p>{{ t("connector.guide.claude.intro") }}</p>
                        <ol class="list-decimal space-y-2 pl-5">
                            <li v-for="step in steps('claude', 6)" :key="step">{{ step }}</li>
                        </ol>
                        <CopyField :label="t('connector.address_label')" :value="connectorUrl" />
                        <Notice tone="info">{{ t("connector.guide.claude.note") }}</Notice>
                    </template>

                    <template v-else-if="host === 'claude_code'">
                        <p>{{ t("connector.guide.claude_code.intro") }}</p>
                        <ol class="list-decimal space-y-2 pl-5">
                            <li>{{ t("connector.guide.claude_code.step_1") }}</li>
                        </ol>
                        <CodeBlock :code="snippets.claudeCode" />
                        <ol start="2" class="list-decimal space-y-2 pl-5">
                            <li>{{ t("connector.guide.claude_code.step_2") }}</li>
                            <li>{{ t("connector.guide.claude_code.step_3") }}</li>
                        </ol>
                    </template>

                    <template v-else-if="host === 'hermes'">
                        <p>{{ t("connector.guide.hermes.intro") }}</p>
                        <ol class="list-decimal space-y-2 pl-5">
                            <li>{{ t("connector.guide.hermes.step_1") }}</li>
                        </ol>
                        <CodeBlock :code="snippets.hermesCli" />
                        <ol start="2" class="list-decimal space-y-2 pl-5">
                            <li>{{ t("connector.guide.hermes.step_2") }}</li>
                        </ol>
                        <p>{{ t("connector.guide.hermes.config") }}</p>
                        <CodeBlock :code="snippets.hermesYaml" />
                    </template>

                    <template v-else-if="host === 'chatgpt'">
                        <p>{{ t("connector.guide.chatgpt.intro") }}</p>
                        <ol class="list-decimal space-y-2 pl-5">
                            <li v-for="step in steps('chatgpt', 4)" :key="step">{{ step }}</li>
                        </ol>
                        <CopyField :label="t('connector.address_label')" :value="connectorUrl" />
                        <Notice tone="info">{{ t("connector.guide.chatgpt.note") }}</Notice>
                    </template>

                    <template v-else>
                        <p>{{ t("connector.guide.generic.intro") }}</p>

                        <h3 class="font-semibold text-slate-900 dark:text-white">Cursor</h3>
                        <p>{{ t("connector.guide.generic.cursor") }}</p>
                        <CodeBlock :code="snippets.cursor" />

                        <h3 class="font-semibold text-slate-900 dark:text-white">VS Code</h3>
                        <p>{{ t("connector.guide.generic.vscode") }}</p>
                        <CodeBlock :code="snippets.vscode" />

                        <h3 class="font-semibold text-slate-900 dark:text-white">Gemini CLI</h3>
                        <CodeBlock :code="snippets.gemini" />

                        <h3 class="font-semibold text-slate-900 dark:text-white">{{ t("connector.guide.generic.stdio_title") }}</h3>
                        <p>{{ t("connector.guide.generic.stdio") }}</p>
                        <CodeBlock :code="snippets.mcpRemote" />

                        <h3 class="font-semibold text-slate-900 dark:text-white">{{ t("connector.guide.generic.key_title") }}</h3>
                        <i18n-t keypath="connector.guide.generic.key" tag="p">
                            <template #link>
                                <Link :href="route('settings.api-keys.index')" :class="linkClass">{{ t("navigation.api_keys") }}</Link>
                            </template>
                        </i18n-t>
                        <CodeBlock :code="snippets.header" />
                    </template>
                </div>
            </Panel>

            <!-- What a connected app may and may not do -->
            <Panel :title="t('connector.safety.title')">
                <ul class="-mt-1 list-disc space-y-2 pl-5 text-sm text-slate-700 dark:text-slate-300">
                    <li>{{ t("connector.safety.account") }}</li>
                    <li>{{ t("connector.safety.permissions") }}</li>
                    <li>{{ t("connector.safety.tokens") }}</li>
                    <li>{{ t("connector.safety.sending") }}</li>
                </ul>
            </Panel>
        </div>

        <ConfirmModal
            :show="revoking !== null"
            type="danger"
            :title="t('connector.revoke_title')"
            :message="t('connector.revoke_message')"
            :confirm-text="t('connector.revoke')"
            :processing="processing"
            @close="revoking = null"
            @confirm="revoke"
        >
            {{ revoking?.name }}
        </ConfirmModal>

        <Toast :toast="toast" @close="dismiss" />
    </AuthenticatedLayout>
</template>
