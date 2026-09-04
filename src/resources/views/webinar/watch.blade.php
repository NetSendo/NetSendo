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
                        @include('webinar.partials.ended')
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
                                <span id="session-start-local" class="font-semibold text-white">{{ $sessionStartTimeFormatted }}</span>
                                <span class="text-indigo-300 text-xs">({{ $registrationTimezone }})</span>
                            </p>
                        </div>
                    </div>
                @endif

                @php
                    // Player selection is presence-based; the save flow (Create/Edit
                    // transform) guarantees only one source id is set per webinar.
                    $rendersVimeo = empty($webinar->youtube_live_id) && !empty($webinar->vimeo_id);
                @endphp
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
                    @elseif($rendersVimeo)
                        <div class="relative w-full h-full">
                            <div class="youtube-overlay" onclick="return false;"></div>
                            <iframe
                                id="vimeo-player"
                                src="{{ $webinar->vimeoEmbedUrl(['autoplay' => 1, 'muted' => 1, 'controls' => 0, 'title' => 0, 'byline' => 0, 'portrait' => 0, 'dnt' => 1]) }}"
                                class="absolute inset-0 w-full h-full"
                                frameborder="0"
                                allow="autoplay; fullscreen; picture-in-picture"
                                allowfullscreen
                            ></iframe>
                        </div>
                    @elseif($webinar->video_url)
                        <video
                            id="webinar-video"
                            class="absolute inset-0 w-full h-full"
                            autoplay
                            muted
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
                                <h3 class="text-xl font-semibold text-white mb-2">{{ $webinar->pageContent('waiting_title', __('webinars.public.watch.not_started_yet')) }}</h3>
                                <p class="text-gray-400">{{ $webinar->pageContent('waiting_message', __('webinars.public.watch.not_started_yet_desc')) }}</p>
                            </div>
                        </div>
                    @endif
                </div>

                @if(!($sessionEnded ?? false) && ($videoSyncEnabled ?? false))
                    <!-- Recording-ended view, revealed client-side once the synced
                         recording finishes (or a late joiner arrives past its end). -->
                    <div id="recording-ended-overlay" class="absolute inset-0 hidden items-center justify-center bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900">
                        @include('webinar.partials.ended')
                    </div>
                @endif

                @if($rendersVimeo || $webinar->video_url)
                    <!-- Unmute affordance (issue #25): browsers block autoplay WITH
                         sound, so native/Vimeo recordings start muted and this button
                         restores audio on the first click. Revealed by JS once muted
                         playback begins; hidden for the countdown/ended states. -->
                    <button id="unmute-button" type="button"
                        class="hidden absolute bottom-6 left-1/2 -translate-x-1/2 z-20 items-center gap-2 px-5 py-3 bg-white/90 text-gray-900 font-semibold rounded-full shadow-2xl hover:bg-white transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072M17.657 6.343a8 8 0 010 11.314M11 5L6 9H2v6h4l5 4V5z"/>
                        </svg>
                        {{ __('webinars.public.watch.tap_to_unmute') }}
                    </button>
                @endif
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

    @include('webinar.builder.embed')

    @if($rendersVimeo)
        <script src="https://player.vimeo.com/api/player.js"></script>
    @endif
    <script>
        // Session start time from server
        const sessionStartTime = @json($sessionStartTime);
        const shouldPlayInitially = @json($shouldPlay);
        const videoSyncEnabled = @json($videoSyncEnabled ?? false);

        // Evergreen sync: for a scheduled recording, a late joiner should resume at
        // the elapsed offset (wall-clock since the session started), not from zero.
        // Computed on the client so it stays accurate despite render/network delay.
        function currentSyncOffset() {
            if (!videoSyncEnabled || !sessionStartTime) return 0;
            return Math.max(0, Math.floor((Date.now() - new Date(sessionStartTime).getTime()) / 1000));
        }

        // Unified video tracker: abstracts the native <video> (direct URL) and the
        // Vimeo iframe (via the Vimeo Player SDK) so play/seek and attendance
        // tracking work the same for both. Live YouTube has no trackable position.
        const videoTracker = (function () {
            const nativeVideo = document.getElementById('webinar-video');
            if (nativeVideo) {
                return {
                    hasPlayer: true,
                    currentTime: () => Math.floor(nativeVideo.currentTime || 0),
                    duration: () => nativeVideo.duration || 0,
                    play: () => nativeVideo.play().catch(e => console.log('Autoplay blocked:', e)),
                    seek: (s) => new Promise((resolve) => {
                        const apply = () => { nativeVideo.currentTime = s; resolve(); };
                        // currentTime can only be set once metadata (duration) is known.
                        if (nativeVideo.readyState >= 1) apply();
                        else nativeVideo.addEventListener('loadedmetadata', apply, { once: true });
                    }),
                    getDuration: () => new Promise((resolve) => {
                        if (nativeVideo.readyState >= 1) resolve(nativeVideo.duration || 0);
                        else nativeVideo.addEventListener('loadedmetadata', () => resolve(nativeVideo.duration || 0), { once: true });
                    }),
                    onEnded: (cb) => nativeVideo.addEventListener('ended', cb),
                    setMuted: (m) => { nativeVideo.muted = m; },
                };
            }

            const vimeoIframe = document.getElementById('vimeo-player');
            if (vimeoIframe && window.Vimeo && window.Vimeo.Player) {
                const player = new window.Vimeo.Player(vimeoIframe);
                let seconds = 0;
                let duration = 0;
                player.on('timeupdate', (data) => {
                    seconds = data.seconds || 0;
                    duration = data.duration || 0;
                });
                return {
                    hasPlayer: true,
                    currentTime: () => Math.floor(seconds),
                    duration: () => duration,
                    play: () => player.play().catch(e => console.log('Autoplay blocked:', e)),
                    seek: (s) => player.setCurrentTime(s).catch(() => {}),
                    getDuration: () => player.getDuration().catch(() => 0),
                    onEnded: (cb) => player.on('ended', cb),
                    setMuted: (m) => player.setMuted(m).catch(() => {}),
                };
            }

            return {
                hasPlayer: false,
                currentTime: () => 0,
                duration: () => 0,
                play: () => {},
                seek: () => Promise.resolve(),
                getDuration: () => Promise.resolve(0),
                onEnded: () => {},
                setMuted: () => {},
            };
        })();

        // Re-sync an evergreen recording forward when the viewer has fallen behind
        // the schedule (tab throttling, buffering). Never seeks backward, and never
        // past the end of the recording.
        function resyncIfBehind() {
            if (!videoSyncEnabled || !videoTracker.hasPlayer) return;
            const expected = currentSyncOffset();
            const duration = videoTracker.duration();
            if (expected - videoTracker.currentTime() > 4 && (duration === 0 || expected < duration)) {
                videoTracker.seek(expected);
            }
        }

        // Reveal the recording-ended screen and hide the player + unmute button.
        function showRecordingEnded() {
            const overlay = document.getElementById('recording-ended-overlay');
            if (!overlay) return;
            const container = document.getElementById('video-player-container');
            if (container) container.classList.add('hidden');
            overlay.classList.remove('hidden');
            overlay.classList.add('flex');
            hideUnmute();
        }

        // Muted-autoplay unmute affordance (issue #25). Playback starts muted so
        // the browser doesn't block it; this reveals a button that restores sound.
        let unmuteWired = false;
        function revealUnmute() {
            const btn = document.getElementById('unmute-button');
            if (!btn || !videoTracker.hasPlayer) return;
            btn.classList.remove('hidden');
            btn.classList.add('flex');
            if (unmuteWired) return;
            unmuteWired = true;
            btn.addEventListener('click', function () {
                videoTracker.setMuted(false);
                hideUnmute();
            });
        }

        function hideUnmute() {
            const btn = document.getElementById('unmute-button');
            if (!btn) return;
            btn.classList.add('hidden');
            btn.classList.remove('flex');
        }

        // Resolve with a fallback if a promise doesn't settle in time, so a
        // stalled/unreachable video never blocks the playback-start decision.
        function withTimeout(promise, ms, fallback) {
            return Promise.race([
                Promise.resolve(promise),
                new Promise((resolve) => setTimeout(() => resolve(fallback), ms)),
            ]);
        }

        // A synced recording that finishes during playback ends the session.
        if (videoSyncEnabled) {
            videoTracker.onEnded(showRecordingEnded);
        }

        // Start playback when the session is already live on initial load. For an
        // evergreen recording: if the wall-clock offset is already past the end of
        // the recording, show the ended screen; otherwise seek to the offset first.
        if (shouldPlayInitially) {
            if (videoSyncEnabled) {
                withTimeout(videoTracker.getDuration(), 8000, 0).then((duration) => {
                    const offset = currentSyncOffset();
                    if (duration > 0 && offset >= duration - 1) {
                        showRecordingEnded();
                    } else if (offset > 0) {
                        videoTracker.seek(offset).then(() => videoTracker.play());
                        revealUnmute();
                    } else {
                        videoTracker.play();
                        revealUnmute();
                    }
                });
            } else {
                videoTracker.play();
                revealUnmute();
            }
        }

        // Catch up as soon as the viewer returns to a backgrounded tab.
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) resyncIfBehind();
        });

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

            // Start video playback (native <video> or Vimeo player)
            videoTracker.play();
            revealUnmute();

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
            navigator.sendBeacon('{{ route('webinar.leave', [$webinar->slug, $registration->access_token]) }}', JSON.stringify({
                video_time_seconds: videoTracker.currentTime()
            }));
        });

        // Progress tracking every 30 seconds
        setInterval(function() {
            if (!videoTracker.hasPlayer) return;

            // Keep evergreen recordings locked to the schedule clock.
            resyncIfBehind();

            const currentTime = videoTracker.currentTime();
            const duration = videoTracker.duration();

            fetch('{{ route('webinar.progress', [$webinar->slug, $registration->access_token]) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    video_time_seconds: currentTime,
                    percent: duration > 0 ? Math.round((currentTime / duration) * 100) : 0
                })
            });
        }, 30000);
    </script>
</body>
</html>
