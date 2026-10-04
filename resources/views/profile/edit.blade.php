<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>

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
