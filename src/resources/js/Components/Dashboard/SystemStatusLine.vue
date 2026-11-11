<script setup>
import { computed } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import { useDateTime } from "@/Composables/useDateTime";

/**
 * The technical state of the installation in one quiet line. When something here is wrong
 * it is raised in the attention strip at the top; this line is the "all good" reading.
 */
const { t, locale } = useI18n();
const { formatRelative, userTimezone } = useDateTime();
const page = usePage();

const props = defineProps({
    cron: { type: Object, default: null },
    // 'ok' | 'stale' | 'never' | null (unknown yet)
    cronState: { type: String, default: null },
    stats: { type: Object, default: null },
});

const number = (value) => new Intl.NumberFormat(locale.value).format(value ?? 0);

const cronText = computed(() => {
    if (props.cronState === "never") return t("dashboard.system.cron_never");
    if (!props.cron?.last_run) return t("dashboard.system.cron_unknown");
    return t("dashboard.system.cron_last_run", { when: formatRelative(props.cron.last_run) });
});

const dotColor = computed(
    () =>
        ({
            ok: "bg-emerald-600 dark:bg-emerald-400",
            stale: "bg-amber-500 dark:bg-amber-400",
            never: "bg-rose-600 dark:bg-rose-400",
        })[props.cronState] || "bg-slate-400",
);
</script>

<template>
    <footer
        class="flex flex-wrap items-center gap-x-5 gap-y-1.5 px-1 text-xs text-slate-600 dark:text-slate-400"
    >
        <span class="inline-flex items-center gap-1.5">
            <span aria-hidden="true" class="h-2 w-2 rounded-full" :class="dotColor"></span>
            {{ cronText }}
        </span>
        <span v-if="stats" class="tabular-nums">
            {{ $t("dashboard.system.queue", { count: number(stats.queue_pending) }) }}
        </span>
        <span v-if="stats" class="tabular-nums">
            {{ $t("dashboard.system.mailboxes", { count: number(stats.mailboxes_count) }) }}
        </span>
        <span>{{ $t("dashboard.clock.timezone_label") }}: {{ userTimezone }}</span>
        <span>NetSendo {{ page.props.appVersion }}</span>
        <Link
            :href="route('settings.cron.index')"
            class="underline underline-offset-2 hover:text-slate-900 dark:hover:text-white"
        >
            {{ $t("dashboard.system.cron_settings") }}
        </Link>
    </footer>
</template>
