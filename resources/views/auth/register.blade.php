<x-guest-layout>
    <div class="space-y-6">
        <div class="text-center">
            <p class="text-sm font-semibold uppercase tracking-[0.3em] text-cyan-300">Create account</p>
            <h2 class="mt-2 text-3xl font-semibold text-white">Join the lending platform</h2>
            <p class="mt-2 text-sm text-slate-400">Set up your secure workspace and start managing applications faster.</p>
        </div>

        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf

            <div>
                <label for="name" class="mb-2 block text-sm font-medium text-slate-300">Full name</label>
                <x-text-input id="name" class="mt-1 block w-full rounded-2xl border border-white/10 bg-slate-950/80 px-4 py-3 text-sm text-white placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <label for="email" class="mb-2 block text-sm font-medium text-slate-300">Email address</label>
                <x-text-input id="email" class="mt-1 block w-full rounded-2xl border border-white/10 bg-slate-950/80 px-4 py-3 text-sm text-white placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400" type="email" name="email" :value="old('email')" required autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <label for="password" class="mb-2 block text-sm font-medium text-slate-300">Password</label>
                <x-text-input id="password" class="mt-1 block w-full rounded-2xl border border-white/10 bg-slate-950/80 px-4 py-3 text-sm text-white placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400" type="password" name="password" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <label for="password_confirmation" class="mb-2 block text-sm font-medium text-slate-300">Confirm password</label>
                <x-text-input id="password_confirmation" class="mt-1 block w-full rounded-2xl border border-white/10 bg-slate-950/80 px-4 py-3 text-sm text-white placeholder:text-slate-500 focus:border-cyan-400 focus:ring-cyan-400" type="password" name="password_confirmation" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            <button type="submit" class="flex w-full items-center justify-center rounded-full bg-cyan-500 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-400">
                {{ __('Register') }}
            </button>

            <div class="text-center text-sm text-slate-400">
                Already registered?
                <a class="ml-1 font-medium text-cyan-300 transition hover:text-cyan-200" href="{{ route('login') }}">
                    Log in instead
                </a>
            </div>
        </form>
    </div>
</x-guest-layout>
