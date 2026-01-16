<script setup>
import { ref, onMounted, computed } from "vue";
import { useI18n } from "vue-i18n";
import { useDateTime } from "@/Composables/useDateTime";

const { t, tm } = useI18n();
const { locale, formatDate: formatDateBase } = useDateTime();

const isOpen = ref(false);
const isLoadingUpdates = ref(false);
const versionData = ref(null);
const versionError = ref(null);

const toggle = () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value && !versionData.value) {
        checkForUpdates();
    }
};

const close = () => {
    isOpen.value = false;
};

const handleQuickStart = () => {
    close();
    // Dispatch global event for Dashboard to pick up
    window.dispatchEvent(new CustomEvent("open-onboarding-modal"));
};

const links = computed(() => [
    {
        label: t("help_menu.quick_start", "Szybki start"),
        href: "#",
        icon: "rocket",
        onClick: handleQuickStart,
        internal: true,
    },
    {
        label: t("help_menu.documentation"),
        href: "https://netsendo.com/en/docs",
        icon: "book",
    },
    { label: t("help_menu.api_reference"), href: "/docs/api", icon: "code" },
    {
        label: t("help_menu.community_forum"),
        href: "https://forum.netsendo.com",
        icon: "users",
    },
    {
        label: t("help_menu.telegram", "Oficjalny kanał Telegram"),
        href: "https://t.me/netsendo_official",
        icon: "telegram",
    },
    // { label: t('help_menu.courses_training'), href: 'https://netsendo.com/courses', icon: 'academic' },
    {
        label: t("help_menu.report_bug"),
        href: "https://github.com/NetSendo/NetSendo/issues/new?template=bug_report.md",
        icon: "bug",
    },
    {
        label: t("help_menu.system_logs", "Logi systemowe"),
        href: "/settings/logs",
        icon: "terminal",
        internal: true,
    },
]);

const whatsNew = computed(() => {
    const list = tm("help_menu.whats_new_list");
    return Array.isArray(list) ? list : [];
});

// Check for updates from GitHub
const checkForUpdates = async () => {
    isLoadingUpdates.value = true;
    versionError.value = null;

    try {
        const response = await fetch("/api/version/check", {
            headers: {
                Accept: "application/json",
            },
        });

        if (response.ok) {
            versionData.value = await response.json();
        } else {
            // If version check fails, just show current version
            const currentResponse = await fetch("/api/version/current");
            if (currentResponse.ok) {
                const current = await currentResponse.json();
                versionData.value = {
                    current_version: current.version,
                    updates_available: false,
                };
            }
        }
    } catch (error) {
        versionError.value = t("help_menu.unable_to_check");
        // Fallback to static version
        versionData.value = {
            current_version: "1.0.0",
            updates_available: false,
        };
    } finally {
        isLoadingUpdates.value = false;
    }
};

// Refresh updates (bypass cache)
const refreshUpdates = async () => {
    isLoadingUpdates.value = true;
    versionError.value = null;

    try {
        const response = await fetch("/api/version/refresh", {
            headers: {
                Accept: "application/json",
            },
        });

        if (response.ok) {
            versionData.value = await response.json();
        }
    } catch (error) {
        versionError.value = t("help_menu.unable_to_check");
    } finally {
        isLoadingUpdates.value = false;
    }
};

// Format date
const formatDate = (dateString) => {
    if (!dateString) return "";
    try {
        return formatDateBase(dateString, null, {
            year: "numeric",
            month: "short",
            day: "numeric",
        });
    } catch {
        return dateString;
    }
};

// Initialize on mount
onMounted(() => {
    // Pre-fetch version info
    checkForUpdates();
});
</script>

<template>
    <div class="relative">
        <!-- Help Button with update badge -->
        <button
            @click="toggle"
            class="group flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-300 transition-all duration-200 hover:bg-white/5 hover:text-white"
        >
            <span
                class="relative flex h-5 w-5 items-center justify-center text-slate-400 group-hover:text-indigo-400"
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
                        d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                </svg>
                <!-- Update notification badge -->
                <span
                    v-if="
                        versionData?.updates_available &&
                        versionData?.update_count > 0
                    "
                    class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-amber-500 text-[10px] font-bold text-white"
                >
                    {{
                        versionData.update_count > 9
                            ? "9+"
                            : versionData.update_count
                    }}
                </span>
            </span>
            <span>{{ $t("help_menu.title") }}</span>
            <svg
                class="ml-auto h-4 w-4 text-slate-500 transition-transform duration-200"
                :class="{ 'rotate-180': isOpen }"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M5 15l7-7 7 7"
                />
            </svg>
        </button>

        <!-- Modal Popup -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-2"
        >
            <div
                v-if="isOpen"
                class="absolute bottom-full left-0 right-0 mb-2 max-h-[80vh] overflow-y-auto rounded-xl bg-slate-800 shadow-2xl ring-1 ring-white/10"
            >
                <!-- Header -->
                <div
                    class="flex items-center justify-between border-b border-white/10 px-4 py-3"
                >
                    <span class="text-sm font-semibold text-white">{{
                        $t("help_menu.title")
                    }}</span>
                    <button
                        @click="close"
                        class="rounded-lg p-1 text-slate-400 hover:bg-white/10 hover:text-white"
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
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>

                <!-- Available Updates Section -->
                <div
                    v-if="
                        versionData?.updates_available &&
                        versionData?.new_versions?.length > 0
                    "
                    class="border-b border-white/10 bg-amber-500/10 p-3"
                >
                    <div class="mb-2 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <svg
                                class="h-4 w-4 text-amber-400"
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
                            <span
                                class="text-xs font-semibold uppercase tracking-wider text-amber-400"
                            >
                                {{
                                    $t("help_menu.updates_available", {
                                        count: versionData.update_count,
                                    })
                                }}
                            </span>
                        </div>
                        <button
                            @click="refreshUpdates"
                            :disabled="isLoadingUpdates"
                            class="rounded p-1 text-slate-400 transition-colors hover:bg-white/10 hover:text-white disabled:opacity-50"
                            title="Refresh"
                        >
                            <svg
                                :class="[
                                    'h-3.5 w-3.5',
                                    isLoadingUpdates ? 'animate-spin' : '',
                                ]"
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
                        </button>
                    </div>

                    <!-- Update list -->
                    <div class="space-y-2">
                        <div
                            v-for="version in versionData.new_versions.slice(
                                0,
                                3
                            )"
                            :key="version.version"
                            class="rounded-lg bg-white/5 p-2"
                        >
                            <div class="flex items-center justify-between">
                                <span class="font-medium text-white"
                                    >v{{ version.version }}</span
                                >
                                <span class="text-xs text-slate-400">{{
                                    formatDate(version.published_at)
                                }}</span>
                            </div>
                            <p
                                v-if="
                                    version.name && version.name !== version.tag
                                "
                                class="mt-1 text-xs text-slate-300"
                            >
                                {{ version.name }}
                            </p>
                        </div>

                        <a
                            v-if="
                                versionData.new_versions.length > 0 &&
                                versionData.new_versions[0].url
                            "
                            :href="versionData.new_versions[0].url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-2 flex items-center justify-center gap-2 rounded-lg bg-amber-500/20 px-3 py-2 text-sm font-medium text-amber-400 transition-colors hover:bg-amber-500/30"
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
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                                />
                            </svg>
                            {{ $t("help_menu.download_latest") }}
                        </a>
                    </div>
                </div>

                <!-- Loading state for updates -->
                <div
                    v-else-if="isLoadingUpdates"
                    class="border-b border-white/10 p-3"
                >
                    <div class="flex items-center gap-2 text-slate-400">
                        <svg
                            class="h-4 w-4 animate-spin"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            ></circle>
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            ></path>
                        </svg>
                        <span class="text-xs">{{
                            $t("help_menu.checking_updates")
                        }}</span>
                    </div>
                </div>

                <!-- Up to date message -->
                <div
                    v-else-if="
                        versionData &&
                        !versionData.updates_available &&
                        !versionData.error
                    "
                    class="border-b border-white/10 p-3"
                >
                    <div class="flex items-center gap-2 text-green-400">
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
                                d="M5 13l4 4L19 7"
                            />
                        </svg>
                        <span class="text-xs font-medium">{{
                            $t("help_menu.up_to_date")
                        }}</span>
                    </div>
                </div>

                <!-- Links -->
                <div class="p-2">
                    <a
                        v-for="link in links"
                        :key="link.href"
                        :href="link.href"
                        :target="link.internal ? '_self' : '_blank'"
                        :rel="link.internal ? '' : 'noopener noreferrer'"
                        @click="
                            link.onClick
                                ? ($event.preventDefault(), link.onClick())
                                : null
                        "
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-slate-300 transition-colors hover:bg-white/5 hover:text-white"
                    >
                        <!-- Rocket -->
                        <svg
                            v-if="link.icon === 'rocket'"
                            class="h-4 w-4 text-slate-500"
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
                        <!-- Book -->
                        <svg
                            v-else-if="link.icon === 'book'"
                            class="h-4 w-4 text-slate-500"
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
                        <!-- Users -->
                        <svg
                            v-else-if="link.icon === 'users'"
                            class="h-4 w-4 text-slate-500"
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
                        <!-- Telegram -->
                        <svg
                            v-else-if="link.icon === 'telegram'"
                            class="h-4 w-4 text-slate-500"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"
                            />
                        </svg>
                        <!-- Academic -->
                        <svg
                            v-else-if="link.icon === 'academic'"
                            class="h-4 w-4 text-slate-500"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 14l9-5-9-5-9 5 9 5z"
                            />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"
                            />
                        </svg>
                        <!-- Bug -->
                        <svg
                            v-else-if="link.icon === 'bug'"
                            class="h-4 w-4 text-slate-500"
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
                        <!-- Code -->
                        <svg
                            v-else-if="link.icon === 'code'"
                            class="h-4 w-4 text-slate-500"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"
                            />
                        </svg>
                        <!-- Terminal -->
                        <svg
                            v-else-if="link.icon === 'terminal'"
                            class="h-4 w-4 text-slate-500"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                            />
                        </svg>
                        {{ link.label }}
                        <svg
                            v-if="!link.internal"
                            class="ml-auto h-3 w-3 text-slate-600"
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
                    </a>
                </div>

                <!-- What's New -->
                <div class="border-t border-white/10 p-3">
                    <div class="mb-2 flex items-center gap-2">
                        <span
                            class="text-xs font-semibold uppercase tracking-wider text-slate-400"
                            >{{ $t("help_menu.whats_new") }}</span
                        >
                        <span
                            class="rounded-full bg-indigo-500/20 px-1.5 py-0.5 text-[10px] font-medium text-indigo-400"
                            >{{ $t("help_menu.new_tag") }}</span
                        >
                    </div>
                    <div class="space-y-1">
                        <div
                            v-for="(item, index) in whatsNew.slice(0, 5)"
                            :key="index"
                            class="flex items-center gap-2 text-xs text-slate-400"
                        >
                            <span
                                class="h-1 w-1 flex-shrink-0 rounded-full bg-indigo-500"
                            ></span>
                            <span>{{ item.value || item }}</span>
                        </div>
                    </div>
                    <a
                        href="/update"
                        class="mt-2 inline-block text-xs text-indigo-400 hover:text-indigo-300"
                    >
                        {{ $t("help_menu.full_changelog") }} →
                    </a>
                </div>

                <!-- Footer -->
                <div class="border-t border-white/10 px-4 py-3">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-500">
                            NetSendo v{{ $page.props.appVersion || "1.0.0" }}
                        </span>
                        <a
                            href="https://netsendo.com"
                            target="_blank"
                            class="text-slate-400 hover:text-white"
                        >
                            {{ $t("help_menu.about") }}
                        </a>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Backdrop to close -->
        <div v-if="isOpen" class="fixed inset-0 z-[-1]" @click="close"></div>
    </div>
</template>
