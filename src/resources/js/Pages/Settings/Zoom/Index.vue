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

const saveSettings = () => {
    saving.value = true;
    form.post(route("settings.zoom.save"), {
        preserveScroll: true,
        onFinish: () => {
            saving.value = false;
        },
    });
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
                        <div class="flex items-center justify-between">
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
                    </div>

                    <!-- API Credentials -->
                    <div class="rounded-2xl bg-white dark:bg-slate-800 p-6 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            API Credentials
                        </h2>
                        <p class="text-sm text-gray-600 dark:text-slate-400 mb-6">
                            Enter your Zoom OAuth app credentials from
                            <a href="https://marketplace.zoom.us/develop" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline">Zoom Marketplace</a>.
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
                                        @click="navigator.clipboard.writeText(settings?.redirect_uri)"
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
</template>
