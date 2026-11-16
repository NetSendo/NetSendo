<script setup>
import { Link } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Panel from "@/Components/Ui/Panel.vue";
import { useDateTime } from "@/Composables/useDateTime";

const { t, te, locale } = useI18n();
const { formatDate } = useDateTime();

defineProps({
    campaigns: { type: Array, default: null },
    loading: { type: Boolean, default: false },
});

const STATUS_STYLES = {
    sent: "bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300",
    scheduled: "bg-indigo-50 text-indigo-800 dark:bg-indigo-500/10 dark:text-indigo-300",
    draft: "bg-slate-100 text-slate-700 dark:bg-slate-700/60 dark:text-slate-300",
};

const statusStyle = (status) => STATUS_STYLES[status] || STATUS_STYLES.draft;
const statusLabel = (status) => {
    const key = `dashboard.recent_campaigns.status.${status}`;
    return te(key) ? t(key) : status;
};

const number = (value) => new Intl.NumberFormat(locale.value).format(value);
const percent = (value) =>
    `${new Intl.NumberFormat(locale.value, { maximumFractionDigits: 1 }).format(value)}%`;

const shortDate = (date) => formatDate(date, null, { year: undefined, month: "short" });

// A sent e-mail opens its statistics; anything still editable opens the editor
const href = (campaign) => {
    if (campaign.channel === "sms") return route("sms.edit", campaign.id);
    return campaign.status === "sent"
        ? route("messages.stats", campaign.id)
        : route("messages.edit", campaign.id);
};
</script>

<template>
    <Panel :title="$t('dashboard.recent_campaigns.title')" flush>
        <template #actions>
            <Link
                :href="route('messages.index')"
                class="font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
            >
                {{ $t("dashboard.recent_campaigns.view_all") }}
            </Link>
        </template>

        <div v-if="campaigns === null" class="flex flex-col gap-3 p-5">
            <div
                v-for="n in 4"
                :key="n"
                class="h-10 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-700/40"
            ></div>
        </div>

        <div
            v-else-if="!campaigns.length"
            class="flex flex-1 flex-col items-center justify-center gap-2 px-5 py-12 text-center"
        >
            <p class="text-sm font-medium text-slate-900 dark:text-white">
                {{ $t("dashboard.recent_campaigns.empty_title") }}
            </p>
            <p class="text-sm text-slate-600 dark:text-slate-400">
                {{ $t("dashboard.recent_campaigns.empty_description") }}
            </p>
            <Link
                :href="route('messages.create')"
                class="mt-2 inline-flex min-h-10 items-center rounded-xl bg-indigo-600 px-4 text-sm font-semibold text-white transition-colors hover:bg-indigo-500"
            >
                {{ $t("dashboard.recent_campaigns.create_first") }}
            </Link>
        </div>

        <div
            v-else
            class="relative overflow-x-auto"
            :class="{ 'opacity-60 transition-opacity': loading }"
        >
            <table class="w-full sm:min-w-[640px] text-sm">
                <thead>
                    <tr
                        class="border-b border-slate-200 text-left text-xs text-slate-600 dark:border-slate-700/60 dark:text-slate-400"
                    >
                        <th scope="col" class="px-5 py-2 font-medium">
                            {{ $t("dashboard.recent_campaigns.columns.campaign") }}
                        </th>
                        <th scope="col" class="px-3 py-2 font-medium max-sm:hidden">
                            {{ $t("common.status") }}
                        </th>
                        <th scope="col" class="px-3 py-2 text-right font-medium max-sm:hidden">
                            {{ $t("dashboard.recent_campaigns.columns.sent") }}
                        </th>
                        <th scope="col" class="px-3 py-2 text-right font-medium">
                            {{ $t("dashboard.recent_campaigns.columns.opens") }}
                        </th>
                        <th scope="col" class="px-3 py-2 text-right font-medium max-sm:hidden">
                            {{ $t("dashboard.recent_campaigns.columns.clicks") }}
                        </th>
                        <th scope="col" class="py-2 pl-3 pr-5 text-right font-medium max-sm:hidden">
                            {{ $t("common.date") }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="campaign in campaigns"
                        :key="campaign.id"
                        class="border-b border-slate-100 last:border-0 dark:border-slate-700/40"
                    >
                        <td class="max-w-0 px-5 py-3 max-sm:w-full sm:w-[46%]">
                            <Link
                                :href="href(campaign)"
                                class="block truncate font-medium text-slate-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-300"
                                :title="campaign.name"
                            >
                                {{ campaign.name || $t("dashboard.recent_campaigns.no_subject") }}
                            </Link>
                            <div
                                class="truncate text-xs text-slate-600 dark:text-slate-400"
                            >
                                <span v-if="campaign.channel === 'sms'" class="font-medium"
                                    >SMS ·
                                </span>
                                {{
                                    campaign.lists.length
                                        ? campaign.lists.join(", ")
                                        : $t("dashboard.recent_campaigns.no_list")
                                }}
                            </div>
                            <!-- On a phone the status has no column of its own: it sits under the name -->
                            <div class="mt-1.5 sm:hidden">
                                <span
                                    class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="statusStyle(campaign.status)"
                                >
                                    <span
                                        aria-hidden="true"
                                        class="h-1.5 w-1.5 rounded-full bg-current"
                                    ></span>
                                    {{ statusLabel(campaign.status) }}
                                </span>
                            </div>
                        </td>
                        <td class="px-3 py-3 max-sm:hidden">
                            <span
                                class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="statusStyle(campaign.status)"
                            >
                                <span
                                    aria-hidden="true"
                                    class="h-1.5 w-1.5 rounded-full bg-current"
                                ></span>
                                {{ statusLabel(campaign.status) }}
                            </span>
                        </td>
                        <td
                            class="px-3 py-3 text-right tabular-nums text-slate-900 dark:text-slate-100 max-sm:hidden"
                        >
                            {{ campaign.sent ? number(campaign.sent) : "—" }}
                        </td>
                        <td
                            class="px-3 py-3 text-right tabular-nums text-slate-900 dark:text-slate-100"
                        >
                            {{
                                campaign.open_rate === null ? "—" : percent(campaign.open_rate)
                            }}
                        </td>
                        <td
                            class="px-3 py-3 text-right tabular-nums text-slate-900 dark:text-slate-100 max-sm:hidden"
                        >
                            {{
                                campaign.click_rate === null
                                    ? "—"
                                    : percent(campaign.click_rate)
                            }}
                        </td>
                        <td
                            class="whitespace-nowrap py-3 pl-3 pr-5 text-right text-slate-600 dark:text-slate-400 max-sm:hidden"
                        >
                            {{ campaign.date ? shortDate(campaign.date) : "—" }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </Panel>
</template>
