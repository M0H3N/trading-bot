@extends('layouts.app')

@section('title', 'Admin Login')

@section('content')
    <section class="mx-auto flex min-h-[70vh] w-full max-w-6xl items-center justify-center px-6 py-12">
        <div class="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl">
            <h1 class="text-2xl font-semibold text-white">Admin Login</h1>
            <p class="mt-2 text-sm text-slate-400">Sign in to manage wallet balances.</p>

            @if (session('success'))
                <div class="mt-4 rounded-xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-4 rounded-xl border border-rose-400/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.store') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="email" class="mb-2 block text-sm font-medium text-slate-200">Admin Email</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        class="w-full rounded-xl border border-slate-700 bg-slate-950/70 px-4 py-3 text-slate-100 placeholder-slate-500 transition focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400/30"
                        placeholder="admin@charity.local"
                    >
                </div>

                <div>
                    <label for="password" class="mb-2 block text-sm font-medium text-slate-200">Password</label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        required
                        class="w-full rounded-xl border border-slate-700 bg-slate-950/70 px-4 py-3 text-slate-100 placeholder-slate-500 transition focus:border-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-400/30"
                        placeholder="********"
                    >
                </div>

                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center rounded-xl bg-cyan-500 px-4 py-3 text-sm font-semibold text-slate-950 transition duration-200 hover:bg-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-300/80"
                >
                    Sign In
                </button>
            </form>
        </div>
    </section>
@endsection
