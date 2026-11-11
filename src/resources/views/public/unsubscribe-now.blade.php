@extends('public.layout')

@section('title', __('preferences.now.title'))

@section('styles')
        .question { text-align: center; font-size: 17px; color: #374151; margin-bottom: 24px; }
@endsection

@section('content')
    <div class="header">
        <h1>{{ __('preferences.now.title') }}</h1>
    </div>

    <p class="question">
        @if($list)
            {{ __('preferences.now.list', ['email' => $subscriber->email, 'list' => $list->name]) }}
        @else
            {{ __('preferences.now.all', ['email' => $subscriber->email]) }}
        @endif
    </p>

    {{-- RFC 8058 one-click request to the signed List-Unsubscribe URL (CSRF-exempt, see bootstrap/app.php) --}}
    <form method="POST" action="{{ $action }}">
        <input type="hidden" name="List-Unsubscribe" value="One-Click">
        <button type="submit" class="btn btn-danger btn-block">{{ __('preferences.now.button') }}</button>
    </form>
    <p class="info-text">{{ __('preferences.now.note') }}</p>
@endsection
