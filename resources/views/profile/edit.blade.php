<x-app-layout>
    <div class="space-y-6">
        <x-page-header eyebrow="Dashboard / Profile" title="{{ __('Profile') }}" subtitle="Keep your details and password up to date." />

        <div class="max-w-3xl space-y-6">
            <x-card>
                @include('profile.partials.update-profile-information-form')
            </x-card>

            <x-card>
                @include('profile.partials.update-password-form')
            </x-card>

            <x-card>
                @include('profile.partials.delete-user-form')
            </x-card>

            {{-- Phone only: the sidebar holds these from md up --}}
            <div class="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 md:hidden">
                <h2 class="text-base font-bold text-ink">Session</h2>
                <x-secondary-button class="w-full" @click="toggle">
                    <x-icon name="moon" />
                    <span x-text="theme === 'dark' ? 'Light mode' : 'Dark mode'">Dark mode</span>
                </x-secondary-button>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-secondary-button type="submit" class="w-full">
                        <x-icon name="logout" />
                        Log out
                    </x-secondary-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
