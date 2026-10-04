<x-guest-layout>
    <div class="space-y-6">
        <div>
            <h2 class="text-2xl font-extrabold text-ink">Create your account</h2>
            <p class="mt-1 text-sm text-body">Set up your account to start a loan application</p>
        </div>

        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf

            <div>
                <x-input-label for="name" :value="__('Full name')" />
                <x-text-input id="name" class="mt-2 block w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input id="email" class="mt-2 block w-full" type="email" name="email" :value="old('email')" placeholder="you@example.com" required autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input id="password" class="mt-2 block w-full" type="password" name="password" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password_confirmation" :value="__('Confirm password')" />
                <x-text-input id="password_confirmation" class="mt-2 block w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            <x-primary-button class="w-full">
                Create account
            </x-primary-button>

            <p class="text-center text-sm text-body">
                Already registered?
                <a class="inline-flex min-h-11 items-center font-bold text-brand hover:text-brand-dark" href="{{ route('login') }}">Sign in</a>
            </p>
        </form>
    </div>
</x-guest-layout>
