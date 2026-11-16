<script setup>
import { computed } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";

// One-time question to the administrator: switch on the opt-in, anonymous usage report?
// Shown until it is answered either way (docs/TELEMETRY.md).
const page = usePage();
const visible = computed(() => page.props.telemetry?.ask === true);

const enable = () =>
    router.post(route("settings.telemetry.update"), { enabled: true }, { preserveScroll: true });
const dismiss = () =>
    router.post(route("settings.telemetry.dismiss"), {}, { preserveScroll: true });
</script>

<template>
    <div
        v-if="visible"
        class="mb-6 rounded-2xl border border-indigo-200 bg-indigo-50 p-5 dark:border-indigo-900/60 dark:bg-indigo-950/40"
    >
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="font-semibold text-slate-900 dark:text-white">
                    {{ $t("telemetry.notice_title") }}
                </p>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    {{ $t("telemetry.notice_text") }}
                </p>
            </div>
            <div class="flex flex-shrink-0 flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    @click="enable"
                >
                    {{ $t("telemetry.notice_enable") }}
                </button>
                <Link
                    :href="route('settings.telemetry.index')"
                    class="rounded-lg px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100 dark:text-indigo-300 dark:hover:bg-indigo-900/40"
                >
                    {{ $t("telemetry.notice_details") }}
                </Link>
                <button
                    type="button"
                    class="rounded-lg px-4 py-2 text-sm text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"
                    @click="dismiss"
                >
                    {{ $t("telemetry.notice_dismiss") }}
                </button>
            </div>
        </div>
    </div>
</template>
