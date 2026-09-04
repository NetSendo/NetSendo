@php
    $target = null;
    if ($props['target'] === 'custom' && trim($props['custom_at']) !== '') {
        try {
            $target = \Carbon\Carbon::parse($props['custom_at'], config('app.timezone'));
        } catch (\Throwable $e) {
            $target = null;
        }
    } else {
        $target = $ctx['start_at'] ?? $webinar->scheduled_at;
    }
    $expired = trim($props['expired_text']) !== '' ? $props['expired_text'] : __('webinars.public.register.starting_now');
@endphp
@if($target)
    <div>
        @if(trim($props['title']) !== '')
            <h3 class="wb-heading wb-heading--sm wb-align-center" style="margin-bottom: 14px;">{{ $props['title'] }}</h3>
        @endif
        <div class="wb-countdown" data-wb-countdown="{{ $target->copy()->utc()->toIso8601String() }}" data-wb-expired="{{ $expired }}">
            @foreach(['days' => __('webinars.countdown.days'), 'hours' => __('webinars.countdown.hours'), 'minutes' => __('webinars.countdown.minutes'), 'seconds' => __('webinars.countdown.seconds')] as $unit => $label)
                <div class="wb-countdown__cell">
                    <div class="wb-countdown__value" data-wb-unit="{{ $unit }}">--</div>
                    <div class="wb-countdown__label">{{ $label }}</div>
                </div>
            @endforeach
        </div>
    </div>
    <script>
        (function () {
            var nodes = document.querySelectorAll('[data-wb-countdown]:not([data-wb-ready])');
            nodes.forEach(function (node) {
                node.setAttribute('data-wb-ready', '1');
                var target = new Date(node.getAttribute('data-wb-countdown')).getTime();
                var cells = {
                    days: node.querySelector('[data-wb-unit="days"]'),
                    hours: node.querySelector('[data-wb-unit="hours"]'),
                    minutes: node.querySelector('[data-wb-unit="minutes"]'),
                    seconds: node.querySelector('[data-wb-unit="seconds"]')
                };
                var pad = function (n) { return n < 10 ? '0' + n : String(n); };
                var tick = function () {
                    var diff = target - Date.now();
                    if (diff <= 0) {
                        node.innerHTML = '<div class="wb-heading wb-heading--md">' + node.getAttribute('data-wb-expired') + '</div>';
                        clearInterval(timer);
                        return;
                    }
                    var s = Math.floor(diff / 1000);
                    cells.days.textContent = pad(Math.floor(s / 86400));
                    cells.hours.textContent = pad(Math.floor((s % 86400) / 3600));
                    cells.minutes.textContent = pad(Math.floor((s % 3600) / 60));
                    cells.seconds.textContent = pad(s % 60);
                };
                var timer = setInterval(tick, 1000);
                tick();
            });
        })();
    </script>
@endif
