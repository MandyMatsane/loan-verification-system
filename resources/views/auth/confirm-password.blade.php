<x-guest-layout>
    <div class="space-y-6">
        <div>
            <h2 class="text-2xl font-extrabold text-ink">Confirm your password</h2>
            <p class="mt-1 text-sm leading-6 text-body">
                {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
            </p>
        </div>

        <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
            @csrf

            <!-- Password -->
            <div>
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input id="password" class="mt-2 block w-full" type="password" name="password" required autocomplete="current-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <x-primary-button class="w-full">
                {{ __('Confirm') }}
            </x-primary-button>
        </form>
    </div>
</x-guest-layout>
