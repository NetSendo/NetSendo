<script setup>
import { computed } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import LanguageSwitcher from "@/Components/LanguageSwitcher.vue";
import PrimaryButton from "@/Components/PrimaryButton.vue";
import SecondaryButton from "@/Components/SecondaryButton.vue";
import Notice from "@/Components/Ui/Notice.vue";
import Panel from "@/Components/Ui/Panel.vue";
import StatusPill from "@/Components/Ui/StatusPill.vue";

/**
 * The connector's consent screen: an AI app (Claude, Hermes, …) asks for access to this
 * NetSendo account. It says who asks, which account is signed in and what is requested;
 * the user may grant less. Approving sends the browser back to the app.
 */
const props = defineProps({
    // A request that cannot be shown (unknown app or return address): no form, only why
    error: { type: String, default: "" },
    errorDescription: { type: String, default: "" },
    requestId: { type: String, default: "" },
    client: { type: Object, default: () => ({}) },
    account: { type: Object, default: () => ({}) },
    scopeGroups: { type: Array, default: () => [] },
    requestedScopes: { type: Array, default: () => [] },
    instanceHost: { type: String, default: "" },
});

const { t } = useI18n();

const form = useForm({
    request_id: props.requestId,
    decision: "approve",
    scopes: [...props.requestedScopes],
});

// Only what the app asked for is offered, grouped by what it concerns
const groups = computed(() =>
    props.scopeGroups
        .map((group) => ({
            ...group,
            read: props.requestedScopes.includes(group.read) ? group.read : null,
            write: props.requestedScopes.includes(group.write) ? group.write : null,
        }))
        .filter((group) => group.read || group.write),
);

const readScopes = computed(() => groups.value.map((group) => group.read).filter(Boolean));
const scopeLabel = (scope) => t(`api_keys.permissions.${scope.replace(":", "_")}`);

const selectAll = () => (form.scopes = [...props.requestedScopes]);
const selectReadOnly = () => (form.scopes = [...readScopes.value]);

const decide = (decision) => {
    form.decision = decision;
    // The answer is a redirect to the app (another site or a local program)
    form.post(route("connector.oauth.decide"), { preserveScroll: true });
};
</script>

<template>
    <Head :title="t('connector.authorize.title')" />

    <div class="flex min-h-screen flex-col bg-slate-50 text-slate-900 dark:bg-slate-900 dark:text-white">
        <!-- The logo has white lettering, so it always sits on dark -->
        <header class="flex items-center justify-between gap-4 bg-slate-950 px-4 py-4 sm:px-8">
            <img src="/images/brand/netsendo-logo.png" alt="NetSendo" class="h-8 w-auto" />
            <LanguageSwitcher variant="dark" />
        </header>

        <main class="mx-auto flex w-full max-w-xl flex-1 flex-col gap-4 px-4 py-8 sm:py-12">
            <!-- The request cannot be trusted enough to answer the app -->
            <template v-if="error">
                <h1 class="text-2xl font-semibold tracking-tight">{{ t("connector.authorize.invalid_title") }}</h1>
                <Notice tone="danger" :title="t('connector.authorize.invalid_text')">
                    {{ errorDescription }}
                </Notice>
                <p class="text-sm text-slate-600 dark:text-slate-400">{{ t("connector.authorize.invalid_hint") }}</p>
            </template>

            <template v-else>
                <div>
                    <h1 class="break-words text-2xl font-semibold tracking-tight">
                        {{ t("connector.authorize.heading", { app: client.name }) }}
                    </h1>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                        {{ t("connector.authorize.intro", { app: client.name, host: instanceHost }) }}
                    </p>
                </div>

                <!-- Who is asking -->
                <Panel :title="t('connector.authorize.app_title')">
                    <template #actions>
                        <StatusPill :tone="client.verified_host ? 'success' : 'warning'">
                            {{ client.verified_host ? t("connector.authorize.verified") : t("connector.authorize.unverified") }}
                        </StatusPill>
                    </template>

                    <p class="break-words text-base font-semibold">{{ client.name }}</p>
                    <p v-if="client.verified_host" class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                        {{ t("connector.authorize.verified_text", { host: client.verified_host }) }}
                    </p>
                    <p v-else class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                        {{ t("connector.authorize.unverified_text") }}
                    </p>
                    <p class="mt-3 break-all text-sm text-slate-600 dark:text-slate-400">
                        {{
                            client.is_local
                                ? t("connector.authorize.returns_local")
                                : t("connector.authorize.returns_to", { host: client.redirect_host })
                        }}
                    </p>
                </Panel>

                <!-- Which account -->
                <Panel :title="t('connector.authorize.account_title')">
                    <p class="break-words text-base font-semibold">{{ account.name }}</p>
                    <p class="break-all text-sm text-slate-600 dark:text-slate-400">{{ account.email }}</p>
                    <p v-if="account.is_team_member" class="mt-3 text-sm text-slate-600 dark:text-slate-400">
                        {{ t("connector.authorize.team_member", { owner: account.owner_name }) }}
                    </p>
                    <p class="mt-3 text-sm text-slate-600 dark:text-slate-400">{{ t("connector.authorize.other_account") }}</p>
                </Panel>

                <!-- What it may do -->
                <Panel :title="t('connector.authorize.permissions_title')">
                    <template #actions>
                        <button
                            type="button"
                            class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                            @click="selectReadOnly"
                        >
                            {{ t("connector.authorize.read_only") }}
                        </button>
                        <button
                            type="button"
                            class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                            @click="selectAll"
                        >
                            {{ t("connector.authorize.select_all") }}
                        </button>
                    </template>

                    <p class="-mt-3 mb-4 text-sm text-slate-600 dark:text-slate-400">
                        {{ t("connector.authorize.permissions_text") }}
                    </p>

                    <ul class="grid gap-x-6 gap-y-1 sm:grid-cols-2">
                        <template v-for="group in groups" :key="group.resource">
                            <li v-for="scope in [group.read, group.write].filter(Boolean)" :key="scope">
                                <label class="flex min-h-10 cursor-pointer items-center gap-3 text-sm">
                                    <input
                                        v-model="form.scopes"
                                        type="checkbox"
                                        :value="scope"
                                        class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800"
                                    />
                                    <span>{{ scopeLabel(scope) }}</span>
                                </label>
                            </li>
                        </template>
                    </ul>

                    <p v-if="form.errors.scopes" role="alert" class="mt-3 text-sm text-rose-600 dark:text-rose-400">
                        {{ form.errors.scopes }}
                    </p>
                </Panel>

                <Notice v-if="form.errors.request_id" tone="danger">{{ form.errors.request_id }}</Notice>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <SecondaryButton type="button" class="justify-center" :disabled="form.processing" @click="decide('deny')">
                        {{ t("connector.authorize.deny") }}
                    </SecondaryButton>
                    <PrimaryButton type="button" class="justify-center" :disabled="form.processing" @click="decide('approve')">
                        {{ t("connector.authorize.allow") }}
                    </PrimaryButton>
                </div>

                <p class="text-sm text-slate-600 dark:text-slate-400">{{ t("connector.authorize.revoke_hint") }}</p>
            </template>
        </main>
    </div>
</template>
