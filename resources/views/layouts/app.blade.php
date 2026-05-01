<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', __('site.title'))</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
    <header class="border-b border-slate-800/80 bg-slate-900/80 backdrop-blur">
        <div class="mx-auto flex w-full max-w-6xl items-center justify-between px-6 py-4">
            <a href="{{ route('charity.index', ['locale' => app()->getLocale()]) }}" class="text-sm font-semibold tracking-wide text-white transition hover:text-cyan-300">
                {{ __('site.brand') }}
            </a>
            <div class="flex items-center gap-3">
{{--                <a href="{{ route('admin.login') }}" class="text-xs font-medium text-slate-300 transition hover:text-cyan-300">--}}
{{--                    Admin--}}
{{--                </a>--}}
                @php
                    $alternateLocale = app()->getLocale() === 'ar' ? 'en' : 'ar';
                    $routeName = request()->route()?->getName();
                    $routeParams = request()->route()?->parameters() ?? [];
                    $routeParams['locale'] = $alternateLocale;
                    $languageUrl = $routeName && ! str_starts_with($routeName, 'admin.')
                        ? route($routeName, $routeParams)
                        : route('charity.index', ['locale' => $alternateLocale]);
                @endphp
                <a href="{{ $languageUrl }}" class="text-xs font-medium text-slate-300 transition hover:text-cyan-300">
                    {{ app()->getLocale() === 'ar' ? __('site.english') : __('site.arabic') }}
                </a>
                <span class="rounded-full border border-cyan-400/40 bg-cyan-400/10 px-3 py-1 text-xs font-medium text-cyan-200">
                    {{ __('site.badge') }}
                </span>
            </div>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="mt-16 border-t border-slate-800/80">
        <div class="mx-auto w-full max-w-6xl px-6 py-6 text-center text-sm text-slate-400">
            <p>&copy; {{ date('Y') }} {{ __('site.brand') }}. {{ __('site.footer') }}</p>
        </div>
    </footer>

    <script>
        function updateCountdowns() {
            document.querySelectorAll('.deadline-countdown').forEach((countdown) => {
                const deadline = new Date(countdown.dataset.deadline);
                const diff = deadline.getTime() - Date.now();
                const remaining = Math.max(0, diff);

                const days = Math.floor(remaining / (1000 * 60 * 60 * 24));
                const hours = Math.floor((remaining / (1000 * 60 * 60)) % 24);
                const minutes = Math.floor((remaining / (1000 * 60)) % 60);
                const seconds = Math.floor((remaining / 1000) % 60);
                const pad = (value) => String(value).padStart(2, '0');

                countdown.querySelector('[data-countdown-days]').textContent = pad(days);
                countdown.querySelector('[data-countdown-hours]').textContent = pad(hours);
                countdown.querySelector('[data-countdown-minutes]').textContent = pad(minutes);
                countdown.querySelector('[data-countdown-seconds]').textContent = pad(seconds);

                const status = countdown.querySelector('.deadline-status');
                if (status) {
                    status.textContent = diff <= 0 ? countdown.dataset.endedStatus : countdown.dataset.activeStatus;
                }
            });
        }

        updateCountdowns();
        setInterval(updateCountdowns, 1000);
    </script>

    @yield('scripts')
</body>
</html>
