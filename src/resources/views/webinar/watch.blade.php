<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $webinar->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @vite(['resources/js/app.js'])
    <style>
        /* Hide default video controls */
        video::-webkit-media-controls {
            display: none !important;
        }
        video::-webkit-media-controls-enclosure {
            display: none !important;
        }
        video::-webkit-media-controls-panel {
            display: none !important;
        }
        video::-moz-range-progress {
            display: none !important;
        }
        video::-ms-fill-upper {
            display: none !important;
        }

        /* Disable pointer events on video */
        .video-container video {
            pointer-events: none;
        }

        /* Hide YouTube iframe controls by overlay */
        .youtube-overlay {
            position: absolute;
            inset: 0;
            z-index: 10;
            cursor: default;
        }

        /* Countdown styling */
        .countdown-number {
            font-variant-numeric: tabular-nums;
        }

        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 20px rgba(99, 102, 241, 0.5); }
            50% { box-shadow: 0 0 40px rgba(99, 102, 241, 0.8); }
        }

        .countdown-card {
            animation: pulse-glow 2s ease-in-out infinite;
        }
    </style>
</head>
<body class="min-h-screen bg-gray-900 text-white">
    <div class="flex flex-col lg:flex-row h-screen">
        <!-- Video Player -->
        <div class="flex-1 bg-black flex flex-col">
            <!-- Video Area -->
            <div class="flex-1 relative video-container">
                @if($sessionEnded ?? false)
                    <!-- Session Ended View -->
                    <div class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900">
                        <div class="text-center max-w-lg p-8">
                            @if($webinar->thumbnail_url)
                                <img src="{{ $webinar->thumbnail_url }}" alt="{{ $webinar->name }}" class="mx-auto rounded-xl shadow-2xl mb-8 max-w-sm w-full opacity-60">
                            @else
                                <div class="mb-8">
                                    <svg class="w-24 h-24 mx-auto text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                            @endif

                            <h2 class="text-2xl md:text-3xl font-bold text-white mb-4">{{ __('webinars.public.watch.session_ended') }}</h2>
                            <p class="text-gray-400 mb-8">{{ __('webinars.public.watch.session_ended_desc') }}</p>

                            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                                @if($hasReplay ?? false)
                                    <a href="{{ route('webinar.replay', ['slug' => $webinar->slug, 'token' => $registration->access_token]) }}"
                                       class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 rounded-lg font-medium transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        {{ __('webinars.public.watch.watch_replay') }}
                                    </a>
                                @endif

                                <a href="{{ route('webinar.register', $webinar->slug) }}"
                                   class="inline-flex items-center justify-center gap-2 px-6 py-3 bg-white/10 hover:bg-white/20 border border-white/20 rounded-lg font-medium transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    {{ __('webinars.public.watch.register_another_time') }}
                                </a>
                            </div>
                        </div>
                    </div>
                @elseif(!$shouldPlay && $sessionStartTime)
                    <!-- Countdown Timer -->
                    <div id="countdown-container" class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-gray-900 via-indigo-900 to-purple-900">
                        <div class="text-center p-8">
                            @if($webinar->thumbnail_url)
                                <img src="{{ $webinar->thumbnail_url }}" alt="{{ $webinar->name }}" class="mx-auto rounded-xl shadow-2xl mb-8 max-w-md w-full opacity-80">
                            @endif
                            <h2 class="text-3xl md:text-4xl font-bold mb-4">{{ $webinar->name }}</h2>
                            <p class="text-indigo-300 text-lg mb-8">{{ __('webinars.public.watch.starts_in') }}</p>

                            <div class="flex justify-center gap-4 md:gap-6 mb-8">
                                <div class="countdown-card bg-white/10 backdrop-blur-lg rounded-2xl p-4 md:p-6 min-w-[80px] md:min-w-[100px]">
                                    <div id="countdown-days" class="countdown-number text-3xl md:text-5xl font-bold text-white">00</div>
                                    <div class="text-indigo-300 text-sm uppercase tracking-wide mt-2">{{ __('webinars.public.watch.countdown.days') }}</div>
                                </div>
                                <div class="countdown-card bg-white/10 backdrop-blur-lg rounded-2xl p-4 md:p-6 min-w-[80px] md:min-w-[100px]">
                                    <div id="countdown-hours" class="countdown-number text-3xl md:text-5xl font-bold text-white">00</div>
                                    <div class="text-indigo-300 text-sm uppercase tracking-wide mt-2">{{ __('webinars.public.watch.countdown.hours') }}</div>
                                </div>
                                <div class="countdown-card bg-white/10 backdrop-blur-lg rounded-2xl p-4 md:p-6 min-w-[80px] md:min-w-[100px]">
                                    <div id="countdown-minutes" class="countdown-number text-3xl md:text-5xl font-bold text-white">00</div>
                                    <div class="text-indigo-300 text-sm uppercase tracking-wide mt-2">{{ __('webinars.public.watch.countdown.minutes') }}</div>
                                </div>
                                <div class="countdown-card bg-white/10 backdrop-blur-lg rounded-2xl p-4 md:p-6 min-w-[80px] md:min-w-[100px]">
                                    <div id="countdown-seconds" class="countdown-number text-3xl md:text-5xl font-bold text-white">00</div>
                                    <div class="text-indigo-300 text-sm uppercase tracking-wide mt-2">{{ __('webinars.public.watch.countdown.seconds') }}</div>
                                </div>
                            </div>

                            <p class="text-gray-400 text-sm">
                                {{ __('webinars.public.watch.session_starts_at') }}
                                <span id="session-start-local" class="font-semibold text-white">{{ \Carbon\Carbon::parse($sessionStartTime)->format('d.m.Y H:i') }}</span>
                                <span class="text-indigo-300 text-xs">({{ $registrationTimezone }})</span>
                            </p>
                        </div>
                    </div>
                @endif

                <div id="video-player-container" class="absolute inset-0 {{ (!$shouldPlay && $sessionStartTime) || ($sessionEnded ?? false) ? 'hidden' : '' }}">
                    @if($webinar->youtube_live_id)
                        <div class="relative w-full h-full">
                            <div class="youtube-overlay" onclick="return false;"></div>
                            <iframe
                                id="youtube-player"
                                src="https://www.youtube.com/embed/{{ $webinar->youtube_live_id }}?autoplay=1&controls=0&disablekb=1&modestbranding=1&rel=0&showinfo=0"
                                class="absolute inset-0 w-full h-full"
                                frameborder="0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen
                            ></iframe>
                        </div>
                    @elseif($webinar->video_url)
                        <video
                            id="webinar-video"
                            class="absolute inset-0 w-full h-full"
                            autoplay
                            playsinline
                            disablepictureinpicture
                            controlslist="nodownload nofullscreen noremoteplayback"
                        >
                            <source src="{{ $webinar->video_url }}" type="video/mp4">
                            Twoja przeglądarka nie obsługuje wideo.
                        </video>
                    @else
                        <div class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900">
                            <div class="text-center max-w-md p-8">
                                <div class="mb-6">
                                    <svg class="w-20 h-20 mx-auto text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                </div>
                                <h3 class="text-xl font-semibold text-white mb-2">{{ __('webinars.public.watch.not_started_yet') }}</h3>
                                <p class="text-gray-400">{{ __('webinars.public.watch.not_started_yet_desc') }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Pinned Product (overlay) -->
            @if($pinnedProduct && $shouldPlay)
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

    <script>
        // Session start time from server
        const sessionStartTime = @json($sessionStartTime);
        const shouldPlayInitially = @json($shouldPlay);

        // Countdown timer logic
        function updateCountdown() {
            if (!sessionStartTime) return;

            const now = new Date();
            const target = new Date(sessionStartTime);
            const diff = target - now;

            if (diff <= 0) {
                // Time to start!
                startWebinar();
                return;
            }

            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);

            document.getElementById('countdown-days').textContent = String(days).padStart(2, '0');
            document.getElementById('countdown-hours').textContent = String(hours).padStart(2, '0');
            document.getElementById('countdown-minutes').textContent = String(minutes).padStart(2, '0');
            document.getElementById('countdown-seconds').textContent = String(seconds).padStart(2, '0');
        }

        function startWebinar() {
            const countdownContainer = document.getElementById('countdown-container');
            const videoContainer = document.getElementById('video-player-container');

            if (countdownContainer) {
                countdownContainer.classList.add('hidden');
            }
            if (videoContainer) {
                videoContainer.classList.remove('hidden');
            }

            // Start video playback
            const video = document.getElementById('webinar-video');
            if (video) {
                video.play().catch(e => console.log('Autoplay blocked:', e));
            }

            // Reload page to get live session data
            setTimeout(() => window.location.reload(), 1000);
        }

        // Start countdown if not playing yet
        if (!shouldPlayInitially && sessionStartTime) {
            updateCountdown();
            setInterval(updateCountdown, 1000);
        }

        // Block right-click context menu on video
        document.addEventListener('contextmenu', function(e) {
            if (e.target.tagName === 'VIDEO') {
                e.preventDefault();
            }
        });

        // Block keyboard controls on video
        document.addEventListener('keydown', function(e) {
            const video = document.getElementById('webinar-video');
            if (!video) return;

            // Block space, arrow keys, etc. when video has focus
            if (document.activeElement === video || e.target === video) {
                if ([' ', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'k', 'm', 'f'].includes(e.key)) {
                    e.preventDefault();
                }
            }
        });

        // Leave tracking
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
