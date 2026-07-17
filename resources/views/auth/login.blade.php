<x-guest-layout>
    <div class="space-y-6">
        <div class="text-center">
            <p class="text-sm font-semibold uppercase tracking-[0.3em] text-cyan-300">Welcome back</p>
            <h2 class="mt-2 text-3xl font-semibold text-white">Sign in to your workspace</h2>
            <p class="mt-2 text-sm text-slate-400">Continue reviewing loan applications with clarity and control.</p>
        </div>

        <x-auth-session-status class="mb-4" :status="session('status')" />

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <div>
                <label for="email" class="mb-2 block text-sm font-medium text-slate-300">Email address</label>
                <x-text-input id="email" class="mt-1 block w-full rounded-2xl border border-white/10 bg-slate-950/80 px-4 py-3 text-sm text-white placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <label for="password" class="mb-2 block text-sm font-medium text-slate-300">Password</label>
                <x-text-input id="password" class="mt-1 block w-full rounded-2xl border border-white/10 bg-slate-950/80 px-4 py-3 text-sm text-white placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400" type="password" name="password" required autocomplete="current-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="flex items-center justify-between">
                <label for="remember_me" class="inline-flex items-center">
                    <input id="remember_me" type="checkbox" class="rounded border-white/10 bg-slate-950 text-cyan-500 shadow-sm focus:ring-cyan-500" name="remember">
                    <span class="ms-2 text-sm text-slate-400">Remember me</span>
                </label>

                @if (Route::has('password.request'))
                    <a class="text-sm font-medium text-cyan-300 transition hover:text-cyan-200" href="{{ route('password.request') }}">
                        {{ __('Forgot your password?') }}
                    </a>
                @endif
            </div>

            <button type="submit" class="flex w-full items-center justify-center rounded-full bg-cyan-500 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-400">
                {{ __('Log in') }}
            </button>

            <div class="text-center text-sm text-slate-400">
                New here?
                <a class="ml-1 font-medium text-cyan-300 transition hover:text-cyan-200" href="{{ route('register') }}">
                    Create an account
                </a>
            </div>
        </form>
    </div>
</x-guest-layout>
