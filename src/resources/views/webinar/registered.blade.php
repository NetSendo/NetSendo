<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dziękujemy! - {{ $webinar->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-900 text-white flex items-center justify-center">
    <div class="max-w-lg mx-auto px-4 text-center">
        <div class="bg-green-500/20 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-6">
            <svg class="w-10 h-10 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>

        <h1 class="text-3xl font-bold mb-4">{{ __('webinars.public.registered.success_title', ['name' => $registration->first_name ?? '']) }}</h1>

        <p class="text-lg opacity-80 mb-8">
            {{ __('webinars.public.registered.confirmation_sent') }} <strong>{{ $registration->email }}</strong>
        </p>

        <div class="bg-white/10 backdrop-blur rounded-xl p-6 mb-8">
            <p class="text-sm opacity-75 mb-2">{{ __('webinars.public.registered.starts_at') }}</p>
            @if($registration->session && $registration->session->scheduled_at)
                <p class="text-2xl font-bold">{{ $registration->session->scheduled_at->format('d.m.Y') }} o {{ $registration->session->scheduled_at->format('H:i') }}</p>
                @if($registration->timezone)
                    <p class="text-sm opacity-60 mt-1">({{ $registration->timezone }})</p>
                @endif
            @elseif($webinar->scheduled_at)
                <p class="text-2xl font-bold">{{ $webinar->scheduled_at->format('d.m.Y') }} o {{ $webinar->scheduled_at->format('H:i') }}</p>
            @else
                <p class="text-2xl font-bold">{{ __('webinars.public.registered.soon') }}</p>
            @endif
        </div>

        <div class="bg-indigo-600/30 rounded-xl p-6 mb-8">
            <p class="text-sm mb-3">{{ __('webinars.public.registered.your_link') }}</p>
            <div class="bg-white/10 rounded-lg p-3 break-all text-sm">
                {{ $registration->watch_url }}
            </div>
            <button onclick="navigator.clipboard.writeText('{{ $registration->watch_url }}')" class="mt-3 text-sm text-indigo-300 hover:text-white">
                📋 {{ __('webinars.public.registered.copy_link') }}
            </button>
        </div>

        <p class="text-sm opacity-60">
            {{ __('webinars.public.registered.add_to_calendar') }}
        </p>

        <div class="flex justify-center gap-4 mt-4">
            <a href="#" class="px-4 py-2 bg-white/10 rounded-lg text-sm hover:bg-white/20 transition">
                📅 Google Calendar
            </a>
            <a href="#" class="px-4 py-2 bg-white/10 rounded-lg text-sm hover:bg-white/20 transition">
                📅 Outlook
            </a>
        </div>
    </div>
</body>
</html>
