@extends('public.layout')

@section('title', __('preferences.title'))

@section('styles')
        .unsubscribe-all { margin-bottom: 8px; }
        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 28px 0 20px;
            color: #9CA3AF;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .divider::before, .divider::after { content: ''; flex: 1; border-top: 1px solid #E5E7EB; }
        .section-title {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .section-help { font-size: 14px; color: #6B7280; margin-bottom: 12px; }
        .lists-container { margin-bottom: 24px; }
        .list-item {
            position: relative;
            display: flex;
            align-items: flex-start;
            padding: 16px;
            background: #F9FAFB;
            border-radius: 12px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: background 0.2s, box-shadow 0.2s;
            border: 2px solid transparent;
        }
        .list-item:hover { background: #F3F4F6; }
        .list-item.selected { background: #EEF2FF; border-color: #667eea; }
        .list-item input[type="checkbox"] { position: absolute; opacity: 0; pointer-events: none; }
        .list-item input[type="checkbox"]:focus-visible + .list-checkbox { outline: 2px solid #667eea; outline-offset: 2px; }
        .list-checkbox {
            flex-shrink: 0;
            width: 24px;
            height: 24px;
            border: 2px solid #D1D5DB;
            border-radius: 6px;
            margin-right: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .list-item.selected .list-checkbox { background: #667eea; border-color: #667eea; }
        .list-checkbox svg { width: 14px; height: 14px; color: white; opacity: 0; transition: opacity 0.2s; }
        .list-item.selected .list-checkbox svg { opacity: 1; }
        .list-info { flex: 1; }
        .list-name { font-weight: 600; color: #1F2937; margin-bottom: 4px; }
        .list-description { font-size: 14px; color: #6B7280; }
        .list-left { font-size: 13px; color: #9CA3AF; margin-top: 4px; }
        .no-lists { text-align: center; padding: 16px; color: #6B7280; }
        .language-select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            font-size: 15px;
            color: #374151;
            background: #F9FAFB;
        }
        .language-help { font-size: 12px; color: #9CA3AF; margin-top: 8px; }
        .delete-section { margin-top: 32px; padding-top: 24px; border-top: 1px solid #E5E7EB; text-align: center; }
        .delete-text { font-size: 12px; color: #9CA3AF; }
        .delete-link {
            background: none;
            border: none;
            color: #EF4444;
            font-size: 12px;
            cursor: pointer;
            text-decoration: underline;
            padding: 0;
        }
        .delete-link:hover { color: #DC2626; }
@endsection

@section('content')
    <div class="header">
        <div class="header-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
        </div>
        <h1>{{ __('preferences.title') }}</h1>
        <p class="subtitle">{{ __('preferences.subtitle') }}</p>
    </div>

    <div class="email-display">{{ $subscriber->email }}</div>

    @if($errors->any())
        <div class="error-message">{{ $errors->first() }}</div>
    @endif

    {{-- Every list of the subscriber, private ones too: the RFC 8058 one-click request
         to the account-wide URL (UnsubscribeController::globalOneClick, CSRF-exempt) --}}
    <div class="unsubscribe-all">
        @if($activeListCount > 0)
            <form method="POST" action="{{ $unsubscribeAllUrl }}" id="unsubscribeAllForm">
                <input type="hidden" name="List-Unsubscribe" value="One-Click">
                <button type="submit" class="btn btn-danger btn-block">{{ __('preferences.unsubscribe_all') }}</button>
            </form>
            <p class="info-text">{{ __('preferences.unsubscribe_all_note') }}</p>
        @else
            <p class="info-text">{{ __('preferences.no_active_lists') }}</p>
        @endif
    </div>

    @if($lists->isNotEmpty() || !empty($availableLanguages))
        <div class="divider">{{ __('preferences.or') }}</div>

        <form method="POST" action="{{ route('subscriber.preferences.update', $subscriber) }}" id="preferencesForm">
            @csrf
            <input type="hidden" name="signed_url" value="{{ $signedUrl }}">

            @if($lists->isNotEmpty())
                <div class="lists-container">
                    <div class="section-title">{{ __('preferences.lists_title') }}</div>
                    <p class="section-help">{{ __('preferences.lists_help') }}</p>

                    @foreach($lists as $list)
                        @php($isSubscribed = in_array($list->id, $subscribedListIds, true))
                        <label class="list-item {{ $isSubscribed ? 'selected' : '' }}" data-list-id="{{ $list->id }}">
                            <input type="checkbox" name="lists[]" value="{{ $list->id }}" {{ $isSubscribed ? 'checked' : '' }}>
                            <div class="list-checkbox">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <div class="list-info">
                                <div class="list-name">{{ $list->name }}</div>
                                @if($list->description)
                                    <div class="list-description">{{ $list->description }}</div>
                                @endif
                                @unless($isSubscribed)
                                    <div class="list-left">{{ __('preferences.left_hint') }}</div>
                                @endunless
                            </div>
                        </label>
                    @endforeach
                </div>
            @endif

            @if(!empty($availableLanguages))
                <div class="lists-container">
                    <label class="section-title" for="language">{{ __('preferences.language_title') }}</label>
                    <select name="language" id="language" class="language-select">
                        <option value="">{{ __('preferences.language_default') }}</option>
                        @foreach($availableLanguages as $code => $name)
                            <option value="{{ $code }}" {{ $currentLanguage === $code ? 'selected' : '' }}>
                                {{ $name }} ({{ strtoupper($code) }})
                            </option>
                        @endforeach
                    </select>
                    <p class="language-help">{{ __('preferences.language_help') }}</p>
                </div>
            @endif

            <div class="btn-container">
                <button type="submit" class="btn">{{ __('preferences.save') }}</button>
            </div>
        </form>
    @endif

    <!-- GDPR: Right to be Forgotten -->
    <div class="delete-section">
        <form method="POST" action="{{ route('subscriber.data.delete', $subscriber) }}" id="deleteForm" onsubmit="return confirm(@js(__('preferences.delete_confirm')))">
            @csrf
            <input type="hidden" name="signed_url" value="{{ $signedUrl }}">
            <p class="delete-text">
                {{ __('preferences.delete_text') }}
                <button type="submit" class="delete-link">{{ __('preferences.delete_button') }}</button>
            </p>
        </form>
    </div>
@endsection

@section('scripts')
    <script>
        // Toggle selected class when a list's checkbox changes
        document.querySelectorAll('.list-item').forEach(item => {
            const checkbox = item.querySelector('input[type="checkbox"]');

            checkbox.addEventListener('change', () => {
                item.classList.toggle('selected', checkbox.checked);
            });
        });
    </script>
@endsection
