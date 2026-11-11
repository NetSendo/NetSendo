@extends('public.layout')

@section('title', __('preferences.confirm.title'))

@section('styles')
        .change-title { font-weight: 600; color: #374151; margin: 16px 0 8px; }
        .change-list { list-style: none; margin-bottom: 8px; }
        .change-list li { padding: 10px 14px; background: #F9FAFB; border-radius: 8px; margin-bottom: 8px; }
        .btn-container { margin-top: 24px; }
@endsection

@section('content')
    <div class="header">
        <h1>{{ __('preferences.confirm.title') }}</h1>
    </div>

    <div class="email-display">{{ $subscriber->email }}</div>

    @if($rejoin->isNotEmpty())
        <p class="change-title">{{ __('preferences.confirm.rejoin') }}</p>
        <ul class="change-list">
            @foreach($rejoin as $list)
                <li>{{ $list->name }}</li>
            @endforeach
        </ul>
    @endif

    @if($leave->isNotEmpty())
        <p class="change-title">{{ __('preferences.confirm.leave') }}</p>
        <ul class="change-list">
            @foreach($leave as $list)
                <li>{{ $list->name }}</li>
            @endforeach
        </ul>
    @endif

    {{-- Opening the emailed link changes nothing; this button does (applyConfirmed) --}}
    <form method="POST" action="{{ $action }}">
        @csrf
        <div class="btn-container">
            <button type="submit" class="btn">{{ __('preferences.confirm.button') }}</button>
        </div>
    </form>
    <p class="info-text">{{ __('preferences.confirm.note') }}</p>
@endsection
