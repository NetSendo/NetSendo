<script setup>
import { Head, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref, onMounted, onUnmounted } from 'vue';
import WebinarChat from '@/Components/Webinar/WebinarChat.vue';
import WebinarProductPanel from '@/Components/Webinar/WebinarProductPanel.vue';

const props = defineProps({
    webinar: Object,
    session: Object,
    registrations: Array,
});

const isLive = ref(props.webinar.status === 'live');
const viewerCount = ref(props.session?.current_viewers || 0);
const activeTab = ref('chat');

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

// Echo/Reverb connection for real-time updates
let channel = null;

onMounted(() => {
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
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <span>{{ $t('webinars.studio.viewers', { count: viewerCount }) }}</span>
                    </div>

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
                                'flex-1 py-3 text-sm font-medium transition-colors flex items-center justify-center gap-2',
                                activeTab === 'chat' ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white'
                            ]"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                            {{ $t('webinars.studio.tabs.chat') }}
                        </button>
                        <button
                            @click="activeTab = 'products'"
                            :class="[
                                'flex-1 py-3 text-sm font-medium transition-colors flex items-center justify-center gap-2',
                                activeTab === 'products' ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white'
                            ]"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                            </svg>
                            {{ $t('webinars.studio.tabs.products') }}
                        </button>
                        <button
                            @click="activeTab = 'attendees'"
                            :class="[
                                'flex-1 py-3 text-sm font-medium transition-colors flex items-center justify-center gap-2',
                                activeTab === 'attendees' ? 'text-white bg-gray-700' : 'text-gray-400 hover:text-white'
                            ]"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            {{ $t('webinars.studio.tabs.attendees') }}
                        </button>
                    </div>

                    <!-- Tab Content -->
                    <div class="flex-1 overflow-hidden">
                        <WebinarChat
                            v-if="activeTab === 'chat'"
                            :webinar-id="webinar.id"
                            :session-id="session?.id"
                            :is-host="true"
                        />
                        <WebinarProductPanel
                            v-else-if="activeTab === 'products'"
                            :webinar-id="webinar.id"
                            :products="webinar.products"
                        />
                        <div v-else-if="activeTab === 'attendees'" class="p-4 text-white">
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
