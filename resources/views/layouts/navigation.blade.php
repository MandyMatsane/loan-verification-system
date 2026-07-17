<nav x-data="{ open: false }" class="border-b border-slate-200/70 bg-white/80 text-slate-900 backdrop-blur dark:border-slate-800/70 dark:bg-slate-950/90 dark:text-slate-100">
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-4">
            <a href="{{ route('dashboard') }}" class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">
                Loan Verification <span class="text-cyan-500">System</span>
            </a>

            <div class="hidden items-center gap-2 sm:flex">
                <a href="{{ route('dashboard') }}" class="rounded-full px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 hover:text-slate-900 {{ request()->routeIs('dashboard') ? 'bg-cyan-100 text-cyan-700 dark:bg-cyan-500/10 dark:text-cyan-200' : 'dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                    Dashboard
                </a>
                <a href="{{ url('/') }}" class="rounded-full px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">
                    Home
                </a>
            </div>
        </div>

        <div class="hidden sm:flex sm:items-center sm:gap-3">
            <button @click="toggle" class="rounded-full border border-slate-200/70 bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-200 dark:border-slate-700/70 dark:bg-slate-800/70 dark:text-slate-200 dark:hover:bg-slate-700">
                <span x-text="theme === 'dark' ? 'Light mode' : 'Dark mode'"></span>
            </button>

            <div class="rounded-full border border-slate-200/70 bg-slate-100 px-3 py-2 text-sm text-slate-700 dark:border-slate-700/70 dark:bg-slate-800/70 dark:text-slate-200">
                {{ Auth::user()->name }}
            </div>

            <a href="{{ route('profile.edit') }}" class="rounded-full border border-slate-200/70 px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 hover:text-slate-900 dark:border-slate-700/70 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-white">
                Profile
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-full bg-cyan-500 px-3 py-2 text-sm font-semibold text-slate-950 transition hover:bg-cyan-400">
                    Log out
                </button>
            </form>
        </div>

        <div class="-mr-2 flex items-center sm:hidden">
            <button @click="open = ! open" class="inline-flex items-center justify-center rounded-md p-2 text-slate-300 transition hover:bg-white/10 hover:text-white">
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden border-t border-white/10 bg-slate-900/90 sm:hidden">
        <div class="space-y-1 px-4 py-3">
            <a href="{{ route('dashboard') }}" class="block rounded-xl px-3 py-2 text-sm font-medium text-slate-300 transition hover:bg-white/10 hover:text-white">
                Dashboard
            </a>
            <a href="{{ url('/') }}" class="block rounded-xl px-3 py-2 text-sm font-medium text-slate-300 transition hover:bg-white/10 hover:text-white">
                Home
            </a>
            <a href="{{ route('profile.edit') }}" class="block rounded-xl px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">
                Profile
            </a>
            <div class="mt-1 flex items-center gap-3 px-3 py-2">
                <button @click="toggle" class="w-full rounded-xl border border-slate-200/70 bg-slate-100 px-3 py-2 text-left text-sm font-medium text-slate-700 transition hover:bg-slate-200 dark:border-slate-700/70 dark:bg-slate-800/70 dark:text-slate-200 dark:hover:bg-slate-700">
                    <span x-text="theme === 'dark' ? 'Light mode' : 'Dark mode'"></span>
                </button>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="pt-1">
                @csrf
                <button type="submit" class="w-full rounded-xl bg-cyan-500 px-3 py-2 text-left text-sm font-semibold text-slate-950 transition hover:bg-cyan-400">
                    Log out
                </button>
            </form>
        </div>
    </div>
</nav>
