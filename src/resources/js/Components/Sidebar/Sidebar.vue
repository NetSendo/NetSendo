<script setup>
import { ref, computed } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import SidebarItem from './SidebarItem.vue';
import SidebarGroup from './SidebarGroup.vue';
import HelpMenu from './HelpMenu.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { useTheme } from '@/Composables/useTheme';

const props = defineProps({
    collapsed: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['toggle']);

const page = usePage();
const { isDark } = useTheme();

// Logos - always use light (white) logos since sidebar is always dark
const logoFull = 'https://gregciupek.com/wp-content/uploads/2025/12/Logo-NetSendo-1700-x-500-px-1.png';
const logoSquare = 'https://gregciupek.com/wp-content/uploads/2025/12/logo-netsendo-kwadrat-biale.png';

const currentLogo = computed(() => {
    return props.collapsed ? logoSquare : logoFull;
});

// Handle logo click - refresh dashboard
const handleLogoClick = () => {
    router.visit(route('dashboard'), { preserveState: false });
};

// Check if route is active
const isActive = (routeName) => {
    try {
        return route().current(routeName);
    } catch {
        return false;
    }
};

// License info
const license = computed(() => page.props.license);
const isGold = computed(() => license.value?.plan === 'GOLD');

// Accordion Logic
const openGroup = ref(null);

const toggleGroup = (groupLabel) => {
    if (openGroup.value === groupLabel) {
        openGroup.value = null;
    } else {
        openGroup.value = groupLabel;
    }
};

// Initialize open group based on current route
import { onMounted, watch } from 'vue';

const updateOpenGroup = () => {
    // Automatyzacja (Check first because of overlaps like fields.global)
    if (isActive('templates.*') || isActive('inserts.*') || isActive('settings.fields.*') || isActive('external-pages.*') || isActive('funnels.*') || isActive('automations.*')) {
        openGroup.value = 'automation';
        return;
    }

    // Lista adresowa (Includes specific subscriber route and other modules)
    if (isActive('messages.*') || isActive('multimail.*') || isActive('sms.*') || isActive('subscribers.subscribed') || isActive('stats.*') || isActive('mailinglist.*') || isActive('forms.*') || isActive('settings.fields.*') || isActive('api.*') || isActive('settings.system-messages.*')) {
        openGroup.value = 'address_list';
        return;
    }

    // CRM (Catches remaining subscribers.* routes)
    if (isActive('mailing-lists.*') || isActive('sms-lists.*') || isActive('groups.*') || isActive('subscribers.*') || isActive('tags.*')) {
        openGroup.value = 'crm';
        return;
    }

    // Statystyki
    if (isActive('settings.tracked-links.*') || isActive('settings.stats.*') || isActive('settings.activity-logs.*')) {
        openGroup.value = 'statistics';
        return;
    }

    // Ustawienia
    if (isActive('defaults.*') || isActive('smtp.*') || isActive('names.*') || isActive('users.*') || isActive('update.*') || isActive('backup.*') || isActive('profile.*') || isActive('settings.ai-integrations.*') || isActive('settings.integrations.*') || isActive('settings.mailboxes.*') || isActive('settings.cron.*') || isActive('settings.api-keys.*') || isActive('settings.backup.*')) {
        openGroup.value = 'settings';
        return;
    }
};

// Watch for route changes (since we use Inertia, the component might stay mounted)
watch(() => page.url, updateOpenGroup, { immediate: true });
</script>

<template>
    <aside
        class="fixed left-0 top-0 z-40 flex h-screen flex-col bg-gradient-to-b from-slate-900 via-slate-800 to-slate-900 transition-all duration-300"
        :class="collapsed ? 'w-20' : 'w-72'"
    >
        <!-- Floating Collapse Button (outside sidebar) -->
        <button
            @click="emit('toggle')"
            class="absolute top-7 z-50 flex h-6 w-6 items-center justify-center rounded-full bg-slate-700 text-slate-300 shadow-lg transition-all duration-300 hover:bg-slate-600 hover:text-white"
            :class="collapsed ? 'left-[72px]' : 'left-[276px]'"
            :title="collapsed ? $t('navigation.expand') : $t('navigation.collapse')"
        >
            <svg
                class="h-3.5 w-3.5 transition-transform duration-300"
                :class="{ 'rotate-180': collapsed }"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
        </button>

        <!-- Logo Section -->
        <div 
            class="flex h-20 items-center justify-center border-b px-4"
            :class="isDark ? 'border-white/5' : 'border-white/10'"
        >
            <button @click="handleLogoClick" class="flex items-center justify-center">
                <img
                    :src="currentLogo"
                    alt="NetSendo"
                    class="transition-all duration-300 object-contain"
                    :class="collapsed ? 'h-12 w-12' : 'h-10 w-auto max-w-[180px]'"
                />
            </button>
        </div>

        <!-- Navigation -->
        <nav 
            class="flex-1 space-y-1 overflow-y-auto px-3 py-4 scrollbar-thin"
            :class="isDark ? 'scrollbar-thumb-slate-700' : 'scrollbar-thumb-slate-500'"
        >


            
            <!-- Dashboard -->
            <SidebarItem :href="route('dashboard')" :active="isActive('dashboard')" :collapsed="collapsed">
                <template #icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                </template>
                Dashboard
            </SidebarItem>

            <!-- Marketplace -->
            <SidebarItem href="/marketplace" :active="isActive('marketplace.*')" :collapsed="collapsed">
                <template #icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </template>
                {{ $t('navigation.marketplace') }}
            </SidebarItem>

            <div class="my-4 border-t border-white/5"></div>

            <!-- Lista adresowa -->
            <SidebarGroup :label="$t('navigation.groups.address_list')" :collapsed="collapsed" :is-open="openGroup === 'address_list'" @toggle="toggleGroup('address_list')">
                <template #icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </template>

                <SidebarItem :href="route('messages.create')" :active="isActive('messages.create')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </template>
                    {{ $t('navigation.add_message') }}
                </SidebarItem>

                <SidebarItem :href="route('messages.index')" :active="isActive('messages.index')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                        </svg>
                    </template>
                    {{ $t('navigation.message_list') }}
                </SidebarItem>



                <!-- SMS -->
                <SidebarItem :href="route('sms.create')" :active="isActive('sms.create')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                    </template>
                    {{ $t('navigation.add_sms') }}
                </SidebarItem>

                <SidebarItem :href="route('sms.index')" :active="isActive('sms.index')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                        </svg>
                    </template>
                    {{ $t('navigation.sms_list') }}
                </SidebarItem>


                <SidebarItem :href="route('settings.system-messages.index')" :active="isActive('settings.system-messages.*')" :collapsed="collapsed">
                     <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                        </svg>
                    </template>
                    Wiadomości Systemowe
                </SidebarItem>


                <SidebarItem href="/subscribers/list" :active="isActive('subscribers.subscribed')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </template>
                    {{ $t('navigation.list_subscribers') }}
                </SidebarItem>

                <SidebarItem href="/stats" :active="isActive('stats.*')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </template>
                    {{ $t('navigation.statistics') }}
                </SidebarItem>

                <SidebarItem :href="route('defaults.index')" :active="isActive('defaults.*')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </template>
                    {{ $t('navigation.default_settings') }}
                </SidebarItem>

                <SidebarItem href="/forms" :active="isActive('forms.*')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </template>
                    {{ $t('navigation.forms') }}
                </SidebarItem>

                <SidebarItem href="/mailinglist/details" :active="isActive('mailinglist.details')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                        </svg>
                    </template>
                    {{ $t('navigation.advanced') }}
                </SidebarItem>

                <SidebarItem :href="route('settings.fields.index')" :active="isActive('settings.fields.*')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                        </svg>
                    </template>
                    {{ $t('navigation.field_management') }}
                </SidebarItem>

                <SidebarItem href="/api" :active="isActive('api.*')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                        </svg>
                    </template>
                    {{ $t('navigation.integration') }}
                </SidebarItem>
            </SidebarGroup>

            <!-- CRM -->
            <SidebarGroup :label="$t('navigation.groups.crm')" :collapsed="collapsed" :is-open="openGroup === 'crm'" @toggle="toggleGroup('crm')">
                <template #icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </template>

                <SidebarItem :href="route('mailing-lists.index')" :active="isActive('mailing-lists.*')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </template>
                    {{ $t('navigation.address_lists') }}
                </SidebarItem>

                <SidebarItem :href="route('sms-lists.index')" :active="isActive('sms-lists.*')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                    </template>
                    {{ $t('sms_lists.title') }}
                </SidebarItem>

                <SidebarItem :href="route('groups.index')" :active="isActive('groups.*')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                        </svg>
                    </template>
                    {{ $t('navigation.list_groups') }}
                </SidebarItem>
                
                <SidebarItem :href="route('tags.index')" :active="isActive('tags.*')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                    </template>
                    {{ $t('navigation.list_tags') }}
                </SidebarItem>

                <SidebarItem :href="route('subscribers.create')" :active="isActive('subscribers.create')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                    </template>
                    {{ $t('navigation.add_subscriber') }}
                </SidebarItem>

                <SidebarItem :href="route('subscribers.index')" :active="isActive('subscribers.index')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </template>
                    {{ $t('navigation.subscriber_list') }}
                </SidebarItem>

                <SidebarItem :href="route('subscribers.import')" :active="isActive('subscribers.import')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                        </svg>
                    </template>
                    {{ $t('navigation.import') }}
                </SidebarItem>

                <SidebarItem href="/subscribers/largeimport" :active="isActive('subscribers.largeimport')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </template>
                    {{ $t('navigation.large_import') }}
                </SidebarItem>

                <SidebarItem href="/subscribers/massdel" :active="isActive('subscribers.massdel')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </template>
                    {{ $t('navigation.mass_delete') }}
                </SidebarItem>
            </SidebarGroup>

            <!-- Automatyzacja -->
            <SidebarGroup :label="$t('navigation.groups.automation')" :collapsed="collapsed" :is-open="openGroup === 'automation'" @toggle="toggleGroup('automation')">
                <template #icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                    </svg>
                </template>

                <SidebarItem :href="route('templates.index')" :active="isActive('templates.index')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                        </svg>
                    </template>
                    {{ $t('navigation.message_templates') }}
                </SidebarItem>

                <SidebarItem :href="route('inserts.index')" :active="isActive('inserts.*')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                    </template>
                    {{ $t('navigation.insert_templates') }}
                </SidebarItem>

                <SidebarItem :href="route('settings.fields.index')" :active="isActive('settings.fields.*')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                        </svg>
                    </template>
                    {{ $t('navigation.field_management') }}
                </SidebarItem>

                <SidebarItem :href="route('external-pages.index')" :active="isActive('external-pages.*')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                    </template>
                    {{ $t('navigation.external_pages') }}
                </SidebarItem> 

                <SidebarItem :href="route('funnels.index')" :active="isActive('funnels.*')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                    </template>
                    {{ $t('funnels.title') }}
                </SidebarItem>

                <SidebarItem :href="route('automations.index')" :active="isActive('automations.*')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </template>
                    {{ $t('automations.title') }}
                </SidebarItem>


            </SidebarGroup>

            <!-- Statystyki -->
            <SidebarGroup :label="$t('navigation.groups.statistics')" :collapsed="collapsed" :is-open="openGroup === 'statistics'" @toggle="toggleGroup('statistics')">
                <template #icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                </template>

                <SidebarItem :href="route('settings.tracked-links.index')" :active="isActive('settings.tracked-links.*')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                        </svg>
                    </template>
                    {{ $t('navigation.tracked_links') }}
                </SidebarItem>

                <SidebarItem :href="route('settings.stats.index')" :active="isActive('settings.stats.*')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </template>
                    {{ $t('settings.global_stats') }}
                </SidebarItem>

                <SidebarItem :href="route('settings.activity-logs.index')" :active="isActive('settings.activity-logs.*')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </template>
                    {{ $t('settings.activity_logs') }}
                </SidebarItem>
            </SidebarGroup>

            <!-- Ustawienia -->
            <SidebarGroup :label="$t('navigation.groups.settings')" :collapsed="collapsed" :is-open="openGroup === 'settings'" @toggle="toggleGroup('settings')">
                <template #icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </template>

                <SidebarItem href="/defaults" :active="isActive('defaults.*')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                        </svg>
                    </template>
                    {{ $t('navigation.default_settings') }}
                </SidebarItem>



                <SidebarItem href="/names" :active="isActive('names.*')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                        </svg>
                    </template>
                    {{ $t('navigation.name_database') }}
                </SidebarItem>

                <SidebarItem href="/users" :active="isActive('users.*')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </template>
                    {{ $t('navigation.users') }}
                </SidebarItem>

                <SidebarItem href="/update" :active="isActive('update.*')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    </template>
                    {{ $t('navigation.updates') }}
                </SidebarItem>

                <SidebarItem :href="route('profile.edit')" :active="isActive('profile.*')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                    </template>
                    {{ $t('navigation.security_settings') }}
                </SidebarItem>

                <SidebarItem :href="route('settings.ai-integrations.index')" :active="isActive('settings.ai-integrations.*')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </template>
                    {{ $t('navigation.ai_integrations') }}
                </SidebarItem>

                <SidebarItem :href="route('settings.integrations.index')" :active="isActive('settings.integrations.*')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z" />
                        </svg>
                    </template>
                    Integracje Google
                </SidebarItem>

                <SidebarItem :href="route('settings.mailboxes.index')" :active="isActive('settings.mailboxes.*')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </template>
                    {{ $t('navigation.mailboxes') }}
                </SidebarItem>

                <SidebarItem :href="route('settings.cron.index')" :active="isActive('settings.cron.*')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </template>
                    {{ $t('navigation.cron_settings') }}
                </SidebarItem>

                <SidebarItem :href="route('settings.api-keys.index')" :active="isActive('settings.api-keys.*')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                    </template>
                    {{ $t('navigation.api_keys', 'Klucze API') }}
                </SidebarItem>

                <SidebarItem :href="route('settings.backup.index')" :active="isActive('settings.backup.*')" :collapsed="collapsed">
                    <template #icon>
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                        </svg>
                    </template>
                    {{ $t('navigation.backups') }}
                </SidebarItem>


            </SidebarGroup>

            <div class="my-4 border-t border-white/10"></div>

            <!-- Udział w zyskach -->
            <SidebarItem href="/profit" :active="isActive('profit.*')" :collapsed="collapsed">
                <template #icon>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </template>
                {{ $t('navigation.profit_sharing') }}
            </SidebarItem>

        </nav>

        <!-- Help Menu (above footer) -->
        <div v-if="!collapsed" class="px-3 pb-2">
            <HelpMenu />
        </div>

        <!-- Footer -->
        <div class="border-t border-white/10 p-4">
            <div class="flex items-center justify-between">
                <ThemeToggle />
                
                <div v-if="!collapsed" class="text-right">
                    <div class="text-xs text-slate-400">NetSendo v2</div>
                    <div 
                        v-if="license?.active" 
                        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="{
                            'bg-amber-500/20 text-amber-400': license?.plan === 'GOLD',
                            'bg-slate-500/20 text-slate-400': license?.plan === 'SILVER'
                        }"
                    >
                        <span class="h-1.5 w-1.5 rounded-full" :class="license?.plan === 'GOLD' ? 'bg-amber-500' : 'bg-slate-400'"></span>
                        {{ license?.plan }}
                    </div>
                </div>
            </div>
        </div>
    </aside>
</template>

<style scoped>
/* Custom scrollbar */
.scrollbar-thin::-webkit-scrollbar {
    width: 4px;
}

.scrollbar-thin::-webkit-scrollbar-track {
    background: transparent;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
    background-color: rgb(71 85 105 / 0.5);
    border-radius: 9999px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background-color: rgb(71 85 105 / 0.8);
}
</style>
