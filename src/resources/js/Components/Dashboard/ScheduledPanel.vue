<script setup>
import { Link } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Panel from "@/Components/Ui/Panel.vue";
import { useDateTime } from "@/Composables/useDateTime";

const { locale } = useI18n();
const { formatDate, formatTime } = useDateTime();

defineProps({
    // [{ id, name, channel, scheduled_at, recipients, lists }]
    items: { type: Array, default: null },
});

const day = (date) => formatDate(date, null, { year: undefined, month: undefined });
const month = (date) => formatDate(date, null, { year: undefined, day: undefined, month: "short" });
const number = (value) => new Intl.NumberFormat(locale.value).format(value);

const href = (item) =>
    item.channel === "sms" ? route("sms.edit", item.id) : route("messages.edit", item.id);
</script>

<template>
    <Panel :title="$t('dashboard.scheduled.title')">
        <div v-if="items === null" class="flex flex-col gap-3">
            <div
                v-for="n in 2"
                :key="n"
                class="h-12 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-700/40"
            ></div>
        </div>

        <div v-else-if="!items.length" class="flex flex-col items-start gap-2">
            <p class="text-sm text-slate-600 dark:text-slate-400">
                {{ $t("dashboard.scheduled.empty") }}
            </p>
            <Link
                :href="route('messages.create')"
                class="text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
            >
                {{ $t("dashboard.scheduled.plan") }}
            </Link>
        </div>

        <ul v-else class="flex flex-col gap-4">
            <li v-for="item in items" :key="item.id" class="flex items-start gap-3">
                <div
                    aria-hidden="true"
                    class="flex w-12 shrink-0 flex-col items-center rounded-lg border border-slate-200 py-1 dark:border-slate-700/60"
                >
                    <span
                        class="text-[11px] font-medium uppercase text-slate-600 dark:text-slate-400"
                        >{{ month(item.scheduled_at) }}</span
                    >
                    <span
                        class="text-base font-semibold leading-tight tabular-nums text-slate-900 dark:text-white"
                        >{{ day(item.scheduled_at) }}</span
                    >
                </div>
                <div class="min-w-0">
                    <Link
                        :href="href(item)"
                        class="block truncate text-sm font-medium text-slate-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-300"
                        :title="item.name"
                    >
                        {{ item.name || $t("dashboard.recent_campaigns.no_subject") }}
                    </Link>
                    <p class="truncate text-xs text-slate-600 dark:text-slate-400">
                        <span class="sr-only">{{ formatDate(item.scheduled_at) }}, </span>
                        <span class="tabular-nums">{{ formatTime(item.scheduled_at) }}</span>
                        <span v-if="item.channel === 'sms'"> · SMS</span>
                        <span v-if="item.lists.length"> · {{ item.lists.join(", ") }}</span>
                        <span v-if="item.recipients">
                            ·
                            {{
                                $t("dashboard.scheduled.recipients", {
                                    count: number(item.recipients),
                                })
                            }}
                        </span>
                    </p>
                </div>
            </li>
        </ul>
    </Panel>
</template>
