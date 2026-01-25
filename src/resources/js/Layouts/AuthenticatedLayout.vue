<script setup>
import { ref, onMounted, onUnmounted } from "vue";
import { Link, usePage, router } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import Sidebar from "@/Components/Sidebar/Sidebar.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import LanguageSwitcher from "@/Components/LanguageSwitcher.vue";
import NotificationDropdown from "@/Components/NotificationDropdown.vue";
import OnboardingModal from "@/Components/Dashboard/OnboardingModal.vue";
import GlobalSearchPanel from "@/Components/GlobalSearchPanel.vue";
import McpStatusIndicator from "@/Components/McpStatusIndicator.vue";
import { useTheme } from "@/Composables/useTheme";

const page = usePage();
const { isDark } = useTheme();

// Sidebar state
const sidebarCollapsed = ref(false);
const mobileMenuOpen = ref(false);

// CRON status for warning banner
const cronStatus = ref(null);
const showCronWarning = ref(false);
const cronBannerDismissed = ref(false);

const showOnboardingModal = ref(false);
const showSearchPanel = ref(false);

// Global search keyboard shortcut handler
const handleGlobalKeydown = (event) => {
    // Open search with Cmd+K (Mac) or Ctrl+K (Windows/Linux)
    if ((event.metaKey || event.ctrlKey) && event.key === 'k') {
        event.preventDefault();
        showSearchPanel.value = true;
    }
};

const checkCronStatus = async () => {
    try {
        const response = await fetch(route("settings.cron.status"));
        if (response.ok) {
            cronStatus.value = await response.json();
            // Show warning if CRON has never run and banner not dismissed
            showCronWarning.value =
                !cronStatus.value.has_ever_run && !cronBannerDismissed.value;
        }
    } catch (e) {
        // Ignore errors - CRON status is not critical
    }
};

const dismissCronWarning = () => {
    cronBannerDismissed.value = true;
    showCronWarning.value = false;
    // Remember dismissal for this session
    sessionStorage.setItem("cronBannerDismissed", "true");
};

// Handle window resize
const handleResize = () => {
    if (window.innerWidth < 1024) {
        sidebarCollapsed.value = true;
    }
};

onMounted(() => {
    handleResize();
    window.addEventListener("resize", handleResize);
    window.addEventListener("keydown", handleGlobalKeydown);

    // Check if banner was dismissed this session
    cronBannerDismissed.value =
        sessionStorage.getItem("cronBannerDismissed") === "true";

    // Check CRON status
    checkCronStatus();

    // Global listener for Quick Start
    window.addEventListener("open-onboarding-modal", () => {
        showOnboardingModal.value = true;
    });
});

onUnmounted(() => {
    window.removeEventListener("resize", handleResize);
    window.removeEventListener("keydown", handleGlobalKeydown);
});

const toggleSidebar = () => {
    sidebarCollapsed.value = !sidebarCollapsed.value;
};

const toggleMobileMenu = () => {
    mobileMenuOpen.value = !mobileMenuOpen.value;
};
</script>

<template>
    <div class="min-h-screen bg-panel-gradient">
        <!-- Sidebar -->
        <Sidebar
            :collapsed="sidebarCollapsed"
            @toggle="toggleSidebar"
            class="hidden lg:flex"
        />

        <!-- Mobile sidebar backdrop -->
        <div
            v-if="mobileMenuOpen"
            class="fixed inset-0 z-30 bg-black/50 backdrop-blur-sm lg:hidden"
            @click="mobileMenuOpen = false"
        ></div>

        <!-- Mobile sidebar -->
        <div
            class="fixed inset-y-0 left-0 z-40 w-72 transform transition-transform duration-300 lg:hidden"
            :class="mobileMenuOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <Sidebar :collapsed="false" @toggle="mobileMenuOpen = false" />
        </div>

        <!-- Main content -->
        <div
            class="transition-all duration-300"
            :class="sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-72'"
        >
            <!-- CRON Warning Banner -->
            <div
                v-if="showCronWarning"
                class="bg-red-600 text-white px-4 py-3 relative"
            >
                <div
                    class="flex items-center justify-between max-w-7xl mx-auto"
                >
                    <div class="flex items-center gap-3">
                        <svg
                            class="h-5 w-5 flex-shrink-0"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                            />
                        </svg>
                        <span class="text-sm font-medium">
                            {{ $t("dashboard.cron.not_configured_warning") }}
                        </span>
                    </div>
                    <div class="flex items-center gap-4">
                        <Link
                            :href="
                                route('settings.cron.index') +
                                '?tab=instructions'
                            "
                            class="text-sm font-semibold underline hover:no-underline"
                        >
                            {{ $t("dashboard.cron.see_instructions") }}
                        </Link>
                        <button
                            @click="dismissCronWarning"
                            class="text-white/80 hover:text-white"
                        >
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
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Top header -->
            <header
                class="sticky top-0 z-20 border-b border-slate-200/50 bg-white/80 backdrop-blur-xl dark:border-slate-700/50 dark:bg-slate-900/80"
            >
                <div
                    class="flex min-h-16 py-2 items-center justify-between px-4 sm:px-6 lg:px-8"
                >
                    <!-- Left side -->
                    <div class="flex items-center gap-4 min-w-0 flex-1">
                        <!-- Mobile menu button -->
                        <button
                            @click="toggleMobileMenu"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600 transition-colors hover:bg-slate-200 lg:hidden dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700"
                        >
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
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                            </svg>
                        </button>

                        <!-- Page title slot -->
                        <div v-if="$slots.header" class="min-w-0 flex-1">
                            <slot name="header" />
                        </div>
                    </div>

                    <!-- Right side -->
                    <div class="flex items-center gap-3 shrink-0">
                        <!-- Search Trigger -->
                        <button
                            @click="showSearchPanel = true"
                            class="hidden items-center gap-2 rounded-xl bg-slate-100 px-3 py-2 transition-colors hover:bg-slate-200 sm:flex dark:bg-slate-800 dark:hover:bg-slate-700"
                            :title="$t('global_search.open_search', 'Szukaj (⌘K)')"
                        >
                            <svg
                                class="h-5 w-5 text-slate-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                />
                            </svg>
                            <span class="hidden text-sm text-slate-500 lg:inline dark:text-slate-400">
                                {{ $t('global_search.placeholder', 'Szukaj...') }}
                            </span>
                            <kbd
                                class="hidden rounded bg-slate-200 px-1.5 py-0.5 text-xs text-slate-500 md:inline-block dark:bg-slate-700 dark:text-slate-400"
                            >
                                ⌘K
                            </kbd>
                        </button>

                        <!-- Notifications -->
                        <NotificationDropdown />

                        <!-- Language Switcher -->
                        <LanguageSwitcher />

                        <!-- MCP Status Indicator -->
                        <McpStatusIndicator />

                        <!-- 2FA Indicator -->
                        <div
                            v-if="$page.props.auth.user.two_factor_enabled"
                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-green-50 text-green-600 transition-colors dark:bg-green-900/30 dark:text-green-400"
                            :title="$t('auth.two_factor_active')"
                        >
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
                                    d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                                />
                            </svg>
                        </div>

                        <!-- User dropdown -->
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    class="flex items-center gap-3 rounded-xl px-3 py-2 transition-colors hover:bg-slate-100 dark:hover:bg-slate-800"
                                >
                                    <div
                                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 text-sm font-medium text-white"
                                    >
                                        {{
                                            $page.props.auth.user.name
                                                .charAt(0)
                                                .toUpperCase()
                                        }}
                                    </div>
                                    <div class="hidden text-left md:block">
                                        <div
                                            class="text-sm font-medium text-slate-700 dark:text-slate-200"
                                        >
                                            {{ $page.props.auth.user.name }}
                                        </div>
                                        <div
                                            class="text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{ $page.props.auth.user.email }}
                                        </div>
                                    </div>
                                    <svg
                                        class="hidden h-4 w-4 text-slate-400 md:block"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M19 9l-7 7-7-7"
                                        />
                                    </svg>
                                </button>
                            </template>

                            <template #content>
                                <div
                                    class="border-b border-slate-100 px-4 py-3 dark:border-slate-700"
                                >
                                    <div
                                        class="text-sm font-medium text-slate-900 dark:text-white"
                                    >
                                        {{ $page.props.auth.user.name }}
                                    </div>
                                    <div
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        {{ $page.props.auth.user.email }}
                                    </div>
                                </div>

                                <DropdownLink :href="route('profile.edit')">
                                    <div class="flex items-center gap-2">
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
                                        {{ $t("navigation.profile") }}
                                    </div>
                                </DropdownLink>

                                <DropdownLink
                                    :href="route('profile.edit') + '#2fa'"
                                >
                                    <div class="flex items-center gap-2">
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
                                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                                            />
                                        </svg>
                                        {{ $t("navigation.security") }}
                                    </div>
                                </DropdownLink>

                                <DropdownLink :href="route('license.index')">
                                    <div class="flex items-center gap-2">
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
                                                d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"
                                            />
                                        </svg>
                                        {{ $t("navigation.license") }}
                                    </div>
                                </DropdownLink>

                                <div
                                    class="border-t border-slate-100 dark:border-slate-700"
                                ></div>

                                <DropdownLink
                                    :href="route('logout')"
                                    method="post"
                                    as="button"
                                >
                                    <div
                                        class="flex items-center gap-2 text-rose-600 dark:text-rose-400"
                                    >
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
                                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                                            />
                                        </svg>
                                        {{ $t("navigation.logout") }}
                                    </div>
                                </DropdownLink>
                            </template>
                        </Dropdown>
                    </div>
                </div>
            </header>

            <!-- Page content -->
            <main class="p-4 sm:p-6 lg:p-8">
                <slot />
            </main>
        </div>

        <OnboardingModal
            :show="showOnboardingModal"
            @close="showOnboardingModal = false"
        />

        <!-- Global Search Panel -->
        <GlobalSearchPanel
            :show="showSearchPanel"
            @close="showSearchPanel = false"
        />
    </div>
</template>
