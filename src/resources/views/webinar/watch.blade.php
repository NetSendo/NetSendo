<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $webinar->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @vite(['resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-900 text-white">
    <div class="flex flex-col lg:flex-row h-screen">
        <!-- Video Player -->
        <div class="flex-1 bg-black flex flex-col">
            <!-- Video Area -->
            <div class="flex-1 relative">
                @if($webinar->youtube_live_id)
                    <iframe
                        src="https://www.youtube.com/embed/{{ $webinar->youtube_live_id }}?autoplay=1"
                        class="absolute inset-0 w-full h-full"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen
                    ></iframe>
                @elseif($webinar->video_url)
                    <video
                        id="webinar-video"
                        class="absolute inset-0 w-full h-full"
                        controls
                        autoplay
                    >
                        <source src="{{ $webinar->video_url }}" type="video/mp4">
                        Twoja przeglądarka nie obsługuje wideo.
                    </video>
                @else
                    <div class="absolute inset-0 flex items-center justify-center">
                        <div class="text-center">
                            <div class="animate-pulse mb-4">
                                <svg class="w-16 h-16 mx-auto text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <p class="text-gray-400">{{ __('webinars.public.watch.waiting') }}</p>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Pinned Product (overlay) -->
            @if($pinnedProduct)
                <div id="pinned-product" class="bg-gradient-to-r from-indigo-600 to-purple-600 p-4">
                    <div class="max-w-4xl mx-auto flex items-center justify-between gap-4">
                        <div class="flex items-center gap-4">
                            @if($pinnedProduct->image_url)
                                <img src="{{ $pinnedProduct->image_url }}" alt="{{ $pinnedProduct->name }}" class="w-16 h-16 rounded-lg object-cover">
                            @endif
                            <div>
                                <h3 class="font-bold text-lg">{{ $pinnedProduct->name }}</h3>
                                <div class="flex items-center gap-2">
                                    <span class="text-2xl font-bold">{{ $pinnedProduct->formatted_price }}</span>
                                    @if($pinnedProduct->original_price)
                                        <span class="text-sm line-through opacity-60">{{ $pinnedProduct->formatted_original_price }}</span>
                                        <span class="bg-yellow-400 text-yellow-900 text-xs font-bold px-2 py-0.5 rounded">-{{ $pinnedProduct->discount_percentage }}%</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <a href="{{ $pinnedProduct->checkout_url }}" target="_blank"
                            class="px-8 py-3 bg-white text-indigo-600 font-bold rounded-lg text-lg hover:bg-gray-100 transition transform hover:scale-105 shadow-lg">
                            {{ $pinnedProduct->cta_text ?? __('webinars.public.watch.buy_now') }}
                        </a>
                    </div>
                </div>
            @endif
        </div>

        <!-- Chat Sidebar -->
        <div class="w-full lg:w-96 bg-gray-800 flex flex-col h-64 lg:h-full">
            <div class="p-4 border-b border-gray-700 flex items-center justify-between">
                <h2 class="font-semibold">{{ __('webinars.public.watch.live_chat') }}</h2>
                <span class="text-sm text-gray-400" id="viewer-count">{{ __('webinars.public.watch.viewers', ['count' => $session?->current_viewers ?? 0]) }}</span>
            </div>

            <!-- Chat Messages -->
            <div id="chat-messages" class="flex-1 overflow-y-auto p-4 space-y-3">
                <!-- Messages will be loaded via JavaScript -->
                <div class="text-center text-gray-500 py-4">
                    <p>{{ __('webinars.public.watch.loading_chat') }}</p>
                </div>
            </div>

            <!-- Chat Input -->
            <div class="p-4 border-t border-gray-700">
                <form id="chat-form" class="flex gap-2">
                    <input type="hidden" name="token" value="{{ $registration->access_token }}">
                    <input
                        type="text"
                        id="chat-input"
                        placeholder="{{ __('webinars.public.watch.type_message') }}"
                        class="flex-1 rounded-lg bg-gray-700 border-gray-600 text-white placeholder-gray-400"
                        maxlength="500"
                    >
                    <button type="submit" class="px-4 py-2 bg-indigo-600 rounded-lg hover:bg-indigo-700 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Leave tracking -->
    <script>
        window.addEventListener('beforeunload', function() {
            const video = document.getElementById('webinar-video');
            const currentTime = video ? Math.floor(video.currentTime) : 0;

            navigator.sendBeacon('{{ route('webinar.leave', [$webinar->slug, $registration->access_token]) }}', JSON.stringify({
                video_time_seconds: currentTime
            }));
        });

        // Progress tracking every 30 seconds
        setInterval(function() {
            const video = document.getElementById('webinar-video');
            if (!video) return;

            fetch('{{ route('webinar.progress', [$webinar->slug, $registration->access_token]) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    video_time_seconds: Math.floor(video.currentTime),
                    percent: Math.round((video.currentTime / video.duration) * 100)
                })
            });
        }, 30000);
    </script>
</body>
</html>
