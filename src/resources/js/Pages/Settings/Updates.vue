<script setup>
import { ref, onMounted, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage();
const isLoading = ref(true);
const isLoadingChangelog = ref(true);
const versionData = ref(null);
const changelogData = ref(null);
const error = ref(null);
const activeTab = ref('updates');

const currentVersion = computed(() => page.props.appVersion || '1.0.0');

// Fallback changelog for when GitHub is not available
const fallbackChangelog = [
    {
        version: '1.0.1',
        date: '2024-12-20',
        type: 'patch',
        title: 'Bug Fixes & Improvements',
        changes: [
            'Fixed hardcoded Polish translations in CRON warning',
            'Fixed AI Assistant translations conflict',
            'Improved Help menu - What\'s New section display',
            'Added CRON warning translation keys',
            'Fixed changelog link to /update',
        ],
    },
    {
        version: '1.0.0',
        date: '2024-12-20',
        type: 'major',
        title: 'Initial Public Release',
        changes: [
            'Authentication and login system',
            'Mailing list management',
            'Email message sending',
            'MJML template editor',
            'SMS system',
            'AI integrations (OpenAI, Anthropic, Google)',
            'Automation and funnels system',
            'Statistics and reports dashboard',
            'Licensing system (SILVER/GOLD)',
            'Multi-language support (EN, PL, DE, ES)',
        ],
    },
];

// Fetch version info from API
const fetchVersionInfo = async () => {
    isLoading.value = true;
    try {
        const response = await fetch('/api/version/check', {
            headers: { 'Accept': 'application/json' },
        });

        if (response.ok) {
            versionData.value = await response.json();
        }
    } catch (e) {
        error.value = 'Unable to fetch update information';
    } finally {
        isLoading.value = false;
    }
};

// Fetch changelog from GitHub
const fetchChangelog = async () => {
    isLoadingChangelog.value = true;
    try {
        const response = await fetch('/api/version/changelog', {
            headers: { 'Accept': 'application/json' },
        });

        if (response.ok) {
            changelogData.value = await response.json();
        }
    } catch (e) {
        // Use fallback changelog
        changelogData.value = null;
    } finally {
        isLoadingChangelog.value = false;
    }
};

const refreshVersionInfo = async () => {
    isLoading.value = true;
    try {
        const response = await fetch('/api/version/refresh', {
            headers: { 'Accept': 'application/json' },
        });

        if (response.ok) {
            versionData.value = await response.json();
        }
    } catch (e) {
        error.value = 'Unable to refresh update information';
    } finally {
        isLoading.value = false;
    }
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    try {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    } catch {
        return dateString;
    }
};

// Parse changelog body from GitHub (markdown to array)
const parseChangelog = (body) => {
    if (!body) return [];
    const lines = body.split('\n').filter(line => line.trim().startsWith('-') || line.trim().startsWith('*'));
    return lines.map(line => line.replace(/^[-*]\s*/, '').trim()).slice(0, 10);
};

const getTypeFromVersion = (version) => {
    const parts = version.split('.');
    if (parts[0] !== '0' && parts[1] === '0' && parts[2] === '0') return 'major';
    if (parts[2] === '0') return 'minor';
    return 'patch';
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

// Computed changelog - merge GitHub data with fallback (GitHub has priority per version)
const changelog = computed(() => {
    const githubReleases = changelogData.value?.releases || [];

    // Create a map of GitHub releases by version for quick lookup
    const githubVersionMap = new Map();
    githubReleases
        .filter(r => !r.prerelease)
        .forEach(r => {
            githubVersionMap.set(r.version, {
                version: r.version,
                date: r.published_at,
                type: getTypeFromVersion(r.version),
                title: r.name || `Version ${r.version}`,
                changes: parseChangelog(r.body),
                url: r.url,
                source: 'github',
            });
        });

    // Merge: GitHub entries take priority, fallback fills gaps
    const mergedChangelog = [];
    const addedVersions = new Set();

    // First, add all GitHub entries
    for (const [version, entry] of githubVersionMap) {
        mergedChangelog.push(entry);
        addedVersions.add(version);
    }

    // Then, add fallback entries for versions not in GitHub
    for (const fallbackEntry of fallbackChangelog) {
        if (!addedVersions.has(fallbackEntry.version)) {
            mergedChangelog.push({
                ...fallbackEntry,
                source: 'local',
            });
            addedVersions.add(fallbackEntry.version);
        }
    }

    // Sort by version descending (newest first)
    return mergedChangelog.sort((a, b) => {
        return -1 * (a.version.localeCompare(b.version, undefined, { numeric: true, sensitivity: 'base' }));
    });
});

onMounted(() => {
    fetchVersionInfo();
    fetchChangelog();
});
</script>

<template>
    <AuthenticatedLayout>
        <div class="py-8">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-white">{{ t('settings.updates.title') }}</h1>
                    <p class="mt-2 text-slate-400">{{ t('settings.updates.description') }}</p>
                </div>

                <!-- Tabs -->
                <div class="mb-6 flex gap-2">
                    <button
                        @click="activeTab = 'updates'"
                        :class="[
                            'rounded-lg px-4 py-2 text-sm font-medium transition-colors',
                            activeTab === 'updates'
                                ? 'bg-indigo-500 text-white'
                                : 'bg-slate-800 text-slate-300 hover:bg-slate-700'
                        ]"
                    >
                        {{ t('settings.updates.tabs.check_updates') }}
                    </button>
                    <button
                        @click="activeTab = 'changelog'"
                        :class="[
                            'rounded-lg px-4 py-2 text-sm font-medium transition-colors',
                            activeTab === 'changelog'
                                ? 'bg-indigo-500 text-white'
                                : 'bg-slate-800 text-slate-300 hover:bg-slate-700'
                        ]"
                    >
                        {{ t('settings.updates.tabs.changelog') }}
                    </button>
                    <button
                        @click="activeTab = 'howto'"
                        :class="[
                            'rounded-lg px-4 py-2 text-sm font-medium transition-colors',
                            activeTab === 'howto'
                                ? 'bg-indigo-500 text-white'
                                : 'bg-slate-800 text-slate-300 hover:bg-slate-700'
                        ]"
                    >
                        {{ t('settings.updates.tabs.how_to_update') }}
                    </button>
                </div>

                <!-- Updates Tab -->
                <div v-if="activeTab === 'updates'">
                    <!-- Current Version Card -->
                    <div class="mb-8 rounded-xl bg-slate-800/50 p-6 ring-1 ring-white/10">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-white">{{ t('settings.updates.current_version') }}</h2>
                                <div class="mt-2 flex items-center gap-3">
                                    <span class="text-3xl font-bold text-indigo-400">v{{ currentVersion }}</span>
                                    <span v-if="versionData && !versionData.updates_available" class="inline-flex items-center gap-1 rounded-full bg-green-500/20 px-2.5 py-0.5 text-xs font-medium text-green-400">
                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        {{ t('settings.updates.up_to_date') }}
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
                                {{ t('settings.updates.check_for_updates') }}
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
                                    <h3 class="font-semibold text-amber-400">{{ t('settings.updates.new_version_available') }}</h3>
                                    <p class="mt-1 text-sm text-slate-300">
                                        {{ t('settings.updates.new_versions_count', { count: versionData.update_count, version: 'v' + versionData.latest_version }) }}
                                    </p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <button
                                            @click="activeTab = 'howto'"
                                            class="inline-flex items-center gap-2 rounded-lg bg-amber-500 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-amber-600"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                            </svg>
                                            {{ t('settings.updates.update_instructions') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Loading State -->
                        <div v-else-if="isLoading" class="mt-6 flex items-center gap-3 text-slate-400">
                            <svg class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            {{ t('settings.updates.checking') }}
                        </div>
                    </div>
                </div>

                <!-- Changelog Tab -->
                <div v-if="activeTab === 'changelog'">
                    <div class="mb-6 flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-white">{{ t('settings.updates.version_history') }}</h2>
                        <div class="flex items-center gap-3">
                            <span v-if="isLoadingChangelog" class="flex items-center gap-2 text-sm text-slate-400">
                                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ t('settings.updates.loading_changelog') }}
                            </span>
                            <button
                                @click="fetchChangelog"
                                :disabled="isLoadingChangelog"
                                class="flex items-center gap-1.5 rounded-lg bg-white/5 px-3 py-1.5 text-xs font-medium text-slate-400 transition-colors hover:bg-white/10 hover:text-white disabled:opacity-50"
                            >
                                <svg :class="['h-3.5 w-3.5', isLoadingChangelog ? 'animate-spin' : '']" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                {{ t('settings.updates.refresh') }}
                            </button>
                        </div>
                    </div>

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
                                    <a
                                        v-if="entry.url"
                                        :href="entry.url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="ml-auto text-xs text-indigo-400 hover:text-indigo-300"
                                    >
                                        {{ t('settings.updates.view_on_github') }} →
                                    </a>
                                </div>

                                <h4 class="mt-2 text-base font-medium text-slate-300">{{ entry.title }}</h4>

                                <ul v-if="entry.changes?.length > 0" class="mt-4 space-y-2">
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
                            {{ t('settings.updates.view_all_releases') }}
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- How to Update Tab -->
                <div v-if="activeTab === 'howto'" class="space-y-6">
                    <div class="rounded-xl bg-slate-800/50 p-6 ring-1 ring-white/10">
                        <h2 class="mb-4 text-xl font-semibold text-white">{{ t('settings.updates.docker_update.title') }}</h2>
                        <p class="mb-4 text-slate-400">{{ t('settings.updates.docker_update.description') }}</p>

                        <div class="rounded-lg bg-slate-900 p-4 font-mono text-sm">
                            <div class="text-slate-500">{{ t('settings.updates.docker_update.step_stop') }}</div>
                            <div class="text-green-400">docker compose down</div>
                            <div class="mt-2 text-slate-500">{{ t('settings.updates.docker_update.step_pull') }}</div>
                            <div class="text-green-400">docker compose pull</div>
                            <div class="mt-2 text-slate-500">{{ t('settings.updates.docker_update.step_start') }}</div>
                            <div class="text-green-400">docker compose up -d</div>
                        </div>

                        <p class="mt-4 text-sm text-slate-500">
                            {{ t('settings.updates.docker_update.specific_version') }}
                        </p>
                        <div class="mt-2 rounded-lg bg-slate-900 p-4 font-mono text-sm text-green-400">
                            NETSENDO_VERSION=1.1.0 docker compose up -d
                        </div>
                    </div>

                    <div class="rounded-xl bg-slate-800/50 p-6 ring-1 ring-white/10">
                        <h2 class="mb-4 text-xl font-semibold text-white">{{ t('settings.updates.manual_update.title') }}</h2>
                        <p class="mb-4 text-slate-400">{{ t('settings.updates.manual_update.description') }}</p>

                        <div class="rounded-lg bg-slate-900 p-4 font-mono text-sm">
                            <div class="text-slate-500">{{ t('settings.updates.manual_update.step_pull') }}</div>
                            <div class="text-green-400">git pull origin main</div>
                            <div class="mt-2 text-slate-500">{{ t('settings.updates.manual_update.step_build') }}</div>
                            <div class="text-green-400">docker compose up -d --build</div>
                        </div>
                    </div>

                    <div class="rounded-xl bg-amber-500/10 p-6 ring-1 ring-amber-500/30">
                        <div class="flex items-start gap-3">
                            <svg class="h-6 w-6 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <div>
                                <h3 class="font-semibold text-amber-400">{{ t('settings.updates.warning.title') }}</h3>
                                <ul class="mt-2 space-y-1 text-sm text-slate-300">
                                    <li>{{ t('settings.updates.warning.backup') }}</li>
                                    <li>{{ t('settings.updates.warning.release_notes') }}</li>
                                    <li>{{ t('settings.updates.warning.staging') }}</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
