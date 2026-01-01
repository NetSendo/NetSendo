<script setup>
import { Head, router, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref, onMounted, onUnmounted, computed } from 'vue';
import WebinarChat from '@/Components/Webinar/WebinarChat.vue';
import WebinarProductPanel from '@/Components/Webinar/WebinarProductPanel.vue';
import ChatControlPanel from '@/Components/Webinar/ChatControlPanel.vue';
import axios from 'axios';

const props = defineProps({
    webinar: Object,
    session: Object,
    registrations: Array,
});

const isLive = ref(props.webinar.status === 'live');
const viewerCount = ref(props.session?.current_viewers || 0);
const activeTab = ref('chat');
const chatSettings = ref(props.webinar.chat_settings || {});
const pendingCount = ref(0);

// Fetch dashboard data periodically
const fetchDashboardData = async () => {
    try {
        const { data } = await axios.get(route('webinars.host.dashboard', props.webinar.id));
        viewerCount.value = data.viewers_count;
        pendingCount.value = data.pending_messages_count;
    } catch (error) {
        console.error('Failed to fetch dashboard:', error);
    }
};

const startWebinar = () => {
    router.post(route('webinars.start', props.webinar.id), {}, {
        onSuccess: () => {
            isLive.value = true;
        }
    });
};

const endWebinar = () => {
    if (confirm(props.t ? props.t('webinars.studio.confirm_end') : 'Are you sure you want to end the webinar?')) {
        router.post(route('webinars.end', props.webinar.id), {}, {
            onSuccess: () => {
                isLive.value = false;
            }
        });
    }
};

const handleSettingsChanged = (newSettings) => {
    chatSettings.value = newSettings;
};

// Echo/Reverb connection for real-time updates
let channel = null;
let dashboardInterval = null;

onMounted(() => {
    fetchDashboardData();
    dashboardInterval = setInterval(fetchDashboardData, 30000); // Every 30 seconds

    if (window.Echo) {
        channel = window.Echo.join(`webinar.${props.webinar.id}`)
            .here((users) => {
                viewerCount.value = users.length;
            })
            .joining((user) => {
                viewerCount.value++;
            })
            .leaving((user) => {
                viewerCount.value--;
            });
    }
});

onUnmounted(() => {
    if (channel) {
        window.Echo.leave(`webinar.${props.webinar.id}`);
    }
    if (dashboardInterval) {
        clearInterval(dashboardInterval);
    }
});
</script>

<template>
    <Head :title="`Studio - ${webinar.name}`" />

    <AuthenticatedLayout>
        <div class="h-[calc(100vh-64px)] flex flex-col">
            <!-- Top Bar -->
            <div class="bg-gray-900 text-white px-4 py-3 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2">
                        <span v-if="isLive" class="flex items-center gap-1.5">
                            <span class="w-3 h-3 bg-red-500 rounded-full animate-pulse"></span>
                            <span class="font-semibold text-red-400">{{ $t('webinars.studio.live_badge') }}</span>
                        </span>
                        <span v-else class="text-gray-400">{{ $t('webinars.studio.inactive_badge') }}</span>
                    </div>
                    <span class="text-gray-500">|</span>
                    <h1 class="font-medium truncate max-w-md">{{ webinar.name }}</h1>
                </div>

                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2 text-sm">
                        <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                        <span>{{ viewerCount }} {{ $t('webinars.chat.viewers', { count: viewerCount }).replace(viewerCount + ' ', '') }}</span>
                    </div>

                    <!-- Pending messages badge -->
                    <div v-if="pendingCount > 0" class="flex items-center gap-1 px-2 py-1 bg-yellow-500/20 rounded-full">
                        <span class="text-yellow-400 text-sm">⏳</span>
                        <span class="text-xs text-yellow-300">{{ pendingCount }}</span>
                    </div>

                    <!-- Scenario Builder Link -->
                    <Link
                        v-if="webinar.type === 'auto'"
                        :href="route('webinars.scripts.builder', webinar.id)"
                        class="flex items-center gap-2 px-3 py-1.5 bg-purple-600/20 hover:bg-purple-600/30 border border-purple-500/50 rounded-lg text-purple-300 text-sm transition-colors"
                    >
                        🎬 {{ $t('webinars.scenario.title') }}
                    </Link>

                    <!-- Auto-webinar info -->
                    <div v-if="webinar.type === 'auto'" class="flex items-center gap-2 px-4 py-2 bg-indigo-600/20 border border-indigo-500/30 rounded-lg">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-indigo-300 text-sm">{{ $t('webinars.studio.auto_mode') }}</span>
                    </div>

                    <!-- Live webinar controls -->
                    <template v-else>
                        <button
                            v-if="!isLive"
                            @click="startWebinar"
                            class="flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg font-medium transition-colors"
                        >
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8 5v14l11-7z"/>
                            </svg>
                            {{ $t('webinars.studio.start') }}
                        </button>
                        <button
                            v-else
                            @click="endWebinar"
                            class="flex items-center gap-2 px-4 py-2 bg-gray-600 hover:bg-gray-700 rounded-lg font-medium transition-colors"
                        >
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <rect x="6" y="6" width="12" height="12"/>
                            </svg>
                            {{ $t('webinars.studio.end') }}
                        </button>
                    </template>
                </div>
            </div>

            <div class="flex-1 flex overflow-hidden">
                <!-- Video Preview -->
                <div class="flex-1 bg-black flex items-center justify-center">
                    <div v-if="webinar.youtube_live_id" class="w-full h-full">
                        <iframe
                            :src="`https://www.youtube.com/embed/${webinar.youtube_live_id}?autoplay=1&mute=1`"
                            class="w-full h-full"
                            frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen
                        ></iframe>
                    </div>
                    <div v-else class="text-gray-500 text-center">
                        <p class="text-lg">{{ $t('webinars.studio.video_preview') }}</p>
                        <p class="text-sm mt-2">{{ $t('webinars.studio.configure_youtube') }}</p>
                    </div>
                </div>

                <!-- Side Panel -->
                <div class="w-96 bg-gray-800 flex flex-col shrink-0">
                    <!-- Tabs -->
                    <div class="flex border-b border-gray-700">
                        <button
                            @click="activeTab = 'chat'"
                            :class="[
                                'flex-1 py-3 text-sm font-medium transition-colors flex items-center justify-center gap-1',
                                activeTab === 'chat' ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white'
                            ]"
                        >
                            💬
                        </button>
                        <button
                            @click="activeTab = 'controls'"
                            :class="[
                                'flex-1 py-3 text-sm font-medium transition-colors flex items-center justify-center gap-1',
                                activeTab === 'controls' ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white'
                            ]"
                        >
                            ⚙️
                        </button>
                        <button
                            @click="activeTab = 'products'"
                            :class="[
                                'flex-1 py-3 text-sm font-medium transition-colors flex items-center justify-center gap-1',
                                activeTab === 'products' ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white'
                            ]"
                        >
                            🛒
                        </button>
                        <button
                            @click="activeTab = 'attendees'"
                            :class="[
                                'flex-1 py-3 text-sm font-medium transition-colors flex items-center justify-center gap-1',
                                activeTab === 'attendees' ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white'
                            ]"
                        >
                            👥
                        </button>
                    </div>

                    <!-- Tab Content -->
                    <div class="flex-1 overflow-hidden">
                        <WebinarChat
                            v-if="activeTab === 'chat'"
                            :webinar-id="webinar.id"
                            :session-id="session?.id"
                            :is-host="true"
                            :initial-settings="chatSettings"
                            @settings-changed="handleSettingsChanged"
                        />

                        <div v-else-if="activeTab === 'controls'" class="p-4 overflow-y-auto h-full">
                            <ChatControlPanel
                                :webinar-id="webinar.id"
                                :initial-settings="chatSettings"
                                :pending-count="pendingCount"
                                :viewers-count="viewerCount"
                                @settings-changed="handleSettingsChanged"
                            />
                        </div>

                        <WebinarProductPanel
                            v-else-if="activeTab === 'products'"
                            :webinar-id="webinar.id"
                            :products="webinar.products"
                        />

                        <div v-else-if="activeTab === 'attendees'" class="p-4 text-white overflow-y-auto h-full">
                            <div v-if="registrations.length === 0" class="text-center text-gray-400 py-8">
                                {{ $t('webinars.studio.no_attendees') }}
                            </div>
                            <ul v-else class="space-y-2">
                                <li
                                    v-for="reg in registrations"
                                    :key="reg.id"
                                    class="flex items-center gap-3 p-2 rounded hover:bg-gray-700"
                                >
                                    <div class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-sm font-medium">
                                        {{ (reg.first_name || reg.email)[0].toUpperCase() }}
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium truncate">
                                            {{ reg.first_name || 'Anonim' }} {{ reg.last_name || '' }}
                                        </p>
                                        <p class="text-xs text-gray-400 truncate">{{ reg.email }}</p>
                                    </div>
                                    <span
                                        :class="[
                                            'text-xs px-2 py-0.5 rounded',
                                            reg.status === 'attended' ? 'bg-green-900 text-green-300' :
                                            reg.status === 'registered' ? 'bg-blue-900 text-blue-300' :
                                            'bg-gray-700 text-gray-300'
                                        ]"
                                    >
                                        {{ reg.status === 'attended' ? $t('webinars.studio.status.attended') : reg.status === 'registered' ? $t('webinars.studio.status.registered') : reg.status }}
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
