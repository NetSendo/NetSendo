<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>{{ __('webinars.public.thankyou.title') }} - {{ $webinar->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-900 text-white py-12">
    <div class="max-w-2xl mx-auto px-4 text-center">
        <div class="bg-green-500/20 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-6">
            <svg class="w-10 h-10 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>

        <h1 class="text-3xl font-bold mb-4">{{ $webinar->pageContent('purchase_thankyou_headline', __('webinars.public.thankyou.purchase_headline')) }}</h1>

        @php($purchaseMessage = $webinar->pageContent('purchase_thankyou_message', __('webinars.public.thankyou.purchase_message')))
        @if($purchaseMessage)
            <p class="text-lg opacity-80 mb-8 whitespace-pre-line">{{ $purchaseMessage }}</p>
        @endif

        <!-- Custom thank-you sections -->
        <div class="mt-12">
            @include('webinar.partials.content-sections', ['sections' => $webinar->thankYouSections()])
        </div>

        <!-- Calendly booking widget -->
        @include('webinar.partials.calendly', ['webinar' => $webinar])
    </div>
</body>
</html>
