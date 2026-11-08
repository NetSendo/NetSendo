<script setup>
import { ref, onMounted, onBeforeUnmount } from "vue";
import { useI18n } from "vue-i18n";

// Where to get a server for the own sending server: outgoing port 25, additional IPv4
// addresses and PTR records (App\Services\Nmi\HostingProviders)
defineProps({
    providers: { type: Array, default: () => [] },
});

const { t } = useI18n();
const open = ref(false);

// Links from the guide (#hosting-providers) open the comparison
const openOnHash = () => {
    if (window.location.hash === "#hosting-providers") open.value = true;
};
onMounted(() => {
    openOnHash();
    window.addEventListener("hashchange", openOnHash);
});
onBeforeUnmount(() => window.removeEventListener("hashchange", openOnHash));

const port25Label = (value) =>
    ({
        open: t("nmi.hosting.port25_open"),
        on_request: t("nmi.hosting.port25_on_request"),
        blocked: t("nmi.hosting.port25_blocked"),
        unconfirmed: t("nmi.hosting.port25_unconfirmed"),
    })[value] || value;

const port25Class = (value) =>
    ({
        open: "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400",
        on_request: "bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400",
        blocked: "bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400",
        unconfirmed: "bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-gray-300",
    })[value] || "";

const ptrLabel = (value) =>
    ({
        panel: t("nmi.hosting.ptr_panel"),
        panel_api: t("nmi.hosting.ptr_panel_api"),
        api: t("nmi.hosting.ptr_api"),
        ticket: t("nmi.hosting.ptr_ticket"),
        none: t("nmi.hosting.ptr_none"),
    })[value] || value;

const priceLabel = (provider) => {
    if (!provider.price) return "";
    const period = { month: t("nmi.hosting.per_month"), hour: t("nmi.hosting.per_hour") }[provider.price_period];
    return period ? `${provider.price} ${period}` : provider.price;
};

const fitLabel = (value) =>
    ({
        recommended: t("nmi.hosting.fit_recommended"),
        possible: t("nmi.hosting.fit_possible"),
        avoid: t("nmi.hosting.fit_avoid"),
    })[value] || "";

const fitClass = (value) =>
    ({
        recommended: "text-emerald-600 dark:text-emerald-400",
        possible: "text-gray-600 dark:text-gray-300",
        avoid: "text-rose-600 dark:text-rose-400",
    })[value] || "";

const note = (key) => {
    const text = t(`nmi.hosting.notes.${key}`);
    return text === `nmi.hosting.notes.${key}` ? "" : text;
};
</script>

<template>
    <div class="rounded-xl border bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <button
            type="button"
            @click="open = !open"
            class="flex w-full items-center justify-between gap-3 px-6 py-4 text-left"
            :aria-expanded="open"
        >
            <div>
                <h3 class="font-semibold text-gray-900 dark:text-white">{{ $t("nmi.hosting.title") }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $t("nmi.hosting.subtitle") }}</p>
            </div>
            <svg class="h-5 w-5 flex-shrink-0 text-gray-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div v-if="open" class="space-y-4 border-t border-gray-200 px-6 py-4 text-sm text-gray-600 dark:border-slate-700 dark:text-gray-300">
            <ol class="list-decimal space-y-1 pl-5">
                <li>{{ $t("nmi.hosting.need_port25") }}</li>
                <li>{{ $t("nmi.hosting.need_ips") }}</li>
                <li>{{ $t("nmi.hosting.need_ptr") }}</li>
            </ol>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $t("nmi.hosting.disclaimer", { date: providers[0]?.checked_at || "" }) }}</p>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-left dark:divide-slate-700">
                    <thead class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="py-2 pr-4">{{ $t("nmi.hosting.col_provider") }}</th>
                            <th class="py-2 pr-4">{{ $t("nmi.hosting.col_port25") }}</th>
                            <th class="py-2 pr-4">{{ $t("nmi.hosting.col_ips") }}</th>
                            <th class="py-2 pr-4">{{ $t("nmi.hosting.col_ptr") }}</th>
                            <th class="py-2">{{ $t("nmi.hosting.col_notes") }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 align-top dark:divide-slate-700">
                        <tr v-for="provider in providers" :key="provider.key">
                            <td class="py-3 pr-4">
                                <div class="font-semibold text-gray-900 dark:text-white">{{ provider.name }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ provider.products }}</div>
                                <div class="mt-1 text-xs font-medium" :class="fitClass(provider.fit)">{{ fitLabel(provider.fit) }}</div>
                            </td>
                            <td class="py-3 pr-4">
                                <span class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium" :class="port25Class(provider.port25)">
                                    {{ port25Label(provider.port25) }}
                                </span>
                            </td>
                            <td class="py-3 pr-4">
                                <template v-if="provider.extra_ips">
                                    <div>{{ provider.extra_ip_product }}</div>
                                    <div v-if="provider.price" class="text-xs text-gray-500 dark:text-gray-400">{{ priceLabel(provider) }}</div>
                                </template>
                                <span v-else>{{ $t("nmi.hosting.no_extra_ips") }}</span>
                            </td>
                            <td class="py-3 pr-4">{{ ptrLabel(provider.ptr) }}</td>
                            <td class="py-3">
                                <p v-if="note(provider.key)">{{ note(provider.key) }}</p>
                                <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs">
                                    <a v-if="provider.links.extra_ips" :href="provider.links.extra_ips" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline dark:text-indigo-400">{{ $t("nmi.hosting.link_extra_ips") }}</a>
                                    <a v-if="provider.links.configure" :href="provider.links.configure" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline dark:text-indigo-400">{{ $t("nmi.hosting.link_configure") }}</a>
                                    <a v-if="provider.links.ptr" :href="provider.links.ptr" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline dark:text-indigo-400">{{ $t("nmi.hosting.link_ptr") }}</a>
                                    <a v-if="provider.links.port25" :href="provider.links.port25" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline dark:text-indigo-400">{{ $t("nmi.hosting.link_port25") }}</a>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
