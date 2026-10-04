<x-guest-layout>
    <div class="space-y-6">
        <div>
            <h2 class="text-2xl font-extrabold text-ink">Welcome back</h2>
            <p class="mt-1 text-sm text-body">Sign in to continue your application</p>
        </div>

        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <div>
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input id="email" class="mt-2 block w-full" type="email" name="email" :value="old('email')" placeholder="you@example.com" required autofocus autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input id="password" class="mt-2 block w-full" type="password" name="password" placeholder="Enter your password" required autocomplete="current-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="flex items-center justify-between gap-4">
                <label for="remember_me" class="inline-flex min-h-11 items-center gap-2">
                    <input id="remember_me" type="checkbox" class="h-5 w-5 rounded border-slate-300 text-brand focus:ring-brand" name="remember">
                    <span class="text-sm text-body">Remember me</span>
                </label>

                @if (Route::has('password.request'))
                    <a class="inline-flex min-h-11 items-center text-sm font-bold text-brand hover:text-brand-dark" href="{{ route('password.request') }}">
                        Forgot password?
                    </a>
                @endif
            </div>

            <x-primary-button class="w-full">
                Sign in
            </x-primary-button>

            <p class="text-center text-sm text-body">
                New here?
                <a class="inline-flex min-h-11 items-center font-bold text-brand hover:text-brand-dark" href="{{ route('register') }}">Create an account</a>
            </p>
        </form>
    </div>
</x-guest-layout>
