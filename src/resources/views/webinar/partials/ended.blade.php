{{-- Shared "session/recording ended" content. Rendered server-side when the
     session is already over, and cloned (hidden) for client-side end-of-recording
     detection in watch.blade.php. Expects: $webinar, $registration, $hasReplay. --}}
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
