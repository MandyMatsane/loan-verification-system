<x-guest-layout>
    <div class="space-y-6">
        <div>
            <h2 class="text-2xl font-extrabold text-ink">Reset your password</h2>
            <p class="mt-1 text-sm leading-6 text-body">
                {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
            </p>
        </div>

        <!-- Session Status -->
        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
            @csrf

            <!-- Email Address -->
            <div>
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input id="email" class="mt-2 block w-full" type="email" name="email" :value="old('email')" placeholder="you@example.com" required autofocus />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <x-primary-button class="w-full">
                {{ __('Email Password Reset Link') }}
            </x-primary-button>

            <p class="text-center text-sm">
                <a class="inline-flex min-h-11 items-center font-bold text-brand hover:text-brand-dark" href="{{ route('login') }}">Back to sign in</a>
            </p>
        </form>
    </div>
</x-guest-layout>
