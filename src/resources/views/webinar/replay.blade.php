<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('webinars.public.watch.watch_replay') }} — {{ $webinar->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    @vite(['resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-900 text-white">
    <div class="max-w-5xl mx-auto px-4 py-8">
        <header class="mb-6">
            <span class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-indigo-300 mb-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ __('webinars.public.watch.watch_replay') }}
            </span>
            <h1 class="text-2xl md:text-3xl font-bold">{{ $webinar->name }}</h1>
            @if($webinar->description)
                <p class="text-gray-400 mt-2">{{ $webinar->description }}</p>
            @endif
        </header>

        <!-- Replay player: full controls, plays from the start -->
        <div class="relative w-full aspect-video bg-black rounded-xl overflow-hidden shadow-2xl">
            @if(!empty($webinar->vimeo_id))
                <iframe
                    src="{{ $webinar->vimeoEmbedUrl(['title' => 0, 'byline' => 0, 'portrait' => 0, 'dnt' => 1]) }}"
                    class="absolute inset-0 w-full h-full"
                    frameborder="0"
                    allow="autoplay; fullscreen; picture-in-picture"
                    allowfullscreen
                ></iframe>
            @elseif(!empty($webinar->video_url))
                <video
                    class="absolute inset-0 w-full h-full"
                    controls
                    playsinline
                    controlslist="nodownload"
                >
                    <source src="{{ $webinar->video_url }}" type="video/mp4">
                    {{ __('webinars.public.watch.not_started_yet_desc') }}
                </video>
            @elseif(!empty($webinar->youtube_live_id))
                <iframe
                    src="https://www.youtube.com/embed/{{ $webinar->youtube_live_id }}?rel=0&modestbranding=1"
                    class="absolute inset-0 w-full h-full"
                    frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen
                ></iframe>
            @else
                <div class="absolute inset-0 flex items-center justify-center">
                    <p class="text-gray-500">{{ __('webinars.public.watch.session_ended_desc') }}</p>
                </div>
            @endif
        </div>

        @if($products->isNotEmpty())
            <section class="mt-8">
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach($products as $product)
                        <div class="flex items-center justify-between gap-4 bg-gray-800 rounded-xl p-4">
                            <div class="flex items-center gap-4 min-w-0">
                                @if($product->image_url)
                                    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="w-14 h-14 rounded-lg object-cover shrink-0">
                                @endif
                                <div class="min-w-0">
                                    <h3 class="font-semibold truncate">{{ $product->name }}</h3>
                                    <span class="text-lg font-bold text-indigo-300">{{ $product->formatted_price }}</span>
                                </div>
                            </div>
                            @if($product->checkout_url)
                                <a href="{{ $product->checkout_url }}" target="_blank" rel="noopener"
                                   class="shrink-0 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 rounded-lg font-medium transition-colors">
                                    {{ $product->cta_text ?? __('webinars.public.watch.buy_now') }}
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    @include('webinar.builder.embed')
</body>
</html>
