<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Manage Your Subscriptions') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .preferences-container {
            background: white;
            padding: 48px 40px;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 640px;
            width: 100%;
        }
        .header {
            text-align: center;
            margin-bottom: 32px;
        }
        .header-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        .header-icon svg {
            width: 40px;
            height: 40px;
            color: white;
        }
        h1 {
            font-size: 28px;
            font-weight: 700;
            color: #1F2937;
            margin-bottom: 8px;
        }
        .subtitle {
            font-size: 16px;
            color: #6B7280;
        }
        .email-display {
            background: #F3F4F6;
            padding: 12px 16px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 24px;
            font-weight: 500;
            color: #374151;
        }
        .lists-container {
            margin-bottom: 24px;
        }
        .lists-title {
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .list-item {
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
        .list-item:hover {
            background: #F3F4F6;
        }
        .list-item.selected {
            background: #EEF2FF;
            border-color: #667eea;
        }
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
        .list-item.selected .list-checkbox {
            background: #667eea;
            border-color: #667eea;
        }
        .list-checkbox svg {
            width: 14px;
            height: 14px;
            color: white;
            opacity: 0;
            transition: opacity 0.2s;
        }
        .list-item.selected .list-checkbox svg {
            opacity: 1;
        }
        .list-info {
            flex: 1;
        }
        .list-name {
            font-weight: 600;
            color: #1F2937;
            margin-bottom: 4px;
        }
        .list-description {
            font-size: 14px;
            color: #6B7280;
        }
        .no-lists {
            text-align: center;
            padding: 32px;
            color: #6B7280;
        }
        .btn-container {
            text-align: center;
        }
        .btn {
            display: inline-block;
            padding: 14px 32px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .info-text {
            text-align: center;
            font-size: 14px;
            color: #9CA3AF;
            margin-top: 16px;
        }
        .error-message {
            background: #FEE2E2;
            color: #DC2626;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            text-align: center;
        }
        /* Hidden actual checkboxes */
        input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        /* GDPR Delete Section */
        .delete-section {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #E5E7EB;
            text-align: center;
        }
        .delete-text {
            font-size: 12px;
            color: #9CA3AF;
        }
        .delete-link {
            background: none;
            border: none;
            color: #EF4444;
            font-size: 12px;
            cursor: pointer;
            text-decoration: underline;
            padding: 0;
        }
        .delete-link:hover {
            color: #DC2626;
        }
    </style>
</head>
<body>
    <div class="preferences-container">
        <div class="header">
            <div class="header-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <h1>Manage Your Subscriptions</h1>
            <p class="subtitle">Choose which mailing lists you want to be subscribed to</p>
        </div>

        <div class="email-display">
            {{ $subscriber->email }}
        </div>

        @if($errors->any())
            <div class="error-message">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('subscriber.preferences.update', $subscriber) }}" id="preferencesForm">
            @csrf
            <input type="hidden" name="signed_url" value="{{ $signedUrl }}">

            <div class="lists-container">
                <!-- Language Preference -->
                @if(!empty($availableLanguages))
                <div style="margin-bottom: 24px;">
                    <div class="lists-title">Preferred Language</div>
                    <select name="language"
                            style="width: 100%; padding: 12px 16px; border: 2px solid #E5E7EB; border-radius: 12px; font-size: 15px; color: #374151; background: #F9FAFB; cursor: pointer; appearance: none; -webkit-appearance: none; background-image: url('data:image/svg+xml;utf8,<svg fill=\'%236B7280\' viewBox=\'0 0 24 24\' xmlns=\'http://www.w3.org/2000/svg\'><path d=\'M7 10l5 5 5-5z\'/></svg>'); background-repeat: no-repeat; background-position: right 12px center; background-size: 20px;">
                        <option value="">— Default —</option>
                        @foreach($availableLanguages as $code => $name)
                            <option value="{{ $code }}" {{ $currentLanguage === $code ? 'selected' : '' }}>
                                {{ $name }} ({{ strtoupper($code) }})
                            </option>
                        @endforeach
                    </select>
                    <p style="font-size: 12px; color: #9CA3AF; margin-top: 8px;">
                        Choose your preferred language for receiving emails.
                    </p>
                </div>
                @endif

                <div class="lists-title">Available Lists</div>

                @forelse($lists as $list)
                    <label class="list-item {{ in_array($list->id, $subscribedListIds) ? 'selected' : '' }}" data-list-id="{{ $list->id }}">
                        <input type="checkbox"
                               name="lists[]"
                               value="{{ $list->id }}"
                               {{ in_array($list->id, $subscribedListIds) ? 'checked' : '' }}>
                        <div class="list-checkbox">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <div class="list-info">
                            <div class="list-name">{{ $list->name }}</div>
                            @if($list->description)
                                <div class="list-description">{{ $list->description }}</div>
                            @endif
                        </div>
                    </label>
                @empty
                    <div class="no-lists">
                        <p>No public lists available.</p>
                    </div>
                @endforelse
            </div>

            @if($lists->isNotEmpty())
                <div class="btn-container">
                    <button type="submit" class="btn">Save Preferences</button>
                </div>
                <p class="info-text">
                    We'll send you a confirmation email to verify your changes.
                </p>
            @endif
        </form>

        <!-- GDPR: Right to be Forgotten -->
        <div class="delete-section">
            <form method="POST" action="{{ route('subscriber.data.delete', $subscriber) }}" id="deleteForm" onsubmit="return confirmDelete()">
                @csrf
                <input type="hidden" name="signed_url" value="{{ $signedUrl }}">
                <p class="delete-text">
                    Want to completely remove your data?
                    <button type="submit" class="delete-link">Delete all my data</button>
                </p>
            </form>
        </div>
    </div>

    <script>
        function confirmDelete() {
            return confirm('Are you sure you want to permanently delete all your data? This action cannot be undone.');
        }

        // Toggle selected class when clicking on list items
        document.querySelectorAll('.list-item').forEach(item => {
            const checkbox = item.querySelector('input[type="checkbox"]');



            checkbox.addEventListener('change', () => {
                item.classList.toggle('selected', checkbox.checked);
            });
        });
    </script>
</body>
</html>
