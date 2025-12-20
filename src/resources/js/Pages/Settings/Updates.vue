<script setup>
import { ref, onMounted, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const page = usePage();
const isLoading = ref(true);
const versionData = ref(null);
const error = ref(null);

const currentVersion = computed(() => page.props.appVersion || '1.0.0');

// Changelog entries for history
const changelog = [
    {
        version: '1.0.0',
        date: '2024-12-20',
        type: 'major',
        title: 'Pierwsza publiczna wersja',
        changes: [
            'System autoryzacji i logowania',
            'Zarządzanie listami mailingowymi',
            'Wysyłka wiadomości email',
            'Edytor szablonów MJML',
            'System SMS',
            'Integracje AI (OpenAI, Anthropic, Google)',
            'System automatyzacji i lejków',
            'Panel statystyk i raportów',
            'System licencjonowania (SILVER/GOLD)',
            'Wielojęzyczność (PL, EN, DE, ES)',
        ],
    },
];

// Fetch version info from API
const fetchVersionInfo = async () => {
    isLoading.value = true;
    try {
        const response = await fetch('/api/version/check', {
            headers: {
                'Accept': 'application/json',
            },
        });
        
        if (response.ok) {
            versionData.value = await response.json();
        }
    } catch (e) {
        error.value = 'Nie można pobrać informacji o aktualizacjach';
    } finally {
        isLoading.value = false;
    }
};

const refreshVersionInfo = async () => {
    isLoading.value = true;
    try {
        const response = await fetch('/api/version/refresh', {
            headers: {
                'Accept': 'application/json',
            },
        });
        
        if (response.ok) {
            versionData.value = await response.json();
        }
    } catch (e) {
        error.value = 'Nie można odświeżyć informacji o aktualizacjach';
    } finally {
        isLoading.value = false;
    }
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    try {
        return new Date(dateString).toLocaleDateString('pl-PL', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    } catch {
        return dateString;
    }
};

const getTypeColor = (type) => {
    switch (type) {
        case 'major': return 'bg-indigo-500';
        case 'minor': return 'bg-green-500';
        case 'patch': return 'bg-blue-500';
        default: return 'bg-slate-500';
    }
};

const getTypeBadge = (type) => {
    switch (type) {
        case 'major': return { text: 'Major', class: 'bg-indigo-500/20 text-indigo-400' };
        case 'minor': return { text: 'Minor', class: 'bg-green-500/20 text-green-400' };
        case 'patch': return { text: 'Patch', class: 'bg-blue-500/20 text-blue-400' };
        default: return { text: 'Update', class: 'bg-slate-500/20 text-slate-400' };
    }
};

onMounted(() => {
    fetchVersionInfo();
});
</script>

<template>
    <AuthenticatedLayout>
        <div class="py-8">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-white">{{ $t('navigation.updates') }}</h1>
                    <p class="mt-2 text-slate-400">Historia wersji i dostępne aktualizacje NetSendo</p>
                </div>

                <!-- Current Version Card -->
                <div class="mb-8 rounded-xl bg-slate-800/50 p-6 ring-1 ring-white/10">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-white">Aktualna wersja</h2>
                            <div class="mt-2 flex items-center gap-3">
                                <span class="text-3xl font-bold text-indigo-400">v{{ currentVersion }}</span>
                                <span v-if="versionData && !versionData.updates_available" class="inline-flex items-center gap-1 rounded-full bg-green-500/20 px-2.5 py-0.5 text-xs font-medium text-green-400">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Najnowsza
                                </span>
                            </div>
                        </div>
                        <button 
                            @click="refreshVersionInfo"
                            :disabled="isLoading"
                            class="flex items-center gap-2 rounded-lg bg-white/5 px-4 py-2 text-sm font-medium text-slate-300 transition-colors hover:bg-white/10 disabled:opacity-50"
                        >
                            <svg :class="['h-4 w-4', isLoading ? 'animate-spin' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            Sprawdź aktualizacje
                        </button>
                    </div>

                    <!-- Update Available -->
                    <div v-if="versionData?.updates_available && versionData?.new_versions?.length > 0" class="mt-6 rounded-lg bg-amber-500/10 p-4 ring-1 ring-amber-500/30">
                        <div class="flex items-start gap-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-500/20">
                                <svg class="h-4 w-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-semibold text-amber-400">Dostępna nowa wersja!</h3>
                                <p class="mt-1 text-sm text-slate-300">
                                    Dostępne są {{ versionData.update_count }} nowe wersje. Najnowsza to <strong>v{{ versionData.latest_version }}</strong>.
                                </p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <a 
                                        v-if="versionData.new_versions[0]?.url"
                                        :href="versionData.new_versions[0].url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-2 rounded-lg bg-amber-500 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-amber-600"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                        </svg>
                                        Pobierz aktualizację
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- New versions list -->
                        <div class="mt-4 space-y-2">
                            <div 
                                v-for="version in versionData.new_versions.slice(0, 3)" 
                                :key="version.version"
                                class="flex items-center justify-between rounded-lg bg-white/5 px-3 py-2"
                            >
                                <div class="flex items-center gap-3">
                                    <span class="font-medium text-white">v{{ version.version }}</span>
                                    <span v-if="version.name && version.name !== version.tag" class="text-sm text-slate-400">{{ version.name }}</span>
                                </div>
                                <span class="text-xs text-slate-500">{{ formatDate(version.published_at) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Loading State -->
                    <div v-else-if="isLoading" class="mt-6 flex items-center gap-3 text-slate-400">
                        <svg class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Sprawdzanie dostępnych aktualizacji...
                    </div>
                </div>

                <!-- Changelog -->
                <div>
                    <h2 class="mb-6 text-xl font-semibold text-white">Historia wersji</h2>
                    
                    <div class="relative space-y-6">
                        <!-- Timeline line -->
                        <div class="absolute left-4 top-0 h-full w-0.5 bg-slate-700"></div>

                        <!-- Version entries -->
                        <div 
                            v-for="entry in changelog" 
                            :key="entry.version"
                            class="relative pl-12"
                        >
                            <!-- Timeline dot -->
                            <div 
                                class="absolute left-2 top-0 h-5 w-5 rounded-full ring-4 ring-slate-900"
                                :class="getTypeColor(entry.type)"
                            ></div>

                            <!-- Entry card -->
                            <div class="rounded-xl bg-slate-800/50 p-5 ring-1 ring-white/10">
                                <div class="flex flex-wrap items-center gap-3">
                                    <h3 class="text-lg font-bold text-white">v{{ entry.version }}</h3>
                                    <span 
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="getTypeBadge(entry.type).class"
                                    >
                                        {{ getTypeBadge(entry.type).text }}
                                    </span>
                                    <span class="text-sm text-slate-500">{{ formatDate(entry.date) }}</span>
                                </div>

                                <h4 class="mt-2 text-base font-medium text-slate-300">{{ entry.title }}</h4>

                                <ul class="mt-4 space-y-2">
                                    <li 
                                        v-for="(change, index) in entry.changes" 
                                        :key="index"
                                        class="flex items-start gap-2 text-sm text-slate-400"
                                    >
                                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        {{ change }}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- GitHub Link -->
                <div class="mt-8 flex items-center justify-center">
                    <a 
                        href="https://github.com/NetSendo/NetSendo/releases"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex items-center gap-2 text-sm text-slate-400 transition-colors hover:text-white"
                    >
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z" clip-rule="evenodd" />
                        </svg>
                        Zobacz wszystkie wersje na GitHub
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
