<script setup>
import { ref, computed } from "vue";
import { usePage, Link, router } from "@inertiajs/vue3";
import SidebarItem from "./SidebarItem.vue";
import SidebarGroup from "./SidebarGroup.vue";
import HelpMenu from "./HelpMenu.vue";
import ThemeToggle from "@/Components/ThemeToggle.vue";
import { useTheme } from "@/Composables/useTheme";

const props = defineProps({
    collapsed: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["toggle"]);

const page = usePage();
const { isDark } = useTheme();

// Logos - always use light (white) logos since sidebar is always dark
const logoFull =
    "https://gregciupek.com/wp-content/uploads/2025/12/Logo-NetSendo-1700-x-500-px-1.png";
const logoSquare =
    "https://gregciupek.com/wp-content/uploads/2025/12/logo-netsendo-kwadrat-biale.png";

const currentLogo = computed(() => {
    return props.collapsed ? logoSquare : logoFull;
});

// Handle logo click - refresh dashboard
const handleLogoClick = () => {
    router.visit(route("dashboard"), { preserveState: false });
};

// Check if route is active
const isActive = (routeName) => {
    try {
        return route().current(routeName);
    } catch {
        return false;
    }
};

// License info
const license = computed(() => page.props.license);
const isGold = computed(() => license.value?.plan === "GOLD");

// Admin check - for hiding admin-only menu items
const isAdmin = computed(() => page.props.auth?.isAdmin ?? false);

// Accordion Logic
const openGroup = ref(null);

const toggleGroup = (groupLabel) => {
    if (openGroup.value === groupLabel) {
        openGroup.value = null;
    } else {
        openGroup.value = groupLabel;
    }
};

// Initialize open group based on current route
import { onMounted, watch } from "vue";

const updateOpenGroup = () => {
    // Webinary
    if (isActive("webinars.*")) {
        openGroup.value = null; // No group - it's a standalone item
        return;
    }

    // Automatyzacja (Check first because of overlaps like fields.global)
    if (
        isActive("templates.*") ||
        isActive("inserts.*") ||
        isActive("settings.fields.*") ||
        isActive("external-pages.*") ||
        isActive("funnels.*") ||
        isActive("automations.*") ||
        isActive("segmentation.*") ||
        isActive("campaign-architect.*") ||
        isActive("campaign-auditor.*") ||
        isActive("campaign-stats.*")
    ) {
        openGroup.value = "automation";
        return;
    }

    // Lista adresowa (Includes specific subscriber route and other modules)
    if (
        isActive("messages.*") ||
        isActive("multimail.*") ||
        isActive("sms.*") ||
        isActive("subscribers.subscribed") ||
        isActive("stats.*") ||
        isActive("mailinglist.*") ||
        isActive("forms.*") ||
        isActive("settings.fields.*") ||
        isActive("api.*") ||
        isActive("settings.system-emails.*") ||
        isActive("settings.system-pages.*")
    ) {
        openGroup.value = "address_list";
        return;
    }

    // CRM Sprzedażowy (Sales CRM)
    if (
        isActive("crm.*") ||
        isActive("crm.dashboard") ||
        isActive("crm.contacts.*") ||
        isActive("crm.companies.*") ||
        isActive("crm.deals.*") ||
        isActive("crm.tasks.*") ||
        isActive("crm.sequences.*") ||
        isActive("crm.scoring.*") ||
        isActive("crm.cardintel.*") ||
        isActive("crm.import.*")
    ) {
        openGroup.value = "crm_sales";
        return;
    }

    // Listy Kontaktów (old CRM - now renamed to contact lists)
    if (
        isActive("mailing-lists.*") ||
        isActive("sms-lists.*") ||
        isActive("groups.*") ||
        isActive("subscribers.*") ||
        isActive("tags.*")
    ) {
        openGroup.value = "contact_lists";
        return;
    }

    // Statystyki
    if (
        isActive("settings.tracked-links.*") ||
        isActive("settings.stats.*") ||
        isActive("settings.activity-logs.*")
    ) {
        openGroup.value = "statistics";
        return;
    }

    // Products
    if (
        isActive("settings.stripe-products.*") ||
        isActive("settings.polar-products.*")
    ) {
        openGroup.value = "products";
        return;
    }

    // Profit (Affiliate Program)
    if (isActive("affiliate.*")) {
        openGroup.value = "profit";
        return;
    }

    // Media Library
    if (isActive("media.*") || isActive("brands.*")) {
        openGroup.value = "media_library";
        return;
    }

    // Ustawienia
    if (
        isActive("defaults.*") ||
        isActive("smtp.*") ||
        isActive("names.*") ||
        isActive("settings.users.*") ||
        isActive("update.*") ||
        isActive("backup.*") ||
        isActive("profile.*") ||
        isActive("settings.ai-integrations.*") ||
        isActive("settings.integrations.*") ||
        isActive("settings.mailboxes.*") ||
        isActive("settings.cron.*") ||
        isActive("settings.api-keys.*") ||
        isActive("settings.backup.*") ||
        isActive("settings.stripe.*") ||
        isActive("settings.polar.*") ||
        isActive("settings.pixel.*") ||
        isActive("settings.woocommerce.*") ||
        isActive("settings.names.*") ||
        isActive("settings.calendar.*") ||
        isActive("settings.sms-providers.*") ||
        isActive("deliverability.*")
    ) {
        openGroup.value = "settings";
        return;
    }
};

// Watch for route changes (since we use Inertia, the component might stay mounted)
watch(() => page.url, updateOpenGroup, { immediate: true });
</script>

<template>
    <aside
        class="fixed left-0 top-0 z-40 flex h-screen flex-col bg-gradient-to-b from-slate-900 via-slate-800 to-slate-900 transition-all duration-300"
        :class="collapsed ? 'w-20' : 'w-72'"
    >
        <!-- Floating Collapse Button (outside sidebar) -->
        <button
            @click="emit('toggle')"
            class="absolute top-7 z-50 flex h-6 w-6 items-center justify-center rounded-full bg-slate-700 text-slate-300 shadow-lg transition-all duration-300 hover:bg-slate-600 hover:text-white"
            :class="collapsed ? 'left-[72px]' : 'left-[276px]'"
            :title="
                collapsed ? $t('navigation.expand') : $t('navigation.collapse')
            "
        >
            <svg
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

        <!-- Logo Section -->
        <div
            class="flex h-20 items-center justify-center border-b px-4"
            :class="isDark ? 'border-white/5' : 'border-white/10'"
        >
            <button
                @click="handleLogoClick"
                class="flex items-center justify-center"
            >
                <img
                    :src="currentLogo"
                    alt="NetSendo"
                    class="transition-all duration-300 object-contain"
                    :class="
                        collapsed ? 'h-12 w-12' : 'h-10 w-auto max-w-[180px]'
                    "
                />
            </button>
        </div>

        <!-- Navigation -->
        <nav
            class="flex-1 space-y-1 overflow-y-auto px-3 py-4 scrollbar-thin"
            :class="
                isDark
                    ? 'scrollbar-thumb-slate-700'
                    : 'scrollbar-thumb-slate-500'
            "
        >
            <!-- Dashboard -->
            <SidebarItem
                :href="route('dashboard')"
                :active="isActive('dashboard')"
                :collapsed="collapsed"
            >
                <template #icon>
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"
                        />
                    </svg>
                </template>
                Dashboard
            </SidebarItem>

            <!-- Marketplace -->
            <SidebarItem
                href="/marketplace"
                :active="isActive('marketplace.*')"
                :collapsed="collapsed"
            >
                <template #icon>
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"
                        />
                    </svg>
                </template>
                {{ $t("navigation.marketplace") }}
            </SidebarItem>

            <!-- Webinary -->
            <SidebarItem
                :href="route('webinars.index')"
                :active="isActive('webinars.*')"
                :collapsed="collapsed"
            >
                <template #icon>
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"
                        />
                    </svg>
                </template>
                <span class="flex items-center gap-2">
                    {{ $t("navigation.webinars") }}
                    <span
                        class="rounded-full bg-gradient-to-r from-red-500 to-pink-500 px-1.5 py-0.5 text-[9px] font-bold text-white"
                        >{{ $t("navigation.new") }}</span
                    >
                </span>
            </SidebarItem>

            <div class="my-4 border-t border-white/5"></div>

            <!-- Lista adresowa -->
            <SidebarGroup
                :label="$t('navigation.groups.address_list')"
                :collapsed="collapsed"
                :is-open="openGroup === 'address_list'"
                @toggle="toggleGroup('address_list')"
            >
                <template #icon>
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                        />
                    </svg>
                </template>

                <SidebarItem
                    :href="route('messages.create')"
                    :active="isActive('messages.create')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 4v16m8-8H4"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.add_message") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('messages.index')"
                    :active="isActive('messages.index')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 6h16M4 10h16M4 14h16M4 18h16"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.message_list") }}
                </SidebarItem>

                <!-- SMS -->
                <SidebarItem
                    :href="route('sms.create')"
                    :active="isActive('sms.create')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.add_sms") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('sms.index')"
                    :active="isActive('sms.index')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.sms_list") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('settings.system-emails.index')"
                    :active="isActive('settings.system-emails.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                            />
                        </svg>
                    </template>
                    {{ $t("system_emails.title", "Wiadomości Systemowe") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('settings.system-pages.index')"
                    :active="isActive('settings.system-pages.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                            />
                        </svg>
                    </template>
                    {{ $t("system_pages.title", "Strony Systemowe") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('subscribers.index')"
                    :active="isActive('subscribers.index')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.list_subscribers") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('settings.stats.index')"
                    :active="isActive('settings.stats.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.statistics") }}
                </SidebarItem>

                <SidebarItem
                    href="/forms"
                    :active="isActive('forms.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.forms") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('settings.fields.index')"
                    :active="isActive('settings.fields.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.field_management") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('settings.api-keys.index')"
                    :active="isActive('settings.api-keys.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.integration") }}
                </SidebarItem>
            </SidebarGroup>

            <!-- CRM Sprzedażowy (Sales CRM) -->
            <SidebarGroup
                :label="$t('navigation.groups.crm_sales', 'CRM Sprzedażowy')"
                :collapsed="collapsed"
                :is-open="openGroup === 'crm_sales'"
                @toggle="toggleGroup('crm_sales')"
            >
                <template #icon>
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                        />
                    </svg>
                </template>

                <SidebarItem
                    :href="route('crm.dashboard')"
                    :active="isActive('crm.dashboard')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"
                            />
                        </svg>
                    </template>
                    {{ $t("crm.dashboard.title", "Dashboard CRM") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('crm.contacts.index')"
                    :active="isActive('crm.contacts.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                            />
                        </svg>
                    </template>
                    {{ $t("crm.contacts.title", "Kontakty") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('crm.companies.index')"
                    :active="isActive('crm.companies.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                            />
                        </svg>
                    </template>
                    {{ $t("crm.companies.title", "Firmy") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('crm.deals.index')"
                    :active="isActive('crm.deals.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"
                            />
                        </svg>
                    </template>
                    {{ $t("crm.deals.title", "Lejek sprzedaży") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('crm.tasks.index')"
                    :active="isActive('crm.tasks.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
                            />
                        </svg>
                    </template>
                    {{ $t("crm.tasks.title", "Zadania") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('crm.sequences.index')"
                    :active="isActive('crm.sequences.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z"
                            />
                        </svg>
                    </template>
                    {{ $t("crm.sequences.title", "Sekwencje") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('crm.scoring.index')"
                    :active="isActive('crm.scoring.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"
                            />
                        </svg>
                    </template>
                    {{ $t("crm.scoring.title", "Lead Scoring") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('crm.cardintel.index')"
                    :active="isActive('crm.cardintel.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
                            />
                        </svg>
                    </template>
                    <span class="flex items-center gap-2">
                        {{ $t("crm.cardintel.title", "CardIntel Agent") }}
                        <span
                            class="rounded-full bg-gradient-to-r from-violet-500 to-purple-500 px-1.5 py-0.5 text-[9px] font-bold text-white"
                            >{{ $t("navigation.new") }}</span
                        >
                    </span>
                </SidebarItem>

                <SidebarItem
                    :href="route('crm.import.index')"
                    :active="isActive('crm.import.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"
                            />
                        </svg>
                    </template>
                    {{ $t("crm.import", "Import") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('crm.guide')"
                    :active="isActive('crm.guide')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
                            />
                        </svg>
                    </template>
                    {{ $t("crm.guide.title", "Instrukcja") }}
                </SidebarItem>
            </SidebarGroup>

            <!-- Listy Kontaktów (formerly CRM) -->
            <SidebarGroup
                :label="
                    $t('navigation.groups.contact_lists', 'Listy Kontaktów')
                "
                :collapsed="collapsed"
                :is-open="openGroup === 'contact_lists'"
                @toggle="toggleGroup('contact_lists')"
            >
                <template #icon>
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
                        />
                    </svg>
                </template>

                <SidebarItem
                    :href="route('mailing-lists.index')"
                    :active="isActive('mailing-lists.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.address_lists") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('sms-lists.index')"
                    :active="isActive('sms-lists.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"
                            />
                        </svg>
                    </template>
                    {{ $t("sms_lists.title") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('groups.index')"
                    :active="isActive('groups.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.list_groups") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('tags.index')"
                    :active="isActive('tags.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.list_tags") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('subscribers.create')"
                    :active="isActive('subscribers.create')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.add_subscriber") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('subscribers.index')"
                    :active="isActive('subscribers.index')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.subscriber_list") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('subscribers.import')"
                    :active="isActive('subscribers.import')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.import") }}
                </SidebarItem>
            </SidebarGroup>

            <!-- Automatyzacja -->
            <SidebarGroup
                :label="$t('navigation.groups.automation')"
                :collapsed="collapsed"
                :is-open="openGroup === 'automation'"
                @toggle="toggleGroup('automation')"
            >
                <template #icon>
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"
                        />
                    </svg>
                </template>

                <SidebarItem
                    :href="route('templates.index')"
                    :active="isActive('templates.index')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.message_templates") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('inserts.index')"
                    :active="isActive('inserts.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.insert_templates") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('settings.fields.index')"
                    :active="isActive('settings.fields.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.field_management") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('external-pages.index')"
                    :active="isActive('external-pages.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.external_pages") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('funnels.index')"
                    :active="isActive('funnels.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"
                            />
                        </svg>
                    </template>
                    {{ $t("funnels.title") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('automations.index')"
                    :active="isActive('automations.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z"
                            />
                        </svg>
                    </template>
                    {{ $t("automations.title") }}
                </SidebarItem>

                <!-- AutoTag Pro - Segmentation Dashboard -->
                <SidebarItem
                    :href="route('segmentation.index')"
                    :active="isActive('segmentation.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"
                            />
                        </svg>
                    </template>
                    <span class="flex items-center gap-2">
                        Segmentacja
                        <span
                            class="rounded-full bg-gradient-to-r from-emerald-500 to-teal-500 px-1.5 py-0.5 text-[9px] font-bold text-white"
                            >PRO</span
                        >
                    </span>
                </SidebarItem>

                <!-- AI Campaign Architect -->
                <SidebarItem
                    :href="route('campaign-architect.index')"
                    :active="isActive('campaign-architect.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"
                            />
                        </svg>
                    </template>
                    <span class="flex items-center gap-2">
                        {{ $t("campaign_architect.title") }}
                        <span
                            class="rounded-full bg-gradient-to-r from-purple-500 to-pink-500 px-1.5 py-0.5 text-[9px] font-bold text-white"
                            >AI</span
                        >
                    </span>
                </SidebarItem>

                <!-- AI Campaign Auditor -->
                <SidebarItem
                    :href="route('campaign-auditor.index')"
                    :active="isActive('campaign-auditor.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                            />
                        </svg>
                    </template>
                    <span class="flex items-center gap-2">
                        {{ $t("campaign_auditor.title") }}
                        <span
                            class="rounded-full bg-gradient-to-r from-indigo-500 to-purple-500 px-1.5 py-0.5 text-[9px] font-bold text-white"
                            >AI</span
                        >
                    </span>
                </SidebarItem>

                <!-- Campaign Statistics -->
                <SidebarItem
                    :href="route('campaign-stats.index')"
                    :active="isActive('campaign-stats.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"
                            />
                        </svg>
                    </template>
                    <span class="flex items-center gap-2">
                        {{ $t("campaign_stats.title") }}
                        <span
                            class="rounded-full bg-gradient-to-r from-indigo-500 to-purple-500 px-1.5 py-0.5 text-[9px] font-bold text-white"
                            >AI</span
                        >
                    </span>
                </SidebarItem>
            </SidebarGroup>

            <!-- Media Library -->
            <SidebarGroup
                :label="$t('navigation.groups.media_library')"
                :collapsed="collapsed"
                :is-open="openGroup === 'media_library'"
                @toggle="toggleGroup('media_library')"
            >
                <template #icon>
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                        />
                    </svg>
                </template>

                <SidebarItem
                    :href="route('media.index')"
                    :active="isActive('media.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
                            />
                        </svg>
                    </template>
                    {{ $t("media.title") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('brands.index')"
                    :active="isActive('brands.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"
                            />
                        </svg>
                    </template>
                    {{ $t("brands.title") }}
                </SidebarItem>
            </SidebarGroup>

            <!-- Produkty -->
            <SidebarGroup
                :label="$t('navigation.groups.products')"
                :collapsed="collapsed"
                :is-open="openGroup === 'products'"
                @toggle="toggleGroup('products')"
            >
                <template #icon>
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                        />
                    </svg>
                </template>

                <SidebarItem
                    :href="route('settings.stripe-products.index')"
                    :active="isActive('settings.stripe-products.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
                            />
                        </svg>
                    </template>
                    {{ $t("stripe.products") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('settings.polar-products.index')"
                    :active="isActive('settings.polar-products.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                            />
                        </svg>
                    </template>
                    {{ $t("polar.products_title") }}
                </SidebarItem>
            </SidebarGroup>

            <!-- Profit -->
            <SidebarGroup
                :label="$t('navigation.groups.profit')"
                :collapsed="collapsed"
                :is-open="openGroup === 'profit'"
                @toggle="toggleGroup('profit')"
            >
                <template #icon>
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                </template>

                <SidebarItem
                    :href="route('affiliate.index')"
                    :active="isActive('affiliate.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.affiliate_program") }}
                </SidebarItem>
            </SidebarGroup>

            <!-- Statystyki -->
            <SidebarGroup
                :label="$t('navigation.groups.statistics')"
                :collapsed="collapsed"
                :is-open="openGroup === 'statistics'"
                @toggle="toggleGroup('statistics')"
            >
                <template #icon>
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
                        />
                    </svg>
                </template>

                <SidebarItem
                    :href="route('settings.tracked-links.index')"
                    :active="isActive('settings.tracked-links.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.tracked_links") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('settings.stats.index')"
                    :active="isActive('settings.stats.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                            />
                        </svg>
                    </template>
                    {{ $t("settings.global_stats") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('settings.activity-logs.index')"
                    :active="isActive('settings.activity-logs.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                            />
                        </svg>
                    </template>
                    {{ $t("settings.activity_logs") }}
                </SidebarItem>
            </SidebarGroup>

            <!-- Ustawienia -->
            <SidebarGroup
                :label="$t('navigation.groups.settings')"
                :collapsed="collapsed"
                :is-open="openGroup === 'settings'"
                @toggle="toggleGroup('settings')"
            >
                <template #icon>
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                        />
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                        />
                    </svg>
                </template>

                <SidebarItem
                    href="/defaults"
                    :active="isActive('defaults.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.default_settings") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('settings.names.index')"
                    :active="isActive('settings.names.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.name_database") }}
                </SidebarItem>

                <SidebarItem
                    v-if="isAdmin"
                    :href="route('settings.users.index')"
                    :active="isActive('settings.users.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.users") }}
                </SidebarItem>

                <SidebarItem
                    href="/update"
                    :active="isActive('update.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.updates") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('profile.edit')"
                    :active="isActive('profile.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.security_settings") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('settings.ai-integrations.index')"
                    :active="isActive('settings.ai-integrations.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.ai_integrations") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('settings.stripe.index')"
                    :active="isActive('settings.stripe.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
                            />
                        </svg>
                    </template>
                    {{ $t("stripe.settings_title") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('settings.polar.index')"
                    :active="isActive('settings.polar.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                            />
                        </svg>
                    </template>
                    {{ $t("polar.settings_title") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('settings.pixel.index')"
                    :active="isActive('settings.pixel.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                            />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                            />
                        </svg>
                    </template>
                    {{ $t("pixel.settings_title") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('settings.woocommerce.index')"
                    :active="isActive('settings.woocommerce.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            viewBox="0 0 24 24"
                            fill="currentColor"
                            class="h-4 w-4"
                        >
                            <path
                                d="M23.594 9.25h-.587c-.22 0-.421.124-.52.32l-.872 1.746a.586.586 0 0 1-.52.32h-5.76a.586.586 0 0 0-.52.32l-.873 1.747a.586.586 0 0 1-.52.32H7.52a.586.586 0 0 0-.52.32l-.873 1.746a.586.586 0 0 1-.52.32H.406A.407.407 0 0 0 0 16.816v1.932c0 .847.686 1.533 1.533 1.533h20.934c.847 0 1.533-.686 1.533-1.533V9.657a.407.407 0 0 0-.406-.407zM1.533 5.72h20.934c.847 0 1.533.686 1.533 1.532v.47a.407.407 0 0 1-.406.406h-5.01a.586.586 0 0 0-.52.32l-.873 1.747a.586.586 0 0 1-.52.32H9.767a.586.586 0 0 0-.52.32l-.873 1.746a.586.586 0 0 1-.52.32H.406A.407.407 0 0 1 0 10.496V7.252c0-.847.686-1.533 1.533-1.533z"
                            />
                        </svg>
                    </template>
                    {{ $t("settings.woocommerce.title", "WooCommerce") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('settings.integrations.index')"
                    :active="isActive('settings.integrations.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z"
                            />
                        </svg>
                    </template>
                    Integracje Google
                </SidebarItem>

                <SidebarItem
                    :href="route('settings.calendar.index')"
                    :active="isActive('settings.calendar.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                            />
                        </svg>
                    </template>
                    {{ $t("calendar.title") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('settings.mailboxes.index')"
                    :active="isActive('settings.mailboxes.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.mailboxes") }}
                </SidebarItem>

                <!-- Deliverability Shield -->
                <SidebarItem
                    :href="route('deliverability.index')"
                    :active="isActive('deliverability.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                            />
                        </svg>
                    </template>
                    <span class="flex items-center gap-2">
                        {{
                            $t(
                                "navigation.deliverability_shield",
                                "Deliverability Shield",
                            )
                        }}
                        <span
                            class="rounded-full bg-gradient-to-r from-yellow-400 to-amber-500 px-1.5 py-0.5 text-[9px] font-bold text-slate-900"
                            >GOLD</span
                        >
                    </span>
                </SidebarItem>

                <SidebarItem
                    :href="route('settings.sms-providers.index')"
                    :active="isActive('settings.sms-providers.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.sms_providers") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('settings.cron.index')"
                    :active="isActive('settings.cron.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.cron_settings") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('settings.api-keys.index')"
                    :active="isActive('settings.api-keys.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.api_keys", "Klucze API") }}
                </SidebarItem>

                <SidebarItem
                    :href="route('settings.backup.index')"
                    :active="isActive('settings.backup.*')"
                    :collapsed="collapsed"
                >
                    <template #icon>
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"
                            />
                        </svg>
                    </template>
                    {{ $t("navigation.backups") }}
                </SidebarItem>
            </SidebarGroup>
        </nav>

        <!-- Help Menu (above footer) -->
        <div v-if="!collapsed" class="px-3 pb-2">
            <HelpMenu />
        </div>

        <!-- Footer -->
        <div class="border-t border-white/10 p-4">
            <div class="flex items-center justify-between">
                <ThemeToggle />

                <div v-if="!collapsed" class="text-right">
                    <div class="text-xs text-slate-400">
                        NetSendo v{{ $page.props.appVersion || "1.0.0" }}
                    </div>
                    <Link
                        v-if="license?.active"
                        :href="route('license.index')"
                        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium transition-all hover:ring-2 hover:ring-white/20"
                        :class="{
                            'bg-amber-500/20 text-amber-400 hover:bg-amber-500/30':
                                license?.plan === 'GOLD',
                            'bg-slate-500/20 text-slate-400 hover:bg-slate-500/30':
                                license?.plan === 'SILVER',
                        }"
                        :title="$t('navigation.license')"
                    >
                        <span
                            class="h-1.5 w-1.5 rounded-full"
                            :class="
                                license?.plan === 'GOLD'
                                    ? 'bg-amber-500'
                                    : 'bg-slate-400'
                            "
                        ></span>
                        {{ license?.plan }}
                    </Link>
                </div>
            </div>
        </div>
    </aside>
</template>

<style scoped>
/* Custom scrollbar */
.scrollbar-thin::-webkit-scrollbar {
    width: 4px;
}

.scrollbar-thin::-webkit-scrollbar-track {
    background: transparent;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
    background-color: rgb(71 85 105 / 0.5);
    border-radius: 9999px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background-color: rgb(71 85 105 / 0.8);
}
</style>
