<script setup>
import { computed, onMounted, ref } from "vue";
import { Link, router } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import axios from "axios";
import Panel from "@/Components/Ui/Panel.vue";

/**
 * Brain on the dashboard is an inbox of decisions: what waits for the user's approval, with
 * the answer one click away, and a field to ask a question. Status counters live in the Monitor.
 */
const { t } = useI18n();

const MAX_SHOWN = 2;

const loading = ref(true);
const status = ref(null);
const approvals = ref([]);
const deciding = ref(null);
const notice = ref(null);
const question = ref("");

const getJson = async (url) => {
    const response = await fetch(url, { headers: { Accept: "application/json" } });
    if (!response.ok) throw new Error(String(response.status));
    return response.json();
};

const load = async () => {
    const [statusResult, approvalsResult] = await Promise.allSettled([
        getJson(route("brain.api.status")),
        getJson(route("brain.api.approvals.index")),
    ]);

    status.value = statusResult.status === "fulfilled" ? statusResult.value : null;
    approvals.value =
        approvalsResult.status === "fulfilled" ? approvalsResult.value.approvals || [] : [];
    loading.value = false;
};

onMounted(load);

// "mixed" exists only as an effective mode: agents set to different modes
const mode = computed(() => {
    if (!status.value) return null;
    if ((status.value.mode_label || "").endsWith("mixed_label")) return "mixed";
    return status.value.work_mode || "semi_auto";
});

const shown = computed(() =>
    approvals.value.slice(0, MAX_SHOWN).map((approval) => ({
        id: approval.id,
        isGoal: approval.type === "goal_proposal",
        title:
            approval.plan?.title ||
            approval.goal?.title ||
            approval.summary ||
            t("dashboard.brain.untitled_plan"),
        description: approval.plan?.description || approval.goal?.description || "",
        steps: approval.plan?.steps?.length || 0,
    })),
);

const hiddenCount = computed(() => Math.max(0, approvals.value.length - MAX_SHOWN));

const decide = async (approval, approved) => {
    deciding.value = approval.id;
    notice.value = null;

    try {
        const { data } = await axios.post(route("brain.api.approvals.decide", approval.id), {
            approved,
        });
        approvals.value = approvals.value.filter((a) => a.id !== approval.id);
        notice.value = {
            ok: true,
            text:
                data.message ||
                t(approved ? "dashboard.brain.approved" : "dashboard.brain.rejected"),
        };
    } catch (error) {
        notice.value = {
            ok: false,
            text:
                error.response?.data?.error ||
                error.response?.data?.message ||
                t("dashboard.brain.decision_failed"),
        };
    } finally {
        deciding.value = null;
    }
};

const ask = () => {
    const text = question.value.trim();
    if (!text) return;
    router.visit(route("brain.index"), { data: { q: text } });
};
</script>

<template>
    <Panel>
        <template #title>
            <svg
                aria-hidden="true"
                class="h-5 w-5 text-indigo-600 dark:text-indigo-400"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <path d="m12 3 1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z" />
                <path d="M19 15v4M17 17h4" />
            </svg>
            Brain
        </template>
        <template #actions>
            <span
                v-if="mode"
                class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 dark:bg-slate-700/60 dark:text-slate-200"
            >
                {{ $t("brain.mode." + mode) }}
            </span>
        </template>

        <div v-if="loading" class="flex flex-1 flex-col gap-3">
            <div class="h-4 w-2/3 animate-pulse rounded bg-slate-200 dark:bg-slate-700"></div>
            <div class="h-24 animate-pulse rounded-xl bg-slate-100 dark:bg-slate-700/40"></div>
        </div>

        <div v-else-if="!status" class="flex flex-1 flex-col items-start gap-3">
            <p class="text-sm text-slate-600 dark:text-slate-400">
                {{ $t("dashboard.brain.unavailable") }}
            </p>
            <Link
                :href="route('brain.settings')"
                class="text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
            >
                {{ $t("dashboard.brain.configure") }}
            </Link>
        </div>

        <div v-else class="flex flex-1 flex-col gap-4">
            <p class="text-sm text-slate-600 dark:text-slate-400">
                <template v-if="approvals.length">
                    {{ $t("dashboard.brain.waiting", { count: approvals.length }) }}
                </template>
                <template v-else>{{ $t("dashboard.brain.nothing_waiting") }}</template>
            </p>

            <p
                v-if="notice"
                role="status"
                class="rounded-lg px-3 py-2 text-sm"
                :class="
                    notice.ok
                        ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300'
                        : 'bg-rose-50 text-rose-800 dark:bg-rose-500/10 dark:text-rose-300'
                "
            >
                {{ notice.text }}
            </p>

            <ul v-if="shown.length" class="flex flex-col gap-3">
                <li
                    v-for="approval in shown"
                    :key="approval.id"
                    class="flex flex-col gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700/60"
                >
                    <div class="flex flex-col gap-1">
                        <p class="text-sm font-medium text-slate-900 dark:text-white">
                            {{ approval.title }}
                        </p>
                        <p
                            v-if="approval.description"
                            class="line-clamp-2 text-sm text-slate-600 dark:text-slate-400"
                        >
                            {{ approval.description }}
                        </p>
                        <p class="text-xs text-slate-600 dark:text-slate-400">
                            <template v-if="approval.isGoal">{{
                                $t("dashboard.brain.goal_proposal")
                            }}</template>
                            <template v-else>{{
                                $t("dashboard.brain.plan_steps", { count: approval.steps })
                            }}</template>
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            :disabled="deciding === approval.id"
                            class="min-h-9 rounded-lg bg-slate-900 px-3 text-sm font-semibold text-white transition-colors hover:bg-slate-700 disabled:opacity-50 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200"
                            @click="decide(approval, true)"
                        >
                            {{ $t("dashboard.brain.approve") }}
                        </button>
                        <button
                            type="button"
                            :disabled="deciding === approval.id"
                            class="min-h-9 rounded-lg border border-slate-300 px-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 disabled:opacity-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700/60"
                            @click="decide(approval, false)"
                        >
                            {{ $t("dashboard.brain.reject") }}
                        </button>
                        <Link
                            :href="route('brain.monitor')"
                            class="ml-auto text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                        >
                            {{ $t("common.details") }}
                        </Link>
                    </div>
                </li>
            </ul>

            <Link
                v-if="hiddenCount"
                :href="route('brain.monitor')"
                class="text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
            >
                {{ $t("dashboard.brain.more", { count: hiddenCount }) }}
            </Link>

            <dl
                v-if="!shown.length"
                class="grid grid-cols-2 gap-3 text-sm"
            >
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-700/40">
                    <dt class="text-xs text-slate-600 dark:text-slate-400">
                        {{ $t("dashboard.brain.knowledge") }}
                    </dt>
                    <dd class="font-semibold tabular-nums text-slate-900 dark:text-white">
                        {{ status.knowledge_count }}
                    </dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-3 dark:bg-slate-700/40">
                    <dt class="text-xs text-slate-600 dark:text-slate-400">Telegram</dt>
                    <dd class="font-semibold text-slate-900 dark:text-white">
                        {{
                            status.telegram_connected
                                ? $t("dashboard.brain.connected")
                                : $t("dashboard.brain.disconnected")
                        }}
                    </dd>
                </div>
            </dl>

            <form class="mt-auto flex flex-col gap-1.5" @submit.prevent="ask">
                <label
                    for="dashboard-brain-question"
                    class="text-xs font-medium text-slate-600 dark:text-slate-400"
                >
                    {{ $t("dashboard.brain.ask_label") }}
                </label>
                <div
                    class="flex items-center gap-2 rounded-xl border border-slate-300 bg-white pl-3 pr-1.5 focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/40"
                >
                    <input
                        id="dashboard-brain-question"
                        v-model="question"
                        type="text"
                        :placeholder="$t('dashboard.brain.ask_placeholder')"
                        class="min-h-11 min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-slate-900 placeholder:text-slate-500 focus:ring-0 dark:text-white dark:placeholder:text-slate-400"
                    />
                    <button
                        type="submit"
                        :aria-label="$t('dashboard.brain.ask_send')"
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-white transition-colors hover:bg-indigo-500"
                    >
                        <svg
                            aria-hidden="true"
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path d="M5 12h14M13 6l6 6-6 6" />
                        </svg>
                    </button>
                </div>
            </form>
        </div>
    </Panel>
</template>
