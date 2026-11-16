<script setup>
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";

/**
 * The foot of a list: which rows are shown out of how many, the page size, and the pages.
 * Takes a Laravel paginator as Inertia delivers it.
 */
const props = defineProps({
    // { from, to, total, links: [{ url, label, active }] }
    paginator: { type: Object, required: true },
    perPage: { type: [String, Number], default: null },
    perPageOptions: { type: Array, default: () => [10, 30, 50, 100] },
});

defineEmits(["update:perPage"]);

const { locale } = useI18n();

const number = (value) => new Intl.NumberFormat(locale.value).format(value ?? 0);

// Laravel puts "previous" first and "next" last; the pages are in between
const previous = computed(() => props.paginator.links?.[0]);
const next = computed(() => props.paginator.links?.[props.paginator.links.length - 1]);
const pages = computed(() => (props.paginator.links || []).slice(1, -1));
</script>

<template>
    <div
        class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3 border-t border-slate-200 px-5 py-3 text-sm text-slate-600 dark:border-slate-700/60 dark:text-slate-400"
    >
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <span class="tabular-nums">
                {{
                    $t("common.pagination.range", {
                        from: number(paginator.from),
                        to: number(paginator.to),
                        total: number(paginator.total),
                    })
                }}
            </span>
            <label v-if="perPage !== null" class="flex items-center gap-2">
                <span>{{ $t("common.pagination.per_page") }}</span>
                <select
                    :value="perPage"
                    class="rounded-lg border border-slate-300 bg-white py-1 pl-2 pr-8 text-sm text-slate-900 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900/40 dark:text-white"
                    @change="$emit('update:perPage', $event.target.value)"
                >
                    <option v-for="option in perPageOptions" :key="option" :value="option">
                        {{ option }}
                    </option>
                </select>
            </label>
        </div>

        <nav
            v-if="pages.length > 1"
            :aria-label="$t('common.pagination.label')"
            class="flex items-center gap-1"
        >
            <component
                :is="previous.url ? Link : 'span'"
                :href="previous.url || undefined"
                preserve-scroll
                :aria-label="$t('common.previous')"
                :aria-disabled="!previous.url"
                class="flex h-9 w-9 items-center justify-center rounded-lg"
                :class="
                    previous.url
                        ? 'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700/60'
                        : 'text-slate-400 dark:text-slate-600'
                "
            >
                <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </component>

            <template v-for="(page, index) in pages" :key="index">
                <Link
                    v-if="page.url"
                    :href="page.url"
                    preserve-scroll
                    :aria-current="page.active ? 'page' : undefined"
                    class="flex h-9 min-w-9 items-center justify-center rounded-lg px-2 tabular-nums"
                    :class="
                        page.active
                            ? 'bg-slate-900 font-semibold text-white dark:bg-white dark:text-slate-900'
                            : 'text-slate-700 hover:bg-slate-100 max-sm:hidden dark:text-slate-200 dark:hover:bg-slate-700/60'
                    "
                >
                    {{ page.label }}
                </Link>
                <span v-else class="px-1 max-sm:hidden" aria-hidden="true">…</span>
            </template>

            <component
                :is="next.url ? Link : 'span'"
                :href="next.url || undefined"
                preserve-scroll
                :aria-label="$t('common.next')"
                :aria-disabled="!next.url"
                class="flex h-9 w-9 items-center justify-center rounded-lg"
                :class="
                    next.url
                        ? 'text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-700/60'
                        : 'text-slate-400 dark:text-slate-600'
                "
            >
                <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </component>
        </nav>
    </div>
</template>
