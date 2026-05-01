@extends('layouts.app')

@section('title', 'Admin Wallet Balances')

@section('content')
    <section class="mx-auto w-full max-w-4xl px-6 py-12">
        <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-white">Wallet Balances</h1>
                    <p class="mt-2 text-sm text-slate-400">Set the USD amounts shown on public donation cards.</p>
                </div>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center justify-center rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-sm font-medium text-slate-100 transition hover:border-cyan-400/60 hover:bg-slate-700"
                    >
                        Logout
                    </button>
                </form>
            </div>

            @if (session('success'))
                <div class="mt-5 rounded-xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-5 rounded-xl border border-rose-400/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.wallet-balances.update') }}" class="mt-6 space-y-4">
                @csrf
                @method('PUT')

                @foreach ($wallets as $wallet)
                    <div class="rounded-xl border border-slate-800 bg-slate-950/50 p-4">
                        <label for="balances_{{ $wallet['type'] }}" class="mb-2 block text-sm font-semibold text-cyan-300">
                            {{ $wallet['label'] }} Balance (USD)
                        </label>
                        <div class="flex items-center gap-2">
                            <span class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-slate-300">$</span>
                            <input
                                id="balances_{{ $wallet['type'] }}"
                                type="number"
                                step="0.01"
                                min="0"
                                name="balances[{{ $wallet['type'] }}]"
                                value="{{ old('balances.' . $wallet['type'], $wallet['usd_balance']) }}"
                                required
                                class="w-full rounded-xl border border-slate-700 bg-slate-950/70 px-4 py-3 text-slate-100 placeholder-slate-500 transition focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400/30"
                            >
                        </div>
                    </div>
                @endforeach

                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center rounded-xl bg-cyan-500 px-4 py-3 text-sm font-semibold text-slate-950 transition duration-200 hover:bg-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-300/80"
                >
                    Save Balances
                </button>
            </form>
        </div>
    </section>
@endsection
