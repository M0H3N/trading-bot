@extends('layouts.app')

@section('title', $project['title'])

@section('content')
    <article class="mx-auto w-full max-w-4xl px-6 pb-16 pt-12">
        <nav aria-label="Breadcrumb" class="mb-6 text-sm text-slate-400">
            <a href="{{ route('charity.index') }}" class="transition hover:text-cyan-300">
                {{ app()->getLocale() === 'ar' ? '→' : '←' }} {{ __('site.back_home') }}
            </a>
        </nav>

        <header class="overflow-hidden rounded-3xl border border-slate-800 bg-slate-900/80 shadow-xl">
            <img
                src="{{ $project['image'] }}"
                alt="{{ $project['title'] }}"
                class="h-[18rem] w-full object-cover sm:h-[22rem]"
                loading="eager"
                fetchpriority="high"
            >
            <div class="p-8">
                <p class="inline-flex rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-cyan-200">
                    {{ __('site.project') }}
                </p>
                <h1 class="mt-4 text-3xl font-bold tracking-tight text-white md:text-4xl">
                    {{ $project['title'] }}
                </h1>
                @php
                    $progress = min(100, round(($project['funding_raised'] / max($project['funding_goal'], 1)) * 100));
                @endphp
                <div class="mt-6 rounded-2xl border border-emerald-400/20 bg-emerald-400/10 p-5">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-200">{{ __('site.funding_progress') }}</p>
                            <p class="mt-1 text-sm text-slate-400">
                                {{ __('site.raised_of_goal', ['raised' => '$'.number_format($project['funding_raised']), 'goal' => '$'.number_format($project['funding_goal'])]) }}
                            </p>
                        </div>
                        <span class="rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1 text-sm font-bold text-emerald-200">
                            {{ $progress }}%
                        </span>
                    </div>
                    <div class="h-4 overflow-hidden rounded-full bg-slate-950/70">
                        <div class="h-full rounded-full bg-emerald-400 transition-all duration-700" style="width: {{ $progress }}%"></div>
                    </div>
                </div>
                <div
                    class="deadline-countdown mt-6 rounded-2xl border border-cyan-400/20 bg-cyan-400/10 p-5"
                    data-deadline="{{ $project['deadline'] }}"
                    data-active-status="{{ __('site.deadline_active') }}"
                    data-ended-status="{{ __('site.deadline_ended') }}"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-cyan-200">{{ __('site.project_deadline') }}</p>
                            <p class="deadline-status mt-1 text-sm text-slate-400"></p>
                        </div>
                        <time datetime="{{ $project['deadline'] }}" class="text-sm text-slate-300">
                            {{ \Illuminate\Support\Carbon::parse($project['deadline'])->format('Y-m-d') }}
                        </time>
                    </div>
                    <div class="mt-4 grid grid-cols-4 gap-3 text-center">
                        <div class="rounded-xl bg-slate-950/60 px-3 py-3">
                            <span class="block text-2xl font-bold text-white" data-countdown-days>00</span>
                            <span class="text-xs uppercase tracking-wide text-slate-400">{{ __('site.days') }}</span>
                        </div>
                        <div class="rounded-xl bg-slate-950/60 px-3 py-3">
                            <span class="block text-2xl font-bold text-white" data-countdown-hours>00</span>
                            <span class="text-xs uppercase tracking-wide text-slate-400">{{ __('site.hours') }}</span>
                        </div>
                        <div class="rounded-xl bg-slate-950/60 px-3 py-3">
                            <span class="block text-2xl font-bold text-white" data-countdown-minutes>00</span>
                            <span class="text-xs uppercase tracking-wide text-slate-400">{{ __('site.minutes') }}</span>
                        </div>
                        <div class="rounded-xl bg-slate-950/60 px-3 py-3">
                            <span class="block text-2xl font-bold text-white" data-countdown-seconds>00</span>
                            <span class="text-xs uppercase tracking-wide text-slate-400">{{ __('site.seconds') }}</span>
                        </div>
                    </div>
                </div>
                <div class="mt-6 space-y-4 text-base leading-7 text-slate-300">
                    @foreach (explode("\n\n", trim($project['body'])) as $paragraph)
                        @if (filled($paragraph))
                            <p>{{ $paragraph }}</p>
                        @endif
                    @endforeach
                </div>

                <div class="mt-8 space-y-4">
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-cyan-300">{{ __('site.bitcoin') }}</p>
                        <p class="break-all rounded-xl border border-slate-700 bg-slate-950/70 px-4 py-3 font-mono text-sm text-slate-200">
                            {{ $project['wallets']['btc'] }}
                        </p>
                        <button
                            type="button"
                            class="copy-btn mt-3 inline-flex w-full items-center justify-center rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm font-medium text-slate-100 transition duration-200 hover:border-cyan-400/60 hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-cyan-400/50 sm:w-auto"
                            data-address="{{ $project['wallets']['btc'] }}"
                        >
                            {{ __('site.copy_btc') }}
                        </button>
                    </div>

                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-cyan-300">{{ __('site.ethereum') }}</p>
                        <p class="break-all rounded-xl border border-slate-700 bg-slate-950/70 px-4 py-3 font-mono text-sm text-slate-200">
                            {{ $project['wallets']['eth'] }}
                        </p>
                        <button
                            type="button"
                            class="copy-btn mt-3 inline-flex w-full items-center justify-center rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm font-medium text-slate-100 transition duration-200 hover:border-cyan-400/60 hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-cyan-400/50 sm:w-auto"
                            data-address="{{ $project['wallets']['eth'] }}"
                        >
                            {{ __('site.copy_eth') }}
                        </button>
                    </div>
                </div>
            </div>
        </header>
    </article>
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
