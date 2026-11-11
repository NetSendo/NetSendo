<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Notice from '@/Components/Ui/Notice.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import Panel from '@/Components/Ui/Panel.vue';
import SegmentedControl from '@/Components/Ui/SegmentedControl.vue';
import StatusPill from '@/Components/Ui/StatusPill.vue';
import { useI18n } from 'vue-i18n';
import { createSafeMarkdown } from '@/utils/safeMarkdown';

const { t, locale } = useI18n();
const page = usePage();
const isLoading = ref(true);
const isLoadingChangelog = ref(true);
const versionData = ref(null);
const changelogData = ref(null);
const error = ref(null);
const activeTab = ref('updates');

const currentVersion = computed(() => page.props.appVersion || '1.0.0');

const tabs = computed(() => [
    { value: 'updates', label: t('settings.updates.tabs.check_updates') },
    { value: 'changelog', label: t('settings.updates.tabs.changelog') },
    { value: 'howto', label: t('settings.updates.tabs.how_to_update') },
]);

// Commands to copy: a quiet block that scrolls sideways instead of wrapping
const codeBlockClass =
    'mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-slate-50 p-4 font-mono text-xs leading-relaxed text-slate-800 dark:border-slate-700/60 dark:bg-slate-900/60 dark:text-slate-200';

// The colours of release notes (rendered Markdown, styled below) for the light and the dark theme
const notesPalette =
    'text-slate-600 dark:text-slate-300 [--rn-strong:#0f172a] dark:[--rn-strong:#e2e8f0] [--rn-accent:#4f46e5] dark:[--rn-accent:#a5b4fc] [--rn-code-bg:#f1f5f9] dark:[--rn-code-bg:rgb(255_255_255/0.08)] [--rn-code:#0f172a] dark:[--rn-code:#cbd5e1] [--rn-pre-bg:#f8fafc] dark:[--rn-pre-bg:#0f172a] [--rn-line:#e2e8f0] dark:[--rn-line:#334155] [--rn-muted:#475569] dark:[--rn-muted:#94a3b8]';

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
        return new Date(dateString).toLocaleDateString(locale.value, {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    } catch {
        return dateString;
    }
};

// Release notes come from GitHub and end up in v-html (notes mention tags like
// <select> outside code spans, which must show as text)
const renderReleaseNotes = createSafeMarkdown();

// Release names are "v2.1.3" or "v2.1.3 – Title"; the version is already the card heading
const releaseTitle = (name, version) => {
    const escapedVersion = version.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const title = (name || '').replace(new RegExp(`^v?${escapedVersion}\\s*[–—-]?\\s*`), '');
    return title.trim();
};

// Long notes are collapsed; a note only gets the toggle when it actually overflows
const expandedNotes = reactive(new Set());
const overflowingNotes = reactive(new Set());

const measureNotes = (version, el) => {
    if (!el || expandedNotes.has(version)) return;
    const overflows = el.scrollHeight > el.clientHeight + 1;
    if (overflows !== overflowingNotes.has(version)) {
        overflows ? overflowingNotes.add(version) : overflowingNotes.delete(version);
    }
};

const toggleNotes = (version) => {
    expandedNotes.has(version) ? expandedNotes.delete(version) : expandedNotes.add(version);
};

const getTypeFromVersion = (version) => {
    const parts = version.split('.');
    if (parts[0] !== '0' && parts[1] === '0' && parts[2] === '0') return 'major';
    if (parts[2] === '0') return 'minor';
    return 'patch';
};

// How big a step the release is, as a tone and a word
const TYPE_BADGES = {
    major: { text: 'Major', tone: 'info' },
    minor: { text: 'Minor', tone: 'success' },
    patch: { text: 'Patch', tone: 'neutral' },
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
                title: releaseTitle(r.name, r.version),
                notesHtml: renderReleaseNotes(r.body),
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
                notesHtml: renderReleaseNotes(fallbackEntry.changes.map(change => `- ${change}`).join('\n')),
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
    <Head :title="t('settings.updates.title')" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader :title="t('settings.updates.title')" :subtitle="t('settings.updates.description')">
                <StatusPill v-if="versionData && !versionData.updates_available" tone="success">
                    {{ t('settings.updates.up_to_date') }}
                </StatusPill>
                <StatusPill v-else-if="versionData?.updates_available" tone="warning">
                    {{ t('settings.updates.new_version_available') }}
                </StatusPill>
            </PageHeader>
        </template>

        <div class="mx-auto flex max-w-4xl flex-col gap-4">
            <SegmentedControl v-model="activeTab" :options="tabs" :label="t('settings.updates.title')" class="self-start" />

            <!-- Which version runs here, and whether a newer one is out -->
            <template v-if="activeTab === 'updates'">
                <Panel>
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p class="text-sm text-slate-600 dark:text-slate-400">{{ t('settings.updates.current_version') }}</p>
                            <p class="text-3xl font-semibold tabular-nums tracking-tight text-slate-900 dark:text-white">
                                v{{ currentVersion }}
                            </p>
                        </div>
                        <SecondaryButton type="button" :disabled="isLoading" @click="refreshVersionInfo">
                            {{ isLoading ? t('settings.updates.checking') : t('settings.updates.check_for_updates') }}
                        </SecondaryButton>
                    </div>
                    <p v-if="error" role="alert" class="mt-3 text-sm text-rose-700 dark:text-rose-300">{{ error }}</p>
                </Panel>

                <Notice
                    v-if="versionData?.updates_available && versionData?.new_versions?.length > 0"
                    tone="warning"
                    :title="t('settings.updates.new_version_available')"
                >
                    <p>
                        {{ t('settings.updates.new_versions_count', { count: versionData.update_count, version: 'v' + versionData.latest_version }) }}
                    </p>
                    <PrimaryButton type="button" class="mt-3" @click="activeTab = 'howto'">
                        {{ t('settings.updates.update_instructions') }}
                    </PrimaryButton>
                </Notice>
            </template>

            <!-- What each release brought, newest first -->
            <template v-if="activeTab === 'changelog'">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">{{ t('settings.updates.version_history') }}</h2>
                    <SecondaryButton type="button" :disabled="isLoadingChangelog" @click="fetchChangelog">
                        {{ isLoadingChangelog ? t('settings.updates.loading_changelog') : t('settings.updates.refresh') }}
                    </SecondaryButton>
                </div>

                <Panel v-for="entry in changelog" :key="entry.version">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <h3 class="text-base font-semibold tabular-nums text-slate-900 dark:text-white">v{{ entry.version }}</h3>
                        <StatusPill :tone="(TYPE_BADGES[entry.type] || TYPE_BADGES.patch).tone">
                            {{ (TYPE_BADGES[entry.type] || TYPE_BADGES.patch).text }}
                        </StatusPill>
                        <span class="text-sm tabular-nums text-slate-600 dark:text-slate-400">{{ formatDate(entry.date) }}</span>
                        <a
                            v-if="entry.url"
                            :href="entry.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="ml-auto text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                        >
                            {{ t('settings.updates.view_on_github') }}
                        </a>
                    </div>
                    <p v-if="entry.title" class="mt-2 text-sm font-medium text-slate-900 dark:text-white">{{ entry.title }}</p>

                    <template v-if="entry.notesHtml">
                        <div
                            :ref="(el) => measureNotes(entry.version, el)"
                            class="release-notes mt-3 text-sm"
                            :class="[
                                notesPalette,
                                {
                                    'max-h-96 overflow-hidden': !expandedNotes.has(entry.version),
                                    'is-faded': overflowingNotes.has(entry.version) && !expandedNotes.has(entry.version),
                                },
                            ]"
                            v-html="entry.notesHtml"
                        ></div>
                        <button
                            v-if="overflowingNotes.has(entry.version)"
                            type="button"
                            :aria-expanded="expandedNotes.has(entry.version)"
                            class="mt-3 text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                            @click="toggleNotes(entry.version)"
                        >
                            {{ expandedNotes.has(entry.version) ? t('settings.updates.show_less') : t('settings.updates.show_more') }}
                        </button>
                    </template>
                </Panel>

                <a
                    href="https://github.com/NetSendo/NetSendo/releases"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="self-center text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300"
                >
                    {{ t('settings.updates.view_all_releases') }}
                </a>
            </template>

            <!-- How to move to a newer version -->
            <template v-if="activeTab === 'howto'">
                <Notice tone="warning" :title="t('settings.updates.warning.title')">
                    <ul class="list-disc space-y-0.5 pl-5">
                        <li>{{ t('settings.updates.warning.backup') }}</li>
                        <li>{{ t('settings.updates.warning.release_notes') }}</li>
                        <li>{{ t('settings.updates.warning.staging') }}</li>
                    </ul>
                </Notice>

                <Panel :title="t('settings.updates.docker_update.title')">
                    <p class="text-sm text-slate-600 dark:text-slate-400">{{ t('settings.updates.docker_update.description') }}</p>
                    <pre :class="codeBlockClass"># {{ t('settings.updates.docker_update.step_stop') }}
docker compose down

# {{ t('settings.updates.docker_update.step_pull') }}
docker compose pull

# {{ t('settings.updates.docker_update.step_start') }}
docker compose up -d</pre>
                    <p class="mt-4 text-sm text-slate-600 dark:text-slate-400">
                        {{ t('settings.updates.docker_update.specific_version') }}
                    </p>
                    <pre :class="codeBlockClass">NETSENDO_VERSION={{ versionData?.latest_version || currentVersion }} docker compose up -d</pre>
                </Panel>

                <Panel :title="t('settings.updates.manual_update.title')">
                    <p class="text-sm text-slate-600 dark:text-slate-400">{{ t('settings.updates.manual_update.description') }}</p>
                    <pre :class="codeBlockClass"># {{ t('settings.updates.manual_update.step_pull') }}
git pull origin main

# {{ t('settings.updates.manual_update.step_build') }}
docker compose up -d --build</pre>
                </Panel>
            </template>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Release notes are Markdown rendered into v-html: styled from here, coloured through the
   variables the container sets for the light and the dark theme */
.release-notes.is-faded {
    mask-image: linear-gradient(to bottom, black calc(100% - 4rem), transparent);
}

.release-notes :deep(> :first-child) {
    margin-top: 0;
}

.release-notes :deep(p),
.release-notes :deep(ul),
.release-notes :deep(ol),
.release-notes :deep(pre),
.release-notes :deep(blockquote) {
    margin: 0.5rem 0;
    line-height: 1.6;
}

.release-notes :deep(h1),
.release-notes :deep(h2),
.release-notes :deep(h3),
.release-notes :deep(h4),
.release-notes :deep(h5),
.release-notes :deep(h6) {
    margin: 1.25rem 0 0.5rem;
    font-weight: 600;
    color: var(--rn-strong);
}

.release-notes :deep(h1),
.release-notes :deep(h2) {
    font-size: 1rem;
}

.release-notes :deep(h3) {
    font-size: 0.9375rem;
}

.release-notes :deep(ul) {
    list-style: disc;
    padding-left: 1.25rem;
}

.release-notes :deep(ol) {
    list-style: decimal;
    padding-left: 1.25rem;
}

.release-notes :deep(li) {
    margin: 0.375rem 0;
}

.release-notes :deep(li::marker) {
    color: var(--rn-muted);
}

.release-notes :deep(li > ul),
.release-notes :deep(li > ol) {
    margin: 0.25rem 0;
}

.release-notes :deep(strong) {
    font-weight: 600;
    color: var(--rn-strong);
}

.release-notes :deep(em) {
    font-style: italic;
}

.release-notes :deep(a) {
    color: var(--rn-accent);
    text-decoration: underline;
    text-underline-offset: 2px;
}

.release-notes :deep(code) {
    border-radius: 0.25rem;
    background: var(--rn-code-bg);
    padding: 0.1em 0.35em;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.85em;
    color: var(--rn-code);
    overflow-wrap: anywhere;
}

.release-notes :deep(pre) {
    overflow-x: auto;
    border: 1px solid var(--rn-line);
    border-radius: 0.5rem;
    background: var(--rn-pre-bg);
    padding: 0.75rem 1rem;
}

.release-notes :deep(pre code) {
    background: none;
    padding: 0;
    overflow-wrap: normal;
}

.release-notes :deep(blockquote) {
    border-left: 3px solid var(--rn-line);
    padding-left: 0.75rem;
    color: var(--rn-muted);
}

.release-notes :deep(hr) {
    margin: 1rem 0;
    border-color: var(--rn-line);
}

.release-notes :deep(img) {
    max-width: 100%;
    border-radius: 0.5rem;
}

.release-notes :deep(table) {
    display: block;
    overflow-x: auto;
    border-collapse: collapse;
}

.release-notes :deep(th),
.release-notes :deep(td) {
    border: 1px solid var(--rn-line);
    padding: 0.25rem 0.5rem;
}
</style>
