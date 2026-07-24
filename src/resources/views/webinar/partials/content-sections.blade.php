{{-- Custom funnel-page sections (text / YouTube / Vimeo blocks). Expects $sections from Webinar::pageSections(). --}}
@foreach($sections as $section)
    @if($section['type'] === 'video')
        <div class="mb-8">
            @if($section['title'] !== '')
                <h3 class="text-2xl font-bold mb-4 text-center">{{ $section['title'] }}</h3>
            @endif
            <div class="relative w-full overflow-hidden rounded-2xl shadow-2xl bg-black" style="padding-top: 56.25%;">
                <iframe
                    src="{{ $section['embed_url'] }}"
                    class="absolute inset-0 w-full h-full"
                    frameborder="0"
                    allow="autoplay; fullscreen; picture-in-picture"
                    allowfullscreen
                    loading="lazy"
                ></iframe>
            </div>
        </div>
    @else
        <div class="mb-8 bg-white/10 backdrop-blur rounded-xl p-8">
            @if($section['title'] !== '')
                <h3 class="text-2xl font-bold mb-4 text-center">{{ $section['title'] }}</h3>
            @endif
            @if($section['body'] !== '')
                <div class="text-lg opacity-90 whitespace-pre-line text-left">{{ $section['body'] }}</div>
            @endif
        </div>
    @endif
@endforeach
