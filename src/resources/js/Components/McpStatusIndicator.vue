<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { Link } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const isOpen = ref(false);
const isTesting = ref(false);
const status = ref(null);
const error = ref(null);

// Status colors and icons
const statusConfig = computed(() => {
    if (!status.value) {
        return {
            color: "gray",
            bgClass: "bg-slate-100 dark:bg-slate-800",
            textClass: "text-slate-400 dark:text-slate-500",
            dotClass: "bg-slate-400",
            label: t("mcp_status.never_tested"),
        };
    }

    if (!status.value.is_configured) {
        return {
            color: "yellow",
            bgClass: "bg-amber-50 dark:bg-amber-900/30",
            textClass: "text-amber-600 dark:text-amber-400",
            dotClass: "bg-amber-500",
            label: t("mcp_status.not_configured"),
        };
    }

    if (status.value.status === "success") {
        return {
            color: "green",
            bgClass: "bg-green-50 dark:bg-green-900/30",
            textClass: "text-green-600 dark:text-green-400",
            dotClass: "bg-green-500",
            label: t("mcp_status.connected"),
        };
    }

    return {
        color: "red",
        bgClass: "bg-red-50 dark:bg-red-900/30",
        textClass: "text-red-600 dark:text-red-400",
        dotClass: "bg-red-500",
        label: t("mcp_status.disconnected"),
    };
});

const fetchStatus = async () => {
    try {
        const response = await fetch(route("mcp.status"));
        if (response.ok) {
            status.value = await response.json();
            error.value = null;
        }
    } catch (e) {
        error.value = e.message;
    }
};

const runTest = async () => {
    isTesting.value = true;
    error.value = null;

    try {
        const response = await fetch(route("mcp.test"), {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute("content"),
            },
        });

        const data = await response.json();

        if (response.ok && data.success) {
            // Refresh status after successful test
            await fetchStatus();
        } else {
            error.value = data.message || t("mcp_status.test_failed");
            await fetchStatus();
        }
    } catch (e) {
        error.value = e.message;
    } finally {
        isTesting.value = false;
    }
};

const closeDropdown = (e) => {
    if (!e.target.closest(".mcp-status-dropdown")) {
        isOpen.value = false;
    }
};

onMounted(() => {
    fetchStatus();
    document.addEventListener("click", closeDropdown);
});

onUnmounted(() => {
    document.removeEventListener("click", closeDropdown);
});
</script>

<template>
    <div class="relative mcp-status-dropdown">
        <!-- Status Button -->
        <button
            @click="isOpen = !isOpen"
            class="flex h-10 w-10 items-center justify-center rounded-xl transition-colors"
            :class="statusConfig.bgClass"
            :title="t('mcp_status.title')"
        >
            <!-- MCP Icon with status dot -->
            <div class="relative">
                <svg
                    class="h-5 w-5"
                    :class="statusConfig.textClass"
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
                <!-- Status dot -->
                <span
                    class="absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full ring-2 ring-white dark:ring-slate-900"
                    :class="statusConfig.dotClass"
                ></span>
            </div>
        </button>

        <!-- Dropdown -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="isOpen"
                class="absolute right-0 z-50 mt-2 w-72 origin-top-right rounded-xl bg-white shadow-lg ring-1 ring-black ring-opacity-5 dark:bg-slate-800 dark:ring-white/10"
            >
                <div class="p-4">
                    <!-- Header -->
                    <div class="flex items-center gap-3 mb-4">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-lg"
                            :class="statusConfig.bgClass"
                        >
                            <svg
                                class="h-5 w-5"
                                :class="statusConfig.textClass"
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
                        </div>
                        <div>
                            <h3
                                class="text-sm font-semibold text-slate-900 dark:text-white"
                            >
                                {{ t("mcp_status.title") }}
                            </h3>
                            <p
                                class="text-xs"
                                :class="statusConfig.textClass"
                            >
                                {{ statusConfig.label }}
                            </p>
                        </div>
                    </div>

                    <!-- Status Details -->
                    <div
                        v-if="status"
                        class="space-y-2 mb-4 text-sm text-slate-600 dark:text-slate-400"
                    >
                        <div
                            v-if="status.message"
                            class="flex items-start gap-2"
                        >
                            <svg
                                class="h-4 w-4 mt-0.5 shrink-0"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                />
                            </svg>
                            <span class="truncate">{{ status.message }}</span>
                        </div>
                        <div
                            v-if="status.version"
                            class="flex items-center gap-2"
                        >
                            <svg
                                class="h-4 w-4 shrink-0"
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
                            <span>NetSendo {{ status.version }}</span>
                        </div>
                        <div
                            v-if="status.tested_at_human"
                            class="flex items-center gap-2"
                        >
                            <svg
                                class="h-4 w-4 shrink-0"
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
                            <span
                                >{{ t("mcp_status.last_tested") }}:
                                {{ status.tested_at_human }}</span
                            >
                        </div>
                    </div>

                    <!-- Error message -->
                    <div
                        v-if="error"
                        class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 px-3 py-2 text-sm text-red-600 dark:text-red-400"
                    >
                        {{ error }}
                    </div>

                    <!-- Actions -->
                    <div class="space-y-2">
                        <button
                            @click="runTest"
                            :disabled="isTesting"
                            class="flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg
                                v-if="isTesting"
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
                            <svg
                                v-else
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
                            {{
                                isTesting
                                    ? t("mcp_status.testing")
                                    : t("mcp_status.test_now")
                            }}
                        </button>

                        <Link
                            :href="route('marketplace.mcp')"
                            class="flex w-full items-center justify-center gap-2 rounded-lg bg-slate-100 dark:bg-slate-700 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 transition-colors hover:bg-slate-200 dark:hover:bg-slate-600"
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
                                    d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                />
                            </svg>
                            {{ t("mcp_status.go_to_settings") }}
                        </Link>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>
