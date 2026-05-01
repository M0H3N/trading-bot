@extends('layouts.app')

@section('title', __('site.title'))

@section('content')
    <section class="mx-auto w-full max-w-6xl px-6 pb-6 pt-12 md:pt-16">
        <div class="rounded-3xl border border-slate-800 bg-gradient-to-br from-slate-900 via-slate-900 to-slate-950 p-8 shadow-2xl shadow-cyan-900/20 md:p-12">
            <p class="mb-3 inline-flex rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-cyan-200">
                {{ __('site.hero_badge') }}
            </p>
            <h1 class="text-3xl font-bold tracking-tight text-white md:text-4xl">
                {{ __('site.title') }}
            </h1>
            <p class="mt-5 max-w-3xl text-base leading-7 text-slate-300 md:text-lg">
                {{ __('site.hero_description') }}
            </p>
        </div>
    </section>

    <section class="mx-auto w-full max-w-6xl px-6 pt-12">
        <h2 class="mb-6 text-2xl font-semibold text-white">{{ __('site.projects_heading') }}</h2>
        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($projects as $project)
                <article class="group overflow-hidden rounded-2xl border border-slate-800 bg-slate-900/80 shadow-lg shadow-black/20 transition duration-200 hover:-translate-y-1 hover:border-cyan-400/50 hover:shadow-cyan-900/30">
                    <a href="{{ route('projects.show', ['slug' => $project['slug']]) }}" class="block outline-none ring-cyan-400 focus-visible:ring-2">
                        <img
                            src="{{ $project['image'] }}"
                            alt="{{ $project['title'] }}"
                            class="h-44 w-full object-cover"
                            loading="lazy"
                        >
                        <div class="p-5">
                            <h3 class="text-lg font-semibold text-white">{{ $project['title'] }}</h3>
                            <p class="mt-3 text-sm leading-6 text-slate-300">{{ $project['summary'] }}</p>
                            @php
                                $progress = min(100, round(($project['funding_raised'] / max($project['funding_goal'], 1)) * 100));
                            @endphp
                            <div class="mt-4 rounded-xl border border-emerald-400/20 bg-emerald-400/10 p-3">
                                <div class="mb-2 flex items-center justify-between gap-3 text-xs">
                                    <span class="font-semibold uppercase tracking-wide text-emerald-200">{{ __('site.progress') }}</span>
                                    <span class="font-bold text-white">{{ $progress }}%</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-950/70">
                                    <div class="h-full rounded-full bg-emerald-400 transition-all duration-700" style="width: {{ $progress }}%"></div>
                                </div>
                                <div class="mt-2 flex items-center justify-between gap-3 text-xs text-slate-400">
                                    <span>${{ number_format($project['funding_raised']) }} {{ __('site.raised') }}</span>
                                    <span>${{ number_format($project['funding_goal']) }} {{ __('site.goal') }}</span>
                                </div>
                            </div>
                            <div
                                class="deadline-countdown mt-4 rounded-xl border border-cyan-400/20 bg-cyan-400/10 p-3"
                                data-deadline="{{ $project['deadline'] }}"
                                data-active-status="{{ __('site.deadline_active') }}"
                                data-ended-status="{{ __('site.deadline_ended') }}"
                            >
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-cyan-200">{{ __('site.deadline') }}</p>
                                <div class="grid grid-cols-4 gap-2 text-center">
                                    <div class="rounded-lg bg-slate-950/60 px-2 py-2">
                                        <span class="block text-base font-bold text-white" data-countdown-days>00</span>
                                        <span class="text-[10px] uppercase tracking-wide text-slate-400">{{ __('site.days') }}</span>
                                    </div>
                                    <div class="rounded-lg bg-slate-950/60 px-2 py-2">
                                        <span class="block text-base font-bold text-white" data-countdown-hours>00</span>
                                        <span class="text-[10px] uppercase tracking-wide text-slate-400">{{ __('site.hours') }}</span>
                                    </div>
                                    <div class="rounded-lg bg-slate-950/60 px-2 py-2">
                                        <span class="block text-base font-bold text-white" data-countdown-minutes>00</span>
                                        <span class="text-[10px] uppercase tracking-wide text-slate-400">{{ __('site.minutes') }}</span>
                                    </div>
                                    <div class="rounded-lg bg-slate-950/60 px-2 py-2">
                                        <span class="block text-base font-bold text-white" data-countdown-seconds>00</span>
                                        <span class="text-[10px] uppercase tracking-wide text-slate-400">{{ __('site.seconds') }}</span>
                                    </div>
                                </div>
                                <p class="deadline-status mt-2 text-xs text-slate-400"></p>
                            </div>
                            <p class="mt-3 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-cyan-300 transition group-hover:text-cyan-200">
                                {{ __('site.view_details') }}
                                <span aria-hidden="true">{{ app()->getLocale() === 'ar' ? '←' : '→' }}</span>
                            </p>
                        </div>
                    </a>

                    <div class="border-t border-slate-800/80 px-5 pb-5 pt-4">
                        <div class="space-y-4">
                            <div>
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-cyan-300">{{ __('site.bitcoin') }}</p>
                                <p class="break-all rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 font-mono text-xs text-slate-200">
                                    {{ $project['wallets']['btc'] }}
                                </p>
                                <button
                                    type="button"
                                    class="copy-btn mt-2 inline-flex w-full items-center justify-center rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-xs font-medium text-slate-100 transition duration-200 hover:border-cyan-400/60 hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-cyan-400/50"
                                    data-address="{{ $project['wallets']['btc'] }}"
                                >
                                    {{ __('site.copy_btc') }}
                                </button>
                            </div>

                            <div>
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-cyan-300">{{ __('site.ethereum') }}</p>
                                <p class="break-all rounded-xl border border-slate-700 bg-slate-950/70 px-3 py-2 font-mono text-xs text-slate-200">
                                    {{ $project['wallets']['eth'] }}
                                </p>
                                <button
                                    type="button"
                                    class="copy-btn mt-2 inline-flex w-full items-center justify-center rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-xs font-medium text-slate-100 transition duration-200 hover:border-cyan-400/60 hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-cyan-400/50"
                                    data-address="{{ $project['wallets']['eth'] }}"
                                >
                                    {{ __('site.copy_eth') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="mx-auto w-full max-w-6xl px-6 pt-12">
        <div class="grid gap-8 rounded-3xl border border-slate-800 bg-slate-900/70 p-8 md:grid-cols-[1.2fr_1fr] md:items-start">
            <div>
                <h2 class="text-2xl font-semibold text-white">{{ __('site.stay_updated') }}</h2>
                <p class="mt-3 max-w-xl text-slate-300">
                    {{ __('site.newsletter_description') }}
                </p>
            </div>

            <div>
                @if (session('success'))
                    <div class="mb-4 rounded-xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
                        {{ session('success') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="mb-4 rounded-xl border border-rose-400/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                        <ul class="list-disc space-y-1 pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('subscriptions.store') }}" class="space-y-4">
                    @csrf
                    <div class="hidden" aria-hidden="true">
                        <label for="website">{{ __('site.website') }}</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div>
                        <label for="email" class="mb-2 block text-sm font-medium text-slate-200">{{ __('site.email_address') }}</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            required
                            class="w-full rounded-xl border border-slate-700 bg-slate-950/70 px-4 py-3 text-slate-100 placeholder-slate-500 transition focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400/30"
                            placeholder="{{ __('site.email_placeholder') }}"
                        >
                        @error('email')
                            <p class="mt-2 text-sm text-rose-300">{{ $message }}</p>
                        @enderror
                    </div>

                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center rounded-xl bg-cyan-500 px-4 py-3 text-sm font-semibold text-slate-950 transition duration-200 hover:bg-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-300/80"
                    >
                        {{ __('site.subscribe') }}
                    </button>
                </form>
            </div>
        </div>
    </section>
@endsection

@section('scripts')
    <script>
        document.querySelectorAll('.copy-btn').forEach((button) => {
            button.addEventListener('click', async () => {
                const originalText = button.textContent;
                const address = button.dataset.address;

                if (!address) return;

                try {
                    await navigator.clipboard.writeText(address);
                    button.textContent = @json(__('site.copied'));
                } catch (error) {
                    button.textContent = @json(__('site.copy_failed'));
                }

                setTimeout(() => {
                    button.textContent = originalText;
                }, 1500);
            });
        });
    </script>
@endsection
