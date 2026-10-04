@php
    $user = Auth::user();
    $isAdmin = $user->role === 'admin';

    $items = $isAdmin
        ? [
            ['label' => 'Dashboard', 'short' => 'Dashboard', 'icon' => 'home', 'href' => route('dashboard'), 'active' => request()->routeIs('dashboard', 'admin.dashboard')],
            ['label' => 'Applications', 'short' => 'Applications', 'icon' => 'list', 'href' => route('admin.applications.index'), 'active' => request()->routeIs('admin.applications.*')],
            ['label' => 'Feature importance', 'short' => 'Insights', 'icon' => 'chart', 'href' => route('admin.feature-importance'), 'active' => request()->routeIs('admin.feature-importance')],
            ['label' => 'Profile', 'short' => 'Profile', 'icon' => 'user', 'href' => route('profile.edit'), 'active' => request()->routeIs('profile.*')],
        ]
        : [
            ['label' => 'Dashboard', 'short' => 'Home', 'icon' => 'home', 'href' => route('dashboard'), 'active' => request()->routeIs('dashboard', 'applications.show', 'applications.confirmation')],
            ['label' => 'New application', 'short' => 'Apply', 'icon' => 'plus-circle', 'href' => route('applications.create'), 'active' => request()->routeIs('applications.create')],
            ['label' => 'Profile', 'short' => 'Profile', 'icon' => 'user', 'href' => route('profile.edit'), 'active' => request()->routeIs('profile.*')],
        ];

    $initials = collect(preg_split('/\s+/', trim($user->name)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

{{-- md and up: sidebar --}}
<aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col bg-brand-dark text-white md:flex">
    <a href="{{ route('dashboard') }}" class="flex items-center px-5 py-6 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-white">
        <x-brand-mark />
    </a>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3" aria-label="Main">
        @foreach ($items as $item)
            <a href="{{ $item['href'] }}" @if ($item['active']) aria-current="page" @endif
               class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-white {{ $item['active'] ? 'bg-white/15 font-bold text-white' : 'font-medium text-brand-mist hover:bg-brand hover:text-white' }}">
                <x-icon :name="$item['icon']" />
                {{ $item['label'] }}
            </a>
        @endforeach
    </nav>

    <div class="space-y-1 border-t border-white/10 p-3">
        <button type="button" @click="toggle" class="flex min-h-11 w-full items-center gap-3 rounded-xl px-3 text-sm font-medium text-brand-mist transition hover:bg-brand hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
            <x-icon name="moon" />
            <span x-text="theme === 'dark' ? 'Light mode' : 'Dark mode'">Dark mode</span>
        </button>

        <div class="flex items-center gap-3 px-3 pt-2">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white text-sm font-bold text-brand-dark">{{ $initials }}</span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-bold text-white">{{ $user->name }}</p>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="-ml-1 rounded px-1 py-1 text-sm text-brand-light transition hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
                        Log out
                    </button>
                </form>
            </div>
        </div>
    </div>
</aside>

{{-- below md: bottom tab bar --}}
<nav class="fixed inset-x-0 bottom-0 z-30 flex border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)] md:hidden" aria-label="Main">
    @foreach ($items as $item)
        <a href="{{ $item['href'] }}" @if ($item['active']) aria-current="page" @endif
           class="flex min-h-14 flex-1 flex-col items-center justify-center gap-1 px-1 text-xs focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand {{ $item['active'] ? 'font-bold text-brand' : 'font-medium text-slate-500' }}">
            <x-icon :name="$item['icon']" class="h-6 w-6" />
            {{ $item['short'] }}
        </a>
    @endforeach
</nav>
