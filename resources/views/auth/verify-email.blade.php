<x-guest-layout>
    <div class="space-y-6">
        <div>
            <h2 class="text-2xl font-extrabold text-ink">Verify your email</h2>
            <p class="mt-1 text-sm leading-6 text-body">
                {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
            </p>
        </div>

        @if (session('status') == 'verification-link-sent')
            <div class="rounded-xl bg-green-100 px-4 py-3 text-sm font-semibold text-green-800">
                {{ __('A new verification link has been sent to the email address you provided during registration.') }}
            </div>
        @endif

        <div class="space-y-3">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf

                <x-primary-button class="w-full">
                    {{ __('Resend Verification Email') }}
                </x-primary-button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <x-secondary-button type="submit" class="w-full">
                    {{ __('Log Out') }}
                </x-secondary-button>
            </form>
        </div>
    </div>
</x-guest-layout>
