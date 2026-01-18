<script setup>
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import { useI18n } from "vue-i18n";
import { ref, computed } from "vue";

const { t } = useI18n();
const page = usePage();

const copied = ref(null);
const activeInstallMode = ref('remote'); // 'local' or 'remote'
const showAgentPrompt = ref(false);
const appUrl = computed(() => window.location.origin);

// Configuration for local Docker installation
const localConfig = computed(() => JSON.stringify({
    mcpServers: {
        netsendo: {
            command: 'docker',
            args: ['compose', '-f', '/path/to/NetSendo/docker-compose.yml', 'run', '--rm', '-i', 'mcp']
        }
    }
}, null, 2));

// Configuration for remote installation (npx)
const remoteConfig = computed(() => JSON.stringify({
    mcpServers: {
        netsendo: {
            command: 'npx',
            args: ['-y', '@netsendo/mcp-client', '--url', appUrl.value, '--api-key', 'YOUR_API_KEY_HERE']
        }
    }
}, null, 2));

const currentConfig = computed(() => activeInstallMode.value === 'local' ? localConfig.value : remoteConfig.value);

// Agent prompt content - cannot use $t() because vue-i18n escapes {{ }} incorrectly in code blocks
const agentPromptContent = computed(() => {
    const locale = page.props?.locale ?? 'en';
    if (locale === 'pl') {
        return `Jesteś asystentem AI z dostępem do NetSendo — platformy email/SMS marketingu.

DOSTĘPNE NARZĘDZIA:
- list_subscribers, get_subscriber, create_subscriber, update_subscriber, delete_subscriber
- list_contact_lists, list_tags, sync_subscriber_tags
- list_campaigns, get_campaign, create_campaign, update_campaign, send_campaign
- set_campaign_lists, set_campaign_exclusions, schedule_campaign, get_campaign_stats
- list_ab_tests, create_ab_test, add_ab_variant, start_ab_test, end_ab_test
- list_funnels, get_funnel, create_funnel, add_funnel_step, activate_funnel
- send_email, send_sms (pojedyncze wiadomości)
- list_placeholders, list_mailboxes, test_connection, get_account_info

⚠️ WAŻNE - create_campaign WYMAGANE PARAMETRY:
1. channel: MUSI być "email" LUB "sms" (określa typ kampanii)
2. subject: Tytuł kampanii
3. type: "broadcast" (jednorazowa) lub "autoresponder" (automatyczna)

Opcjonalne: content, mailbox_id, contact_list_ids, scheduled_at

PLACEHOLDERY W TREŚCI:
- [[first_name]], [[last_name]], [[email]], [[phone]] - dane subskrybenta
- [[!fname]] - imię w wołaczu (polski)
- [[unsubscribe_link]] - WYMAGANY w każdym emailu
- [[manage]] - link zarządzania preferencjami
- {{męska|żeńska}} - odmiana przez płeć

WORKFLOW KAMPANII EMAIL:
1. create_campaign z channel:"email", type:"broadcast", subject:"..."
2. set_campaign_lists z contact_list_ids
3. send_campaign

WORKFLOW KAMPANII SMS:
1. create_campaign z channel:"sms", type:"broadcast", subject:"..."
2. set_campaign_lists z contact_list_ids
3. send_campaign

ZASADY:
- Zawsze używaj list_placeholders na początku, aby poznać dostępne placeholdery
- Każdy email MUSI zawierać [[unsubscribe_link]]
- Przed wysłaniem kampanii MUSISZ przypisać listy (set_campaign_lists)
- Dla create_campaign ZAWSZE podaj channel: "email" lub "sms"`;
    }
    // Default: English (also for ES, DE until translated)
    return `You are an AI assistant with access to NetSendo — an email/SMS marketing platform.

AVAILABLE TOOLS:
- list_subscribers, get_subscriber, create_subscriber, update_subscriber, delete_subscriber
- list_contact_lists, list_tags, sync_subscriber_tags
- list_campaigns, get_campaign, create_campaign, update_campaign, send_campaign
- set_campaign_lists, set_campaign_exclusions, schedule_campaign, get_campaign_stats
- list_ab_tests, create_ab_test, add_ab_variant, start_ab_test, end_ab_test
- list_funnels, get_funnel, create_funnel, add_funnel_step, activate_funnel
- send_email, send_sms
- list_placeholders, test_connection, get_account_info

PLACEHOLDERS IN CONTENT:
- [[first_name]], [[last_name]], [[email]], [[phone]] - subscriber data
- [[!fname]] - first name in vocative case (Polish)
- [[unsubscribe_link]] - REQUIRED in every email
- [[manage]] - manage preferences link
- {{male|female}} - gender variation

CAMPAIGN WORKFLOW:
1. create_campaign → 2. set_campaign_lists → 3. send_campaign

IMPORTANT:
- Always use list_placeholders first to learn available placeholders
- Every email MUST contain [[unsubscribe_link]]
- Before sending a campaign you MUST assign lists (set_campaign_lists)`;
});

// Gender example placeholder - needs actual {{ }}
const placeholderGenderExample = '{{Drogi|Droga}}';

const copyToClipboard = async (text, key) => {
    try {
        await navigator.clipboard.writeText(text);
        copied.value = key;
        setTimeout(() => {
            copied.value = null;
        }, 2000);
    } catch (err) {
        console.error('Failed to copy:', err);
    }
};

const features = [
    {
        title: "mcp.features.subscribers.title",
        description: "mcp.features.subscribers.description",
        icon: "👥",
    },
    {
        title: "mcp.features.lists.title",
        description: "mcp.features.lists.description",
        icon: "📋",
    },
    {
        title: "mcp.features.campaigns.title",
        description: "mcp.features.campaigns.description",
        icon: "📧",
    },
    {
        title: "mcp.features.ab_tests.title",
        description: "mcp.features.ab_tests.description",
        icon: "🧪",
    },
    {
        title: "mcp.features.funnels.title",
        description: "mcp.features.funnels.description",
        icon: "⚡",
    },
    {
        title: "mcp.features.messaging.title",
        description: "mcp.features.messaging.description",
        icon: "✉️",
    },
    {
        title: "mcp.features.sms.title",
        description: "mcp.features.sms.description",
        icon: "📱",
    },
    {
        title: "mcp.features.tags.title",
        description: "mcp.features.tags.description",
        icon: "🏷️",
    },
    {
        title: "mcp.features.stats.title",
        description: "mcp.features.stats.description",
        icon: "📊",
    },
];

// Tool categories for organized display
const toolCategories = [
    {
        name: "mcp.tool_categories.subscribers",
        tools: [
            { name: "list_subscribers", description: "mcp.tools.list_subscribers" },
            { name: "get_subscriber", description: "mcp.tools.get_subscriber" },
            { name: "create_subscriber", description: "mcp.tools.create_subscriber" },
            { name: "update_subscriber", description: "mcp.tools.update_subscriber" },
            { name: "delete_subscriber", description: "mcp.tools.delete_subscriber" },
            { name: "sync_subscriber_tags", description: "mcp.tools.sync_subscriber_tags" },
        ]
    },
    {
        name: "mcp.tool_categories.lists",
        tools: [
            { name: "list_contact_lists", description: "mcp.tools.list_contact_lists" },
            { name: "list_tags", description: "mcp.tools.list_tags" },
        ]
    },
    {
        name: "mcp.tool_categories.campaigns",
        tools: [
            { name: "list_campaigns", description: "mcp.tools.list_campaigns" },
            { name: "get_campaign", description: "mcp.tools.get_campaign" },
            { name: "create_campaign", description: "mcp.tools.create_campaign" },
            { name: "update_campaign", description: "mcp.tools.update_campaign" },
            { name: "set_campaign_lists", description: "mcp.tools.set_campaign_lists" },
            { name: "set_campaign_exclusions", description: "mcp.tools.set_campaign_exclusions" },
            { name: "schedule_campaign", description: "mcp.tools.schedule_campaign" },
            { name: "send_campaign", description: "mcp.tools.send_campaign" },
            { name: "get_campaign_stats", description: "mcp.tools.get_campaign_stats" },
            { name: "delete_campaign", description: "mcp.tools.delete_campaign" },
        ]
    },
    {
        name: "mcp.tool_categories.ab_tests",
        tools: [
            { name: "list_ab_tests", description: "mcp.tools.list_ab_tests" },
            { name: "create_ab_test", description: "mcp.tools.create_ab_test" },
            { name: "add_ab_variant", description: "mcp.tools.add_ab_variant" },
            { name: "start_ab_test", description: "mcp.tools.start_ab_test" },
            { name: "end_ab_test", description: "mcp.tools.end_ab_test" },
            { name: "get_ab_test_results", description: "mcp.tools.get_ab_test_results" },
            { name: "delete_ab_test", description: "mcp.tools.delete_ab_test" },
        ]
    },
    {
        name: "mcp.tool_categories.funnels",
        tools: [
            { name: "list_funnels", description: "mcp.tools.list_funnels" },
            { name: "get_funnel", description: "mcp.tools.get_funnel" },
            { name: "create_funnel", description: "mcp.tools.create_funnel" },
            { name: "add_funnel_step", description: "mcp.tools.add_funnel_step" },
            { name: "activate_funnel", description: "mcp.tools.activate_funnel" },
            { name: "pause_funnel", description: "mcp.tools.pause_funnel" },
            { name: "get_funnel_stats", description: "mcp.tools.get_funnel_stats" },
            { name: "delete_funnel", description: "mcp.tools.delete_funnel" },
        ]
    },
    {
        name: "mcp.tool_categories.messaging",
        tools: [
            { name: "send_email", description: "mcp.tools.send_email" },
            { name: "send_sms", description: "mcp.tools.send_sms" },
        ]
    },
    {
        name: "mcp.tool_categories.account",
        tools: [
            { name: "test_connection", description: "mcp.tools.test_connection" },
            { name: "get_account_info", description: "mcp.tools.get_account_info" },
            { name: "list_placeholders", description: "mcp.tools.list_placeholders" },
        ]
    },
];

const clients = [
    {
        name: "Claude Desktop",
        icon: "🤖",
        description: "mcp.clients.claude.description",
        configPath: "~/Library/Application Support/Claude/claude_desktop_config.json"
    },
    {
        name: "Cursor IDE",
        icon: "💻",
        description: "mcp.clients.cursor.description",
        configPath: "Cursor Settings → MCP Servers"
    },
    {
        name: "VS Code",
        icon: "📝",
        description: "mcp.clients.vscode.description",
        configPath: ".vscode/mcp.json"
    },
];

const installSteps = [
    {
        title: "mcp.steps.api_key.title",
        description: "mcp.steps.api_key.description",
    },
    {
        title: "mcp.steps.add_env.title",
        description: "mcp.steps.add_env.description",
    },
    {
        title: "mcp.steps.build.title",
        description: "mcp.steps.build.description",
    },
    {
        title: "mcp.steps.configure.title",
        description: "mcp.steps.configure.description",
    },
];

const exampleQueries = [
    "mcp.examples.show_lists",
    "mcp.examples.count_subscribers",
    "mcp.examples.create_subscriber",
    "mcp.examples.send_email",
    "mcp.examples.analyze_list",
];
</script>

<template>
    <Head :title="$t('mcp.page_title')" />

    <AuthenticatedLayout>
        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Breadcrumb -->
                <div class="mb-8 flex items-center gap-2 text-sm text-gray-500 dark:text-slate-400">
                    <Link
                        :href="route('marketplace.index')"
                        class="hover:text-gray-900 dark:hover:text-white transition-colors"
                    >
                        {{ $t('marketplace.title') }}
                    </Link>
                    <span>/</span>
                    <span class="text-gray-900 dark:text-white">{{ $t('mcp.title') }}</span>
                </div>

                <div class="grid gap-8 lg:grid-cols-3">
                    <!-- Main Content -->
                    <div class="lg:col-span-2 space-y-8">
                        <!-- Hero Header -->
                        <div class="overflow-hidden rounded-2xl bg-white dark:bg-slate-800 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm">
                            <div class="bg-gradient-to-r from-violet-600/10 via-indigo-600/10 to-purple-600/10 dark:from-violet-600/20 dark:via-indigo-600/20 dark:to-purple-600/20 px-8 py-12">
                                <div class="flex items-start gap-6">
                                    <div class="h-24 w-24 shrink-0 overflow-hidden rounded-2xl bg-gradient-to-br from-violet-500 to-indigo-600 p-4 shadow-lg ring-4 ring-violet-500/20 flex items-center justify-center">
                                        <span class="text-4xl">🤖</span>
                                    </div>
                                    <div class="pt-2">
                                        <div class="flex items-center gap-3 mb-2">
                                            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                                                {{ $t('mcp.hero_title') }}
                                            </h1>
                                            <span class="rounded-full bg-emerald-100 dark:bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-500/20">
                                                {{ $t('marketplace.active') }}
                                            </span>
                                        </div>
                                        <p class="text-lg text-gray-600 dark:text-slate-300">
                                            {{ $t('mcp.hero_subtitle') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="p-8">
                                <p class="text-gray-600 dark:text-slate-400 leading-relaxed text-lg">
                                    {{ $t('mcp.hero_description') }}
                                </p>
                            </div>
                        </div>

                        <!-- How It Works -->
                        <div class="rounded-2xl bg-white dark:bg-slate-800 p-8 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm">
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">
                                💡 {{ $t('mcp.how_it_works_title') }}
                            </h2>
                            <div class="rounded-xl bg-gray-50 dark:bg-slate-900/50 p-6 mb-6">
                                <div class="flex items-center justify-between flex-wrap gap-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-100 dark:bg-indigo-500/10 text-xl">
                                            🤖
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900 dark:text-white">AI Assistant</p>
                                            <p class="text-xs text-gray-500 dark:text-slate-500">Claude, Cursor, VS Code</p>
                                        </div>
                                    </div>
                                    <svg class="h-6 w-6 text-gray-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                    </svg>
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-violet-100 dark:bg-violet-500/10 text-xl">
                                            ⚡
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900 dark:text-white">MCP Server</p>
                                            <p class="text-xs text-gray-500 dark:text-slate-500">STDIO Protocol</p>
                                        </div>
                                    </div>
                                    <svg class="h-6 w-6 text-gray-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                    </svg>
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-500/10 text-xl">
                                            📧
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-900 dark:text-white">NetSendo</p>
                                            <p class="text-xs text-gray-500 dark:text-slate-500">REST API v1</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-slate-400">
                                {{ $t('mcp.how_it_works_description') }}
                            </p>
                        </div>

                        <!-- Features -->
                        <div class="rounded-2xl bg-white dark:bg-slate-800 p-8 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm">
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">
                                🚀 {{ $t('mcp.features_title') }}
                            </h2>
                            <div class="grid gap-6 sm:grid-cols-2">
                                <div
                                    v-for="feature in features"
                                    :key="feature.title"
                                    class="flex gap-4"
                                >
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-50 dark:bg-white/5 text-xl text-gray-600 dark:text-white">
                                        {{ feature.icon }}
                                    </div>
                                    <div>
                                        <h3 class="font-semibold text-gray-900 dark:text-white">
                                            {{ $t(feature.title) }}
                                        </h3>
                                        <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">
                                            {{ $t(feature.description) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Installation Steps -->
                        <div class="rounded-2xl bg-white dark:bg-slate-800 p-8 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm">
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">
                                📦 {{ $t('mcp.installation_title') }}
                            </h2>

                            <!-- Installation Mode Tabs -->
                            <div class="flex rounded-xl bg-gray-100 dark:bg-slate-900/50 p-1 mb-6">
                                <button
                                    @click="activeInstallMode = 'remote'"
                                    :class="[
                                        'flex-1 rounded-lg px-4 py-2.5 text-sm font-medium transition-all',
                                        activeInstallMode === 'remote'
                                            ? 'bg-white dark:bg-slate-800 text-gray-900 dark:text-white shadow-sm'
                                            : 'text-gray-600 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white'
                                    ]"
                                >
                                    🌐 {{ $t('mcp.mode_remote') }}
                                </button>
                                <button
                                    @click="activeInstallMode = 'local'"
                                    :class="[
                                        'flex-1 rounded-lg px-4 py-2.5 text-sm font-medium transition-all',
                                        activeInstallMode === 'local'
                                            ? 'bg-white dark:bg-slate-800 text-gray-900 dark:text-white shadow-sm'
                                            : 'text-gray-600 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white'
                                    ]"
                                >
                                    🐳 {{ $t('mcp.mode_local') }}
                                </button>
                            </div>

                            <!-- Remote Mode Content -->
                            <div v-if="activeInstallMode === 'remote'" class="space-y-6 mb-6">
                                <div class="rounded-lg bg-indigo-50 dark:bg-indigo-500/10 px-4 py-3 ring-1 ring-indigo-500/20">
                                    <p class="text-sm text-indigo-700 dark:text-indigo-400">
                                        {{ $t('mcp.remote_setup_hint') }}
                                    </p>
                                </div>
                                <div class="rounded-xl bg-emerald-50 dark:bg-emerald-500/10 p-4 ring-1 ring-emerald-500/20">
                                    <p class="text-sm text-emerald-700 dark:text-emerald-400">
                                        <span class="font-semibold">{{ $t('mcp.remote_recommended') }}</span> — {{ $t('mcp.remote_description') }}
                                    </p>
                                </div>
                                <div class="rounded-lg bg-indigo-50 dark:bg-indigo-500/10 px-4 py-3 ring-1 ring-indigo-500/20">
                                    <p class="text-sm text-indigo-700 dark:text-indigo-400">
                                        {{ $t('mcp.remote_setup_hint') }}
                                    </p>
                                </div>
                                <div class="flex gap-4">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-500/10 text-sm font-bold text-indigo-600 dark:text-indigo-400 ring-1 ring-indigo-500/20">1</div>
                                    <div class="flex-1">
                                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ $t('mcp.steps.api_key.title') }}</h3>
                                        <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">{{ $t('mcp.steps.api_key.description') }}</p>
                                    </div>
                                </div>
                                <div class="flex gap-4">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-500/10 text-sm font-bold text-indigo-600 dark:text-indigo-400 ring-1 ring-indigo-500/20">2</div>
                                    <div class="flex-1">
                                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ $t('mcp.steps.install_node.title') }}</h3>
                                        <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">{{ $t('mcp.steps.install_node.description') }}</p>
                                    </div>
                                </div>
                                <div class="flex gap-4">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-500/10 text-sm font-bold text-indigo-600 dark:text-indigo-400 ring-1 ring-indigo-500/20">3</div>
                                    <div class="flex-1">
                                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ $t('mcp.steps.configure.title') }}</h3>
                                        <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">{{ $t('mcp.steps.configure_remote.description') }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Local Mode Content -->
                            <div v-else class="space-y-6 mb-6">
                                <div class="rounded-xl bg-blue-50 dark:bg-blue-500/10 p-4 ring-1 ring-blue-500/20">
                                    <p class="text-sm text-blue-700 dark:text-blue-400">
                                        <span class="font-semibold">{{ $t('mcp.local_docker') }}</span> — {{ $t('mcp.local_description') }}
                                    </p>
                                </div>
                                <div class="rounded-lg bg-indigo-50 dark:bg-indigo-500/10 px-4 py-3 ring-1 ring-indigo-500/20">
                                    <p class="text-sm text-indigo-700 dark:text-indigo-400">
                                        {{ $t('mcp.local_setup_note') }}
                                    </p>
                                </div>
                                <div
                                    v-for="(step, index) in installSteps"
                                    :key="index"
                                    class="flex gap-4"
                                >
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-500/10 text-sm font-bold text-indigo-600 dark:text-indigo-400 ring-1 ring-indigo-500/20">
                                        {{ index + 1 }}
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="font-semibold text-gray-900 dark:text-white">
                                            {{ $t(step.title) }}
                                        </h3>
                                        <p class="text-sm text-gray-600 dark:text-slate-400 mt-1">
                                            {{ $t(step.description) }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Config Example -->
                            <div class="rounded-xl bg-gray-900 dark:bg-slate-950 p-6 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10">
                                <div class="flex items-center justify-between mb-4">
                                    <p class="text-sm text-gray-400 dark:text-slate-400">
                                        {{ $t('mcp.config_example') }} (Claude Desktop)
                                    </p>
                                    <button
                                        @click="copyToClipboard(currentConfig, 'config')"
                                        class="text-xs text-gray-400 hover:text-white transition-colors flex items-center gap-1"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                        {{ copied === 'config' ? $t('common.copied') : $t('common.copy') }}
                                    </button>
                                </div>
                                <pre class="text-sm text-emerald-400 font-mono overflow-x-auto"><code>{{ currentConfig }}</code></pre>

                                <!-- Replace API key warning for remote mode -->
                                <div v-if="activeInstallMode === 'remote'" class="mt-4 rounded-lg bg-amber-500/10 px-4 py-3 ring-1 ring-amber-500/20">
                                    <p class="text-xs text-amber-400">
                                        ⚠️ {{ $t('mcp.replace_api_key') }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Available Tools -->
                        <div class="rounded-2xl bg-white dark:bg-slate-800 p-8 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm">
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">
                                🛠️ {{ $t('mcp.tools_title') }}
                            </h2>
                            <p class="text-sm text-gray-600 dark:text-slate-400 mb-6">
                                {{ $t('mcp.tools_count', { count: 37 }) }}
                            </p>
                            <div class="space-y-6">
                                <div
                                    v-for="category in toolCategories"
                                    :key="category.name"
                                >
                                    <h3 class="text-sm font-semibold text-gray-700 dark:text-slate-300 mb-3 flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                                        {{ $t(category.name) }}
                                    </h3>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <div
                                            v-for="tool in category.tools"
                                            :key="tool.name"
                                            class="flex items-start gap-2 rounded-lg bg-gray-50 dark:bg-white/5 px-3 py-2"
                                        >
                                            <code class="text-xs font-mono text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-500/10 px-1.5 py-0.5 rounded shrink-0">
                                                {{ tool.name }}
                                            </code>
                                            <span class="text-xs text-gray-600 dark:text-slate-400">
                                                {{ $t(tool.description) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Placeholders Documentation -->
                        <div class="rounded-2xl bg-white dark:bg-slate-800 p-8 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm">
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">
                                ✨ {{ $t('mcp.placeholders_title') }}
                            </h2>
                            <p class="text-sm text-gray-600 dark:text-slate-400 mb-6">
                                {{ $t('mcp.placeholders_description') }}
                            </p>
                            <div class="space-y-4">
                                <!-- Standard Placeholders -->
                                <div class="rounded-xl bg-gray-50 dark:bg-slate-900/50 p-4">
                                    <h3 class="text-sm font-semibold text-gray-700 dark:text-slate-300 mb-3">{{ $t('mcp.placeholders_standard') }}</h3>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <div class="flex items-center gap-2">
                                            <code class="text-xs font-mono text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 px-1.5 py-0.5 rounded">[[first_name]]</code>
                                            <span class="text-xs text-gray-600 dark:text-slate-400">{{ $t('mcp.placeholder_first_name') }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <code class="text-xs font-mono text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 px-1.5 py-0.5 rounded">[[!fname]]</code>
                                            <span class="text-xs text-gray-600 dark:text-slate-400">{{ $t('mcp.placeholder_vocative') }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <code class="text-xs font-mono text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 px-1.5 py-0.5 rounded">[[last_name]]</code>
                                            <span class="text-xs text-gray-600 dark:text-slate-400">{{ $t('mcp.placeholder_last_name') }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <code class="text-xs font-mono text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 px-1.5 py-0.5 rounded">[[email]]</code>
                                            <span class="text-xs text-gray-600 dark:text-slate-400">{{ $t('mcp.placeholder_email') }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <code class="text-xs font-mono text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10 px-1.5 py-0.5 rounded">[[phone]]</code>
                                            <span class="text-xs text-gray-600 dark:text-slate-400">{{ $t('mcp.placeholder_phone') }}</span>
                                        </div>
                                    </div>
                                </div>
                                <!-- System Placeholders -->
                                <div class="rounded-xl bg-indigo-50 dark:bg-indigo-500/10 p-4 ring-1 ring-indigo-500/20">
                                    <h3 class="text-sm font-semibold text-indigo-700 dark:text-indigo-300 mb-3">{{ $t('mcp.placeholders_system') }}</h3>
                                    <div class="grid gap-2">
                                        <div class="flex items-center gap-2">
                                            <code class="text-xs font-mono text-indigo-600 dark:text-indigo-400 bg-indigo-100 dark:bg-indigo-500/10 px-1.5 py-0.5 rounded">[[unsubscribe_link]]</code>
                                            <span class="text-xs text-indigo-700 dark:text-indigo-300">{{ $t('mcp.placeholder_unsubscribe') }}</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <code class="text-xs font-mono text-indigo-600 dark:text-indigo-400 bg-indigo-100 dark:bg-indigo-500/10 px-1.5 py-0.5 rounded">[[manage]]</code>
                                            <span class="text-xs text-indigo-700 dark:text-indigo-300">{{ $t('mcp.placeholder_manage') }}</span>
                                        </div>
                                    </div>
                                </div>
                                <!-- Gender Forms -->
                                <div class="rounded-xl bg-violet-50 dark:bg-violet-500/10 p-4 ring-1 ring-violet-500/20">
                                    <h3 class="text-sm font-semibold text-violet-700 dark:text-violet-300 mb-3">{{ $t('mcp.placeholders_gender') }}</h3>
                                    <div class="space-y-2">
                                        <div class="flex items-start gap-2">
                                            <code class="text-xs font-mono text-violet-600 dark:text-violet-400 bg-violet-100 dark:bg-violet-500/10 px-1.5 py-0.5 rounded shrink-0">{{Drogi|Droga}}</code>
                                            <span class="text-xs text-violet-700 dark:text-violet-300">{{ $t('mcp.placeholder_gender_example') }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Agent Prompt -->
                        <div class="rounded-2xl bg-gradient-to-br from-emerald-500/5 to-teal-500/5 p-8 ring-1 ring-emerald-500/20">
                            <div class="flex items-center justify-between mb-6">
                                <h2 class="text-xl font-bold text-gray-900 dark:text-white">
                                    🤖 {{ $t('mcp.agent_prompt_title') }}
                                </h2>
                                <button
                                    @click="showAgentPrompt = !showAgentPrompt"
                                    class="text-sm font-medium text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 transition-colors"
                                >
                                    {{ showAgentPrompt ? $t('common.hide') : $t('common.show') }}
                                </button>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-slate-400 mb-4">
                                {{ $t('mcp.agent_prompt_description') }}
                            </p>
                            <div v-if="showAgentPrompt" class="rounded-xl bg-gray-900 dark:bg-slate-950 p-6 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10">
                                <div class="flex items-center justify-between mb-4">
                                    <p class="text-sm text-gray-400 dark:text-slate-400">
                                        {{ $t('mcp.agent_prompt_label') }}
                                    </p>
                                    <button
                                    @click="copyToClipboard(agentPromptContent, 'agent-prompt')"
                                        class="text-xs text-gray-400 hover:text-white transition-colors flex items-center gap-1"
                                    >
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                        {{ copied === 'agent-prompt' ? $t('common.copied') : $t('common.copy') }}
                                    </button>
                                </div>
                                <pre class="text-sm text-emerald-400 font-mono overflow-x-auto whitespace-pre-wrap"><code>{{ agentPromptContent }}</code></pre>
                            </div>
                        </div>

                        <!-- Example Queries -->
                        <div class="rounded-2xl bg-gradient-to-br from-violet-500/5 to-indigo-500/5 p-8 ring-1 ring-violet-500/20">
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-6">
                                💬 {{ $t('mcp.examples_title') }}
                            </h2>
                            <div class="space-y-3">
                                <div
                                    v-for="query in exampleQueries"
                                    :key="query"
                                    class="flex items-center gap-3 rounded-xl bg-white dark:bg-slate-800/80 px-4 py-3 shadow-sm"
                                >
                                    <span class="text-indigo-500">→</span>
                                    <span class="text-gray-700 dark:text-slate-300 italic">
                                        "{{ $t(query) }}"
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Supported Clients -->
                        <div class="rounded-2xl bg-white dark:bg-slate-800 p-6 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-4">
                                {{ $t('mcp.supported_clients') }}
                            </h3>
                            <div class="space-y-3">
                                <div
                                    v-for="client in clients"
                                    :key="client.name"
                                    class="flex items-start gap-3 rounded-xl bg-gray-50 dark:bg-white/5 px-4 py-3"
                                >
                                    <span class="text-xl">{{ client.icon }}</span>
                                    <div>
                                        <p class="font-medium text-gray-900 dark:text-white text-sm">{{ client.name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-slate-500">{{ $t(client.description) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- API Configuration -->
                        <div class="rounded-2xl bg-white dark:bg-slate-800 p-6 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-4">
                                🔑 {{ $t('mcp.api_config_title') }}
                            </h3>

                            <div class="space-y-4">
                                <div>
                                    <p class="text-xs text-gray-500 dark:text-slate-500 mb-2">{{ $t('mcp.api_url_label') }}</p>
                                    <div class="flex items-center gap-2 rounded-lg bg-gray-50 dark:bg-white/5 px-3 py-2">
                                        <code class="text-sm text-gray-700 dark:text-slate-300 truncate flex-1">{{ appUrl }}</code>
                                        <button
                                            @click="copyToClipboard(appUrl, 'url')"
                                            class="text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors"
                                        >
                                            <svg v-if="copied !== 'url'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                            </svg>
                                            <svg v-else class="h-4 w-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <div>
                                    <p class="text-xs text-gray-500 dark:text-slate-500 mb-2">{{ $t('mcp.api_key_label') }}</p>
                                    <Link
                                        :href="route('settings.api-keys.index')"
                                        class="flex items-center justify-between rounded-lg bg-indigo-50 dark:bg-indigo-500/10 px-4 py-3 text-sm font-medium text-indigo-600 dark:text-indigo-400 transition-colors hover:bg-indigo-100 dark:hover:bg-indigo-500/20"
                                    >
                                        <span>{{ $t('mcp.manage_api_keys') }}</span>
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- Test Connection -->
                        <div class="rounded-2xl bg-white dark:bg-slate-800 p-6 border border-gray-200 dark:border-transparent dark:ring-1 dark:ring-white/10 shadow-sm">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-4">
                                🧪 {{ $t('mcp.test_connection_title') }}
                            </h3>

                            <p class="text-sm text-gray-600 dark:text-slate-400 mb-4">
                                {{ $t('mcp.test_connection_description') }}
                            </p>

                            <div>
                                <p class="text-xs text-gray-500 dark:text-slate-500 mb-2">{{ $t('mcp.test_endpoint_label') }}</p>
                                <div class="flex items-center gap-2 rounded-lg bg-gray-50 dark:bg-white/5 px-3 py-2 mb-3">
                                    <code class="text-sm text-gray-700 dark:text-slate-300 truncate flex-1">{{ appUrl }}/api/mcp/test</code>
                                    <button
                                        @click="copyToClipboard(appUrl + '/api/mcp/test', 'endpoint')"
                                        class="text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors"
                                    >
                                        <svg v-if="copied !== 'endpoint'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                        <svg v-else class="h-4 w-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <p class="text-xs text-gray-500 dark:text-slate-500 mb-2">{{ $t('mcp.test_curl_example') }}</p>
                                <div class="rounded-lg bg-gray-900 dark:bg-slate-950 p-3 relative">
                                    <pre class="text-xs text-emerald-400 font-mono overflow-x-auto whitespace-pre-wrap"><code>curl -X GET "{{ appUrl }}/api/mcp/test" \
  -H "Authorization: Bearer YOUR_API_KEY"</code></pre>
                                    <button
                                        @click="copyToClipboard(`curl -X GET '${appUrl}/api/mcp/test' -H 'Authorization: Bearer YOUR_API_KEY'`, 'curl')"
                                        class="absolute top-2 right-2 text-gray-400 hover:text-white transition-colors"
                                    >
                                        <svg v-if="copied !== 'curl'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                        </svg>
                                        <svg v-else class="h-4 w-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div class="mt-4 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 px-4 py-3 ring-1 ring-emerald-500/20">
                                <p class="text-xs text-emerald-700 dark:text-emerald-400">
                                    ✓ {{ $t('mcp.test_success_hint') }}
                                </p>
                            </div>
                        </div>

                        <!-- Security Note -->
                        <div class="rounded-2xl bg-amber-50 dark:bg-amber-500/10 p-6 ring-1 ring-amber-500/20">
                            <h3 class="font-semibold text-amber-800 dark:text-amber-300 mb-2 flex items-center gap-2">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                {{ $t('mcp.security_title') }}
                            </h3>
                            <p class="text-sm text-amber-700 dark:text-amber-400/80">
                                {{ $t('mcp.security_note') }}
                            </p>
                        </div>

                        <!-- Help -->
                        <div class="rounded-2xl bg-gradient-to-br from-indigo-500/10 to-purple-500/10 p-6 ring-1 ring-indigo-500/20">
                            <h3 class="font-semibold text-gray-900 dark:text-white mb-2">
                                {{ $t('mcp.need_help') }}
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-slate-400 mb-4">
                                {{ $t('mcp.help_description') }}
                            </p>
                            <a
                                href="https://netsendo.com/docs/mcp"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-indigo-500"
                            >
                                {{ $t('mcp.documentation') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
