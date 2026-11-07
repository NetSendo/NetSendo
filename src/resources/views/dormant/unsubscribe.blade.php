@extends('dormant.layout')

@section('title', __('dormant.unsubscribe.title', [], $locale))

@section('content')
    @if ($action)
        <h1>{{ __('dormant.unsubscribe.title', [], $locale) }}</h1>
        <p>{{ __('dormant.unsubscribe.body', [], $locale) }}</p>
        {{-- RFC 8058 one-click request to the same signed URL (CSRF-exempt, see bootstrap/app.php) --}}
        <form method="POST" action="{{ $action }}">
            <input type="hidden" name="List-Unsubscribe" value="One-Click">
            <button type="submit">{{ __('dormant.unsubscribe.button', [], $locale) }}</button>
        </form>
    @else
        <h1>{{ __('dormant.unsubscribe.invalid_title', [], $locale) }}</h1>
        <p>{{ __('dormant.unsubscribe.invalid', [], $locale) }}</p>
    @endif
@endsection
