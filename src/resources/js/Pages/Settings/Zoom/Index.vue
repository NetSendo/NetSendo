<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, useForm, usePage } from "@inertiajs/vue3";
import { ref, computed } from "vue";

const props = defineProps({
    settings: Object,
    connection: Object,
    is_configured: Boolean,
});

const page = usePage();
const flash = computed(() => page.props.flash);

const form = useForm({
    client_id: props.settings?.client_id || "",
    client_secret: "",
});

const saving = ref(false);
const toastMessage = ref("");
const showToast = ref(false);

const displayToast = (message) => {
    toastMessage.value = message;
    showToast.value = true;
    setTimeout(() => {
        showToast.value = false;
    }, 2000);
};

const copyToClipboard = async (text) => {
    try {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(text);
        } else {
            // Fallback dla starszych przeglądarek
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";
            textArea.style.left = "-999999px";
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand("copy");
            document.body.removeChild(textArea);
        }
        displayToast("Copied to clipboard!");
    } catch (err) {
        console.error("Failed to copy: ", err);
        displayToast("Failed to copy");
    }
};

const saveSettings = () => {
    saving.value = true;
    form.post(route("settings.zoom.save"), {
        preserveScroll: true,
        onFinish: () => {
            saving.value = false;
        },
        onSuccess: () => {
            displayToast("Credentials saved!");
        },
    });
};

// Zoom scope labels and descriptions
const scopeLabels = {
    // Meeting scopes
    'meeting:write': 'Create Meetings',
    'meeting:read': 'View Meetings',
    'meeting:write:admin': 'Admin: Create Meetings',
    'meeting:read:admin': 'Admin: View Meetings',
    'meeting:master': 'Meeting Master',
    // User scopes
    'user:read': 'View Profile',
    'user:write': 'Edit Profile',
    'user:read:admin': 'Admin: View Users',
    'user:write:admin': 'Admin: Edit Users',
    'user:master': 'User Master',
    // Recording scopes
    'recording:read': 'View Recordings',
    'recording:write': 'Manage Recordings',
    'recording:read:admin': 'Admin: View Recordings',
    // Webinar scopes
    'webinar:read': 'View Webinars',
    'webinar:write': 'Create Webinars',
    // Phone scopes
    'phone:read': 'View Phone',
    'phone:write': 'Manage Phone',
    // Other common scopes
    'account:read': 'View Account',
    'dashboard:read': 'View Dashboard',
    'report:read': 'View Reports',
    'group:read': 'View Groups',
    'group:write': 'Manage Groups',
    'chat_message:read': 'Read Chat Messages',
    'chat_message:write': 'Send Chat Messages',
    'contact:read': 'View Contacts',
    'contact:write': 'Manage Contacts',
};

const scopeDescriptions = {
    'meeting:write': 'Allows creating and managing Zoom meetings',
    'meeting:read': 'Allows viewing meeting details and lists',
    'user:read': 'Allows viewing user profile information',
    'user:write': 'Allows editing user profile information',
    'recording:read': 'Allows viewing cloud recordings',
    'recording:write': 'Allows managing cloud recordings',
    'webinar:read': 'Allows viewing webinar details',
    'webinar:write': 'Allows creating and managing webinars',
};

const getScopeLabel = (scope) => {
    return scopeLabels[scope] || scope.replace(/[:_]/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const getScopeDescription = (scope) => {
    return scopeDescriptions[scope] || `Permission: ${scope}`;
};

const disconnect = () => {
    if (confirm("Are you sure you want to disconnect your Zoom account?")) {
        form.post(route("settings.zoom.disconnect"), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="Zoom Settings" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <!-- Breadcrumb -->
                <div class="mb-8 flex items-center gap-2 text-sm text-gray-500 dark:text-slate-400">
                    <Link :href="route('marketplace.index')" class="hover:text-gray-900 dark:hover:text-white transition-colors">
                        Marketplace
                    </Link>
                    <span>/</span>
                    <Link :href="route('marketplace.zoom')" class="hover:text-gray-900 dark:hover:text-white transition-colors">
                        Zoom
                    </Link>
                    <span>/</span>
                    <span class="text-gray-900 dark:text-white">Settings</span>
                </div>

                <!-- Header -->
                <div class="mb-8">
                    <div class="flex items-center gap-4">
                        <div class="h-14 w-14 shrink-0 overflow-hidden rounded-xl bg-white p-2 shadow ring-1 ring-gray-100 dark:ring-transparent flex items-center justify-center">
                            <svg viewBox="0 0 24 24" class="h-10 w-10">
                                <circle cx="12" cy="12" r="10" fill="#2D8CFF"/>
                                <path fill="#FFF" d="M8 9.5v5c0 .28.22.5.5.5h5c.28 0 .5-.22.5-.5v-5c0-.28-.22-.5-.5-.5h-5c-.28 0-.5.22-.5.5z"/>
                                <path fill="#FFF" d="M15 10.5l2.5-1.5v6l-2.5-1.5v-3z"/>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                                Zoom Settings
                            </h1>
                            <p class="text-gray-600 dark:text-slate-400">
                                Configure Zoom video meeting integration
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Flash Messages -->
                <div v-if="flash?.success" class="mb-6 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 p-4 text-emerald-700 dark:text-emerald-400 ring-1 ring-emerald-200 dark:ring-emerald-500/20">
                    {{ flash.success }}
                </div>
                <div v-if="flash?.error" class="mb-6 rounded-xl bg-red-50 dark:bg-red-500/10 p-4 text-red-700 dark:text-red-400 ring-1 ring-red-200 dark:ring-red-500/20">
                    {{ flash.error }}
                </div>

                <div class="space-y-6">
                    <!-- Connection Status -->
                    <div v-if="connection" class="rounded-2xl bg-white dark:bg-slate-800 p-6 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-4">
                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-500/10">
                                    <svg class="h-6 w-6 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="font-semibold text-gray-900 dark:text-white">
                                        Connected Account
                                    </h3>
                                    <p class="text-sm text-gray-600 dark:text-slate-400">
                                        {{ connection.zoom_email }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-slate-500 mt-1">
                                        Connected {{ connection.connected_at }}
                                    </p>
                                </div>
                            </div>
                            <button
                                @click="disconnect"
                                class="rounded-lg px-4 py-2 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors"
                            >
                                Disconnect
                            </button>
                        </div>

                        <!-- Granted Scopes -->
                        <div v-if="connection.granted_scopes && connection.granted_scopes.length" class="mt-4 pt-4 border-t border-gray-100 dark:border-slate-700">
                            <h4 class="text-sm font-medium text-gray-700 dark:text-slate-300 mb-3">
                                Granted Permissions
                            </h4>
                            <div class="flex flex-wrap gap-2">
                                <div
                                    v-for="scope in connection.granted_scopes"
                                    :key="scope"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-blue-50 dark:bg-blue-500/10 px-3 py-1.5 text-xs font-medium text-blue-700 dark:text-blue-300 ring-1 ring-blue-100 dark:ring-blue-500/20"
                                    :title="getScopeDescription(scope)"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                    </svg>
                                    {{ getScopeLabel(scope) }}
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-slate-500 mt-3">
                                These permissions were granted during authorization. To modify permissions, disconnect and reconnect your account.
                            </p>
                        </div>
                    </div>

                    <!-- API Credentials -->
                    <div class="rounded-2xl bg-white dark:bg-slate-800 p-6 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            API Credentials
                        </h2>
                        <p class="text-sm text-gray-600 dark:text-slate-400 mb-6">
                            Enter your Zoom OAuth app credentials from
                            <a href="https://marketplace.zoom.us/" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline">Zoom Marketplace</a>.
                        </p>

                        <form @submit.prevent="saveSettings" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">
                                    Client ID
                                </label>
                                <input
                                    v-model="form.client_id"
                                    type="text"
                                    class="w-full rounded-lg border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-4 py-2.5 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="Your Zoom Client ID"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">
                                    Client Secret
                                </label>
                                <input
                                    v-model="form.client_secret"
                                    type="password"
                                    class="w-full rounded-lg border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-4 py-2.5 text-gray-900 dark:text-white focus:border-blue-500 focus:ring-blue-500"
                                    :placeholder="settings?.has_client_secret ? '••••••••••••' : 'Your Zoom Client Secret'"
                                />
                                <p v-if="settings?.has_client_secret" class="text-xs text-gray-500 dark:text-slate-500 mt-1">
                                    Leave empty to keep current secret
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">
                                    Redirect URI
                                </label>
                                <div class="flex items-center gap-2">
                                    <input
                                        type="text"
                                        :value="settings?.redirect_uri"
                                        readonly
                                        class="flex-1 rounded-lg border-gray-300 dark:border-slate-600 bg-gray-50 dark:bg-slate-900/50 px-4 py-2.5 text-gray-600 dark:text-slate-400"
                                    />
                                    <button
                                        type="button"
                                        @click="copyToClipboard(settings?.redirect_uri)"
                                        class="rounded-lg px-3 py-2.5 bg-gray-100 dark:bg-white/5 text-gray-600 dark:text-slate-400 hover:bg-gray-200 dark:hover:bg-white/10 transition-colors"
                                    >
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                        </svg>
                                    </button>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-slate-500 mt-1">
                                    Add this URL to your Zoom OAuth app as Redirect URL
                                </p>
                            </div>

                            <div class="pt-4">
                                <button
                                    type="submit"
                                    :disabled="saving"
                                    class="rounded-lg bg-blue-600 px-6 py-2.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 transition-colors"
                                >
                                    {{ saving ? 'Saving...' : 'Save Credentials' }}
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Connect Account -->
                    <div v-if="is_configured && !connection" class="rounded-2xl bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 p-6 border border-blue-100 dark:border-blue-500/20">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                            Connect Your Zoom Account
                        </h2>
                        <p class="text-sm text-gray-600 dark:text-slate-400 mb-4">
                            Authorize NetSendo to create meetings on your behalf.
                        </p>
                        <a
                            :href="route('settings.zoom.connect')"
                            class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-6 py-2.5 text-sm font-medium text-white hover:bg-blue-700 transition-colors"
                        >
                            <svg viewBox="0 0 24 24" class="h-5 w-5">
                                <circle cx="12" cy="12" r="10" fill="currentColor" fill-opacity="0.2"/>
                                <path fill="currentColor" d="M8 9.5v5c0 .28.22.5.5.5h5c.28 0 .5-.22.5-.5v-5c0-.28-.22-.5-.5-.5h-5c-.28 0-.5.22-.5.5z"/>
                                <path fill="currentColor" d="M15 10.5l2.5-1.5v6l-2.5-1.5v-3z"/>
                            </svg>
                            Connect Zoom Account
                        </a>
                    </div>

                    <!-- Not Configured Warning -->
                    <div v-if="!is_configured" class="rounded-2xl bg-amber-50 dark:bg-amber-500/10 p-6 border border-amber-200 dark:border-amber-500/20">
                        <div class="flex items-start gap-4">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-500/20">
                                <svg class="h-5 w-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold text-amber-800 dark:text-amber-200">
                                    Configuration Required
                                </h3>
                                <p class="text-sm text-amber-700 dark:text-amber-300 mt-1">
                                    Please enter your Zoom OAuth app credentials above before connecting your account.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>

    <!-- Toast Notification -->
    <Transition
        enter-active-class="transition ease-out duration-300"
        enter-from-class="opacity-0 translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-2"
    >
        <div
            v-if="showToast"
            class="fixed bottom-6 right-6 z-50 rounded-lg bg-gray-900 dark:bg-white px-4 py-3 text-sm font-medium text-white dark:text-gray-900 shadow-lg"
        >
            {{ toastMessage }}
        </div>
    </Transition>
</template>
