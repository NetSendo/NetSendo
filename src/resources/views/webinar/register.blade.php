<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $webinar->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="min-h-screen bg-gray-900 text-white">
    <div class="gradient-bg min-h-screen">
        <div class="max-w-4xl mx-auto px-4 py-12">
            <!-- Header -->
            <div class="text-center mb-12">
                @if($webinar->thumbnail_url)
                    <img src="{{ $webinar->thumbnail_url }}" alt="{{ $webinar->name }}" class="mx-auto rounded-2xl shadow-2xl mb-8 max-w-2xl w-full">
                @endif
                <h1 class="text-4xl md:text-5xl font-bold mb-4">{{ $webinar->name }}</h1>
                @if($webinar->description)
                    <p class="text-xl opacity-90 max-w-2xl mx-auto">{{ $webinar->description }}</p>
                @endif
            </div>

            <!-- Date Info -->
            @if($webinar->scheduled_at)
                <div class="bg-white/10 backdrop-blur rounded-xl p-6 mb-8 text-center">
                    <p class="text-sm uppercase tracking-wide opacity-75 mb-2">{{ __('webinars.public.register.starts_at') }}</p>
                    <p class="text-3xl font-bold">{{ $webinar->scheduled_at->format('d.m.Y') }}</p>
                    <p class="text-2xl">{{ __('webinars.public.register.at_hour') }} {{ $webinar->scheduled_at->format('H:i') }}</p>
                </div>
            @endif

            <!-- Session Times (for auto-webinars) -->
            @if($webinar->isAutoWebinar() && count($nextSessions) > 0)
                <div class="bg-white/10 backdrop-blur rounded-xl p-6 mb-8">
                    <h3 class="text-lg font-semibold mb-4 text-center">{{ __('webinars.public.register.choose_date') }}</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                        @foreach($nextSessions as $session)
                            <label class="cursor-pointer">
                                <input type="radio" name="session_time" value="{{ $session->format('Y-m-d H:i:s') }}" form="registration-form" class="sr-only peer">
                                <div class="p-3 rounded-lg bg-white/5 border-2 border-transparent peer-checked:border-white peer-checked:bg-white/20 text-center transition-all hover:bg-white/10">
                                    <p class="font-medium">{{ $session->format('d.m') }}</p>
                                    <p class="text-sm opacity-75">{{ $session->format('H:i') }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Registration Form -->
            @if($canRegister)
                <div class="bg-white rounded-2xl shadow-2xl p-8 text-gray-900">
                    <h2 class="text-2xl font-bold mb-6 text-center">{{ __('webinars.public.register.title') }}</h2>

                    @if(session('error'))
                        <div class="bg-red-100 border border-red-300 text-red-700 px-4 py-3 rounded mb-4">
                            {{ session('error') }}
                        </div>
                    @endif

                    <form id="registration-form" action="{{ route('webinar.register.submit', $webinar->slug) }}" method="POST" class="space-y-4">
                        @csrf

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">{{ __('webinars.public.register.email') }} *</label>
                            <input type="email" name="email" id="email" required
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="jan@example.com">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('webinars.public.register.first_name') }}</label>
                                <input type="text" name="first_name" id="first_name"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder="Jan">
                            </div>
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('webinars.public.register.last_name') }}</label>
                                <input type="text" name="last_name" id="last_name"
                                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder="Kowalski">
                            </div>
                        </div>

                        <!-- UTM params -->
                        <input type="hidden" name="utm_source" value="{{ request('utm_source') }}">
                        <input type="hidden" name="utm_medium" value="{{ request('utm_medium') }}">
                        <input type="hidden" name="utm_campaign" value="{{ request('utm_campaign') }}">

                        <button type="submit"
                            class="w-full py-4 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-bold rounded-lg text-lg hover:from-indigo-700 hover:to-purple-700 transition-all transform hover:scale-[1.02] shadow-lg">
                            {{ __('webinars.public.register.submit') }}
                        </button>

                        <p class="text-xs text-gray-500 text-center mt-4">
                            {{ __('webinars.public.register.consent') }}
                        </p>
                    </form>
                </div>
            @else
                <div class="bg-white/10 backdrop-blur rounded-xl p-8 text-center">
                    <p class="text-xl">{{ __('webinars.public.register.closed') }}</p>
                </div>
            @endif

            <!-- Benefits / What You'll Learn -->
            @if($webinar->settings_with_defaults['show_benefits'] ?? false)
                <div class="mt-12 bg-white/10 backdrop-blur rounded-xl p-8">
                    <h3 class="text-2xl font-bold mb-6 text-center">{{ __('webinars.public.register.benefits') }}</h3>
                    <ul class="space-y-4">
                        @foreach($webinar->settings_with_defaults['benefits'] ?? [] as $benefit)
                            <li class="flex items-start gap-3">
                                <svg class="w-6 h-6 text-green-400 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span>{{ $benefit }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</body>
</html>
