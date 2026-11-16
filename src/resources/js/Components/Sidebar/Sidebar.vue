<script setup>
import { computed, nextTick, ref, watch } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import GoldBadge from "@/Components/License/GoldBadge.vue";
import ThemeToggle from "@/Components/ThemeToggle.vue";
import HelpMenu from "./HelpMenu.vue";
import { useLicense } from "@/Composables/useLicense";
import { buildNavigation, isCurrent } from "./navigation";

/**
 * The side menu: the dashboard and nine sections (see ./navigation.js for what goes where).
 * One section is open at a time; the one holding the current page opens by itself.
 * Collapsed, it is a rail of section icons, each with its pages in a fly-out.
 */
const props = defineProps({
    collapsed: { type: Boolean, default: false },
    // The phone drawer: no collapse handle, it closes by tapping outside
    drawer: { type: Boolean, default: false },
});

const emit = defineEmits(["toggle"]);

const { t } = useI18n();
const page = usePage();
const { can } = useLicense();

// The sidebar is dark in both themes, so the logos with white lettering are used.
// Shipped in public/images/brand, so the panel loads no image from another host
const logo = computed(() =>
    props.collapsed
        ? "/images/brand/netsendo-logo-square.png"
        : "/images/brand/netsendo-logo.png",
);

const license = computed(() => page.props.license);
const isAdmin = computed(() => page.props.auth?.isAdmin ?? false);

const sections = computed(() => {
    // Recomputed on every visit: `route().current()` is not reactive by itself
    page.url;

    return buildNavigation(t, { isAdmin: isAdmin.value }).map((section) => {
        const items = section.items.map((item) => ({
            ...item,
            active: !item.heading && isCurrent(item),
        }));

        return {
            ...section,
            items,
            // Collapsed, a section's icon leads to its first page
            href: section.href || items.find((item) => item.href)?.href,
            active: isCurrent(section) || items.some((item) => item.active),
        };
    });
});

const openSection = ref(null);
const nav = ref(null);

watch(
    () => page.url,
    () => {
        openSection.value =
            sections.value.find((section) => section.active && section.items.length)?.key ??
            null;

        // In a long section (settings) the current page may be below the fold of the menu
        nextTick(() =>
            nav.value?.querySelector('[aria-current="page"]')?.scrollIntoView({ block: "nearest" }),
        );
    },
    { immediate: true },
);

const toggleSection = (key) => {
    openSection.value = openSection.value === key ? null : key;
};

const goToDashboard = () => router.visit(route("dashboard"), { preserveState: false });
</script>

<template>
    <aside
        class="fixed left-0 top-0 z-40 flex h-screen flex-col border-r border-white/10 bg-slate-950 transition-[width] duration-300"
        :class="collapsed ? 'w-20' : 'w-64'"
    >
        <button
            v-if="!drawer"
            type="button"
            class="absolute top-7 z-50 flex h-6 w-6 -translate-x-1/2 items-center justify-center rounded-full border border-white/10 bg-slate-800 text-slate-300 transition-colors hover:bg-slate-700 hover:text-white"
            :class="collapsed ? 'left-20' : 'left-64'"
            :aria-label="collapsed ? $t('navigation.expand') : $t('navigation.collapse')"
            :title="collapsed ? $t('navigation.expand') : $t('navigation.collapse')"
            @click="emit('toggle')"
        >
            <svg
                aria-hidden="true"
                class="h-3.5 w-3.5 transition-transform duration-300"
                :class="{ 'rotate-180': collapsed }"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M15 19l-7-7 7-7"
                />
            </svg>
        </button>

        <div class="flex h-16 shrink-0 items-center px-5" :class="{ 'justify-center px-0': collapsed }">
            <button
                type="button"
                class="flex items-center rounded-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-indigo-400"
                :aria-label="$t('navigation.dashboard')"
                @click="goToDashboard"
            >
                <img
                    :src="logo"
                    alt="NetSendo"
                    class="object-contain"
                    :class="collapsed ? 'h-10 w-10' : 'h-9 w-auto max-w-[160px]'"
                />
            </button>
        </div>

        <!-- Collapsed, the fly-outs must be able to leave the rail, so it does not scroll -->
        <nav
            ref="nav"
            :aria-label="$t('navigation.main')"
            class="flex-1 px-3 py-2"
            :class="collapsed ? 'overflow-visible' : 'overflow-y-auto'"
        >
            <ul class="flex flex-col gap-0.5">
                <li
                    v-for="section in sections"
                    :key="section.key"
                    class="group/section relative"
                >
                    <!-- A section that is a page itself (the dashboard), and every section in the rail -->
                    <Link
                        v-if="!section.items.length || collapsed"
                        :href="section.href"
                        :aria-current="section.active && !section.items.length ? 'page' : undefined"
                        :aria-label="collapsed ? section.label : undefined"
                        class="flex min-h-10 items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-400"
                        :class="[
                            section.active
                                ? 'bg-white/10 text-white'
                                : 'text-slate-300 hover:bg-white/5 hover:text-white',
                            collapsed ? 'justify-center' : '',
                        ]"
                    >
                        <svg
                            aria-hidden="true"
                            class="h-5 w-5 shrink-0"
                            :class="section.active ? 'text-indigo-300' : 'text-slate-400'"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                v-for="d in section.icon"
                                :key="d"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.75"
                                :d="d"
                            />
                        </svg>
                        <span v-if="!collapsed" class="truncate">{{ section.label }}</span>
                    </Link>

                    <button
                        v-else
                        type="button"
                        :aria-expanded="openSection === section.key"
                        :aria-controls="`nav-${section.key}`"
                        class="flex min-h-10 w-full items-center gap-3 rounded-lg px-3 text-left text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-400"
                        :class="
                            section.active
                                ? 'text-white'
                                : 'text-slate-300 hover:bg-white/5 hover:text-white'
                        "
                        @click="toggleSection(section.key)"
                    >
                        <svg
                            aria-hidden="true"
                            class="h-5 w-5 shrink-0"
                            :class="section.active ? 'text-indigo-300' : 'text-slate-400'"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                v-for="d in section.icon"
                                :key="d"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.75"
                                :d="d"
                            />
                        </svg>
                        <span class="flex-1 truncate">{{ section.label }}</span>
                        <svg
                            aria-hidden="true"
                            class="h-4 w-4 shrink-0 text-slate-500 transition-transform duration-200"
                            :class="{ 'rotate-90': openSection === section.key }"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 5l7 7-7 7"
                            />
                        </svg>
                    </button>

                    <!-- The section's pages: a list under the header, or, collapsed, a full-height panel
                         beside the rail (a long list such as settings would not fit a small pop-up) -->
                    <ul
                        v-if="section.items.length"
                        v-show="collapsed || openSection === section.key"
                        :id="`nav-${section.key}`"
                        class="flex flex-col gap-0.5"
                        :class="
                            collapsed
                                ? 'invisible fixed left-20 top-0 z-50 h-screen w-60 overflow-y-auto border-r border-white/10 bg-slate-900 px-2 py-4 opacity-0 transition-opacity group-focus-within/section:visible group-focus-within/section:opacity-100 group-hover/section:visible group-hover/section:opacity-100'
                                : 'pb-2 pt-0.5'
                        "
                    >
                        <li
                            v-if="collapsed"
                            class="px-3 pb-1 pt-1.5 text-xs font-semibold text-white"
                        >
                            {{ section.label }}
                        </li>
                        <li v-for="(item, index) in section.items" :key="index">
                            <div
                                v-if="item.heading"
                                class="pb-1 pt-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400"
                                :class="collapsed ? 'px-3' : 'pl-11 pr-3'"
                            >
                                {{ item.heading }}
                            </div>
                            <Link
                                v-else
                                :href="item.href"
                                :aria-current="item.active ? 'page' : undefined"
                                class="flex min-h-9 items-center gap-2 rounded-lg py-1.5 pr-3 text-sm transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-400"
                                :class="[
                                    item.active
                                        ? 'bg-white/10 font-medium text-white'
                                        : 'text-slate-400 hover:bg-white/5 hover:text-white',
                                    collapsed ? 'pl-3' : 'pl-11',
                                ]"
                            >
                                <span class="min-w-0 flex-1 truncate" :title="item.label">{{ item.label }}</span>
                                <GoldBadge v-if="item.gold" :locked="!can(item.gold)" />
                                <span
                                    v-else-if="item.isNew"
                                    class="rounded-full bg-indigo-500/20 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-indigo-200"
                                    >{{ $t("navigation.new") }}</span
                                >
                            </Link>
                        </li>
                    </ul>
                </li>
            </ul>
        </nav>

        <div v-if="!collapsed" class="shrink-0 px-3 pb-2">
            <HelpMenu />
        </div>

        <div
            class="flex shrink-0 items-center justify-between gap-3 border-t border-white/10 p-3"
            :class="{ 'justify-center': collapsed }"
        >
            <ThemeToggle />
            <div v-if="!collapsed" class="flex items-center gap-2 text-xs text-slate-400">
                <span>v{{ $page.props.appVersion || "1.0.0" }}</span>
                <Link
                    v-if="license?.active"
                    :href="route('license.index')"
                    class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-medium transition-colors"
                    :class="
                        license?.plan === 'GOLD'
                            ? 'bg-amber-500/20 text-amber-300 hover:bg-amber-500/30'
                            : 'bg-slate-500/20 text-slate-300 hover:bg-slate-500/30'
                    "
                    :title="$t('navigation.license')"
                >
                    {{ license?.plan }}
                </Link>
            </div>
        </div>
    </aside>
</template>
