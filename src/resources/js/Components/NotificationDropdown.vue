<script setup>
import { ref, onMounted, onUnmounted, computed } from "vue";
import { useI18n } from "vue-i18n";
import { Link } from "@inertiajs/vue3";

const { t } = useI18n();

const open = ref(false);
const loading = ref(false);
const notifications = ref([]);
const unreadCount = ref(0);

// Fetch notifications
const fetchNotifications = async () => {
    loading.value = true;
    try {
        const response = await fetch(route("api.notifications.recent"));
        if (response.ok) {
            const data = await response.json();
            notifications.value = data.notifications;
            unreadCount.value = data.unread_count;
        }
    } catch (e) {
        console.error("Failed to fetch notifications:", e);
    } finally {
        loading.value = false;
    }
};

// Fetch just the unread count
const fetchUnreadCount = async () => {
    try {
        const response = await fetch(route("api.notifications.unread-count"));
        if (response.ok) {
            const data = await response.json();
            unreadCount.value = data.count;
        }
    } catch (e) {
        console.error("Failed to fetch unread count:", e);
    }
};

// Mark notification as read
const markAsRead = async (notification) => {
    if (notification.read_at) return;

    try {
        const response = await fetch(
            route("api.notifications.read", notification.id),
            {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": document.querySelector(
                        'meta[name="csrf-token"]'
                    )?.content,
                    "Content-Type": "application/json",
                },
            }
        );
        if (response.ok) {
            const data = await response.json();
            notification.read_at = new Date().toISOString();
            unreadCount.value = data.unread_count;
        }
    } catch (e) {
        console.error("Failed to mark as read:", e);
    }
};

// Mark all as read
const markAllAsRead = async () => {
    try {
        const response = await fetch(route("api.notifications.read-all"), {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document.querySelector(
                    'meta[name="csrf-token"]'
                )?.content,
                "Content-Type": "application/json",
            },
        });
        if (response.ok) {
            notifications.value.forEach(
                (n) => (n.read_at = new Date().toISOString())
            );
            unreadCount.value = 0;
        }
    } catch (e) {
        console.error("Failed to mark all as read:", e);
    }
};

// Handle notification click
const handleNotificationClick = (notification) => {
    markAsRead(notification);
    if (notification.action_url) {
        window.location.href = notification.action_url;
    }
    open.value = false;
};

// Toggle dropdown
const toggleDropdown = () => {
    open.value = !open.value;
    if (open.value && notifications.value.length === 0) {
        fetchNotifications();
    }
};

// Close on escape
const closeOnEscape = (e) => {
    if (open.value && e.key === "Escape") {
        open.value = false;
    }
};

// Format relative time
const formatRelativeTime = (dateString) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) {
        return t("notifications.just_now");
    } else if (diffMins < 60) {
        return t("notifications.minutes_ago", { count: diffMins }, diffMins);
    } else if (diffHours < 24) {
        return t("notifications.hours_ago", { count: diffHours }, diffHours);
    } else {
        return t("notifications.days_ago", { count: diffDays }, diffDays);
    }
};

// Get icon for notification type
const getIcon = (type) => {
    switch (type) {
        case "success":
            return `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />`;
        case "warning":
            return `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />`;
        case "error":
            return `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />`;
        default:
            return `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />`;
    }
};

// Get color classes for notification type
const getColorClasses = (type) => {
    switch (type) {
        case "success":
            return {
                icon: "text-emerald-500",
                bg: "bg-emerald-50 dark:bg-emerald-900/20",
            };
        case "warning":
            return {
                icon: "text-amber-500",
                bg: "bg-amber-50 dark:bg-amber-900/20",
            };
        case "error":
            return {
                icon: "text-rose-500",
                bg: "bg-rose-50 dark:bg-rose-900/20",
            };
        default:
            return {
                icon: "text-blue-500",
                bg: "bg-blue-50 dark:bg-blue-900/20",
            };
    }
};

// Polling interval
let pollInterval = null;

onMounted(() => {
    document.addEventListener("keydown", closeOnEscape);
    fetchUnreadCount();

    // Poll for new notifications every 60 seconds
    pollInterval = setInterval(fetchUnreadCount, 60000);
});

onUnmounted(() => {
    document.removeEventListener("keydown", closeOnEscape);
    if (pollInterval) {
        clearInterval(pollInterval);
    }
});

// Check if there are unread notifications
const hasUnread = computed(() => unreadCount.value > 0);
</script>

<template>
    <div class="relative">
        <!-- Trigger Button -->
        <button
            @click="toggleDropdown"
            class="relative flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 text-slate-600 transition-colors hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700"
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
                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                />
            </svg>

            <!-- Notification Badge -->
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="transform scale-0 opacity-0"
                enter-to-class="transform scale-100 opacity-100"
                leave-active-class="transition duration-75 ease-in"
                leave-from-class="transform scale-100 opacity-100"
                leave-to-class="transform scale-0 opacity-0"
            >
                <span
                    v-if="hasUnread"
                    class="absolute -right-0.5 -top-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-rose-500 text-[10px] font-bold text-white ring-2 ring-white dark:ring-slate-800"
                >
                    {{ unreadCount > 99 ? "99+" : unreadCount }}
                </span>
            </Transition>
        </button>

        <!-- Overlay -->
        <div
            v-show="open"
            class="fixed inset-0 z-40"
            @click="open = false"
        ></div>

        <!-- Dropdown Panel -->
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0 scale-95 translate-y-1"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-95 translate-y-1"
        >
            <div
                v-show="open"
                class="absolute right-0 z-50 mt-2 w-80 sm:w-96 origin-top-right rounded-2xl bg-white shadow-xl ring-1 ring-black/5 dark:bg-slate-800 dark:ring-white/10"
            >
                <!-- Header -->
                <div
                    class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-slate-700"
                >
                    <h3 class="font-semibold text-slate-900 dark:text-white">
                        {{ t("notifications.title") }}
                    </h3>
                    <button
                        v-if="hasUnread"
                        @click="markAllAsRead"
                        class="text-xs font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300"
                    >
                        {{ t("notifications.mark_all_read") }}
                    </button>
                </div>

                <!-- Notifications List -->
                <div class="max-h-96 overflow-y-auto">
                    <!-- Loading State -->
                    <div
                        v-if="loading"
                        class="flex items-center justify-center py-8"
                    >
                        <svg
                            class="h-6 w-6 animate-spin text-indigo-600"
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
                    </div>

                    <!-- Empty State -->
                    <div
                        v-else-if="notifications.length === 0"
                        class="flex flex-col items-center justify-center py-12 text-center"
                    >
                        <div
                            class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-700"
                        >
                            <svg
                                class="h-8 w-8 text-slate-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.5"
                                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                                />
                            </svg>
                        </div>
                        <p
                            class="mt-4 text-sm font-medium text-slate-600 dark:text-slate-400"
                        >
                            {{ t("notifications.no_notifications") }}
                        </p>
                    </div>

                    <!-- Notification Items -->
                    <div
                        v-else
                        class="divide-y divide-slate-100 dark:divide-slate-700"
                    >
                        <button
                            v-for="notification in notifications"
                            :key="notification.id"
                            @click="handleNotificationClick(notification)"
                            class="flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/50"
                            :class="{
                                'bg-indigo-50/50 dark:bg-indigo-900/10':
                                    !notification.read_at,
                            }"
                        >
                            <!-- Icon -->
                            <div
                                class="mt-0.5 flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg"
                                :class="getColorClasses(notification.type).bg"
                            >
                                <svg
                                    class="h-5 w-5"
                                    :class="
                                        getColorClasses(notification.type).icon
                                    "
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                    v-html="getIcon(notification.type)"
                                ></svg>
                            </div>

                            <!-- Content -->
                            <div class="min-w-0 flex-1">
                                <div
                                    class="flex items-start justify-between gap-2"
                                >
                                    <p
                                        class="text-sm font-medium text-slate-900 dark:text-white"
                                        :class="{
                                            'font-semibold':
                                                !notification.read_at,
                                        }"
                                    >
                                        {{ notification.title }}
                                    </p>
                                    <!-- Unread indicator -->
                                    <span
                                        v-if="!notification.read_at"
                                        class="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full bg-indigo-600"
                                    ></span>
                                </div>
                                <p
                                    v-if="notification.message"
                                    class="mt-0.5 text-sm text-slate-600 dark:text-slate-400 line-clamp-2"
                                >
                                    {{ notification.message }}
                                </p>
                                <p
                                    class="mt-1 text-xs text-slate-500 dark:text-slate-500"
                                >
                                    {{
                                        formatRelativeTime(
                                            notification.created_at
                                        )
                                    }}
                                </p>
                            </div>
                        </button>
                    </div>
                </div>

                <div
                    v-if="notifications.length > 0"
                    class="border-t border-slate-100 px-4 py-3 dark:border-slate-700"
                >
                    <button
                        @click="
                            markAllAsRead();
                            open = false;
                        "
                        class="w-full rounded-lg bg-slate-100 px-4 py-2 text-center text-sm font-medium text-slate-700 transition-colors hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600"
                    >
                        {{ t("notifications.mark_all_read") }}
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>
