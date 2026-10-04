<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Loan Verification System') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body x-data="theme()" :class="themeClass" class="min-h-screen bg-surface font-sans text-body antialiased">
        <script>if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark');</script>
        @php
            $navLink = 'inline-flex min-h-11 items-center rounded-xl px-3 text-sm font-medium transition focus:outline-none focus-visible:ring-2';
            $navButton = 'ml-2 inline-flex min-h-12 items-center justify-center rounded-xl px-5 py-2 text-sm font-bold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2';

            $solutions = [
                'Identity checks' => 'Confirm applicant identity through structured document review and verification workflow.',
                'Income validation' => 'Review earnings evidence and financial records to support responsible lending decisions.',
                'Fraud review' => 'Reduce exposure with risk signals, document consistency checks, and audit-ready review trails.',
                'Compliance' => 'Maintain oversight with transparent process tracking and structured approval documentation.',
            ];

            $process = [
                'Application intake' => 'Capture applicant details and supporting records in a secure digital workflow.',
                'Verification review' => 'Validate identity, financial credentials, and risk indicators against policy rules.',
                'Decision support' => 'Review recommended outcomes, approve confidently, and maintain a clear audit trail.',
            ];

            $pipeline = [
                'Document verification' => '96%',
                'Income validation' => 'Passed',
                'Risk review' => 'In review',
                'Compliance status' => 'Verified',
            ];
        @endphp

        {{-- Sticky header: white while it sits on a teal section, teal while it sits on the page background --}}
        <header x-data="{
                    onTeal: true,
                    navOnTeal: 'text-body hover:bg-brand-tint hover:text-ink focus-visible:ring-brand',
                    navOnPage: 'text-brand-mist hover:bg-brand hover:text-white focus-visible:ring-white',
                    buttonOnTeal: 'bg-brand text-white hover:bg-brand-dark focus-visible:ring-brand',
                    buttonOnPage: 'bg-paper text-brand-dark hover:bg-brand-mist focus-visible:ring-white focus-visible:ring-offset-brand-dark',
                    check() {
                        const y = this.$el.offsetHeight + 1;
                        this.onTeal = [...document.querySelectorAll('[data-teal]')].some((el) => {
                            const box = el.getBoundingClientRect();
                            return box.top <= y && box.bottom > y;
                        });
                    },
                }"
                x-init="check()" @scroll.window.passive="check()" @resize.window.passive="check()"
                :class="onTeal ? 'border-slate-200 bg-white' : 'border-white/10 bg-brand-dark'"
                class="sticky top-0 z-40 border-b transition-colors duration-300">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 md:px-8">
                <a href="/" class="inline-flex items-center gap-3 rounded-xl focus:outline-none focus-visible:ring-2" :class="onTeal ? 'focus-visible:ring-brand' : 'focus-visible:ring-white'">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand text-white">
                        <x-icon name="check" class="h-5 w-5" />
                    </span>
                    <span class="whitespace-nowrap leading-tight">
                        <span class="block text-sm font-bold transition-colors duration-300" :class="onTeal ? 'text-ink' : 'text-white'">Loan Verification</span>
                        <span class="block text-sm transition-colors duration-300" :class="onTeal ? 'text-body' : 'text-brand-light'">System</span>
                    </span>
                </a>

                @if (Route::has('login'))
                    <nav class="flex items-center gap-1" aria-label="Main">
                        <a href="#solutions" class="{{ $navLink }} hidden md:inline-flex" :class="onTeal ? navOnTeal : navOnPage">Solutions</a>
                        <a href="#process" class="{{ $navLink }} hidden md:inline-flex" :class="onTeal ? navOnTeal : navOnPage">Process</a>
                        <button type="button" @click="toggle" class="{{ $navLink }} gap-2" :class="onTeal ? navOnTeal : navOnPage" :aria-label="theme === 'dark' ? 'Light mode' : 'Dark mode'">
                            <x-icon name="moon" x-show="theme !== 'dark'" />
                            <x-icon name="sun" x-show="theme === 'dark'" x-cloak />
                            <span class="hidden md:inline" x-text="theme === 'dark' ? 'Light mode' : 'Dark mode'">Dark mode</span>
                        </button>
                        @auth
                            <a href="{{ url('/dashboard') }}" class="{{ $navButton }}" :class="onTeal ? buttonOnTeal : buttonOnPage">Dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="{{ $navLink }}" :class="onTeal ? navOnTeal : navOnPage">Sign in</a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="{{ $navButton }} hidden md:inline-flex" :class="onTeal ? buttonOnTeal : buttonOnPage">Create account</a>
                            @endif
                        @endauth
                    </nav>
                @endif
            </div>
        </header>

        <div class="bg-brand-dark text-white" data-teal>

            <section class="mx-auto grid max-w-6xl gap-8 px-4 pb-12 pt-6 md:px-8 md:pb-16 md:pt-10 lg:grid-cols-5 lg:items-center">
                <div class="lg:col-span-3">
                    <p class="text-sm font-semibold text-brand-light">Loan verification platform</p>
                    <h1 class="mt-3 max-w-xl text-3xl font-extrabold leading-tight text-white md:text-5xl">
                        Verify every loan with clarity, speed, and confidence.
                    </h1>
                    <p class="mt-4 max-w-xl text-base leading-7 text-brand-mist">
                        Streamline applicant review, validate identity and income, and surface risk indicators in a secure workflow built for lending teams.
                    </p>
                    <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                        @if (Route::has('register'))
                            <x-button-link variant="white" href="{{ route('register') }}">Start verification</x-button-link>
                        @endif
                        <a href="{{ route('login') }}" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-white px-5 py-2 text-sm font-bold text-white transition hover:bg-brand focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
                            View dashboard
                        </a>
                    </div>
                </div>

                <div class="rounded-2xl bg-white p-5 lg:col-span-2">
                    <p class="text-sm font-bold text-ink">Application pipeline</p>
                    <ul class="mt-3 divide-y divide-slate-200">
                        @foreach ($pipeline as $label => $value)
                            <li class="flex items-center justify-between gap-4 py-3 text-sm">
                                <span class="text-body">{{ $label }}</span>
                                <span class="font-bold text-ink">{{ $value }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        </div>

        <main class="mx-auto max-w-6xl space-y-12 px-4 py-12 md:px-8">
            <section id="solutions" class="scroll-mt-24">
                <h2 class="text-2xl font-extrabold text-ink">Solutions</h2>
                <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    @foreach ($solutions as $title => $text)
                        <x-card>
                            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-tint text-sm font-bold text-brand">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <h3 class="mt-4 text-base font-bold text-ink">{{ $title }}</h3>
                            <p class="mt-2 text-sm leading-6 text-body">{{ $text }}</p>
                        </x-card>
                    @endforeach
                </div>
            </section>

            <section id="process" class="scroll-mt-24">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold text-brand">How it works</p>
                    <h2 class="mt-1 text-2xl font-extrabold text-ink">A structured verification process for modern lending teams</h2>
                    <p class="mt-2 text-sm leading-6 text-body">Every loan application follows a clear review path from intake to decision, with responsible checks and complete visibility.</p>
                </div>

                <ol class="relative mt-8 grid gap-4 md:grid-cols-3 md:gap-6">
                    {{-- line joining the three steps on wide screens --}}
                    <li class="absolute left-[16.66%] right-[16.66%] top-6 hidden h-0.5 bg-brand-mist md:block" aria-hidden="true"></li>

                    @foreach ($process as $title => $text)
                        <li class="relative flex items-start gap-4 rounded-2xl border border-slate-200 bg-white p-5 md:flex-col md:items-center md:border-0 md:bg-transparent md:p-0 md:text-center">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-brand text-base font-extrabold text-white ring-8 ring-surface">{{ $loop->iteration }}</span>
                            <div class="md:w-full md:rounded-2xl md:border md:border-slate-200 md:bg-white md:p-5">
                                <h3 class="text-base font-bold text-ink">{{ $title }}</h3>
                                <p class="mt-1 text-sm leading-6 text-body">{{ $text }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>

            <section data-teal class="flex flex-col gap-5 rounded-2xl bg-brand-dark p-6 text-white md:flex-row md:items-center md:justify-between md:p-8">
                <div class="max-w-xl">
                    <h2 class="text-2xl font-extrabold text-white">Ready to start your application?</h2>
                    <p class="mt-2 text-sm leading-6 text-brand-mist">Upload your documents once. We read them, check them and give you a clear result.</p>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row">
                    @auth
                        <x-button-link variant="white" href="{{ url('/dashboard') }}">Go to dashboard</x-button-link>
                    @else
                        @if (Route::has('register'))
                            <x-button-link variant="white" href="{{ route('register') }}">Create account</x-button-link>
                        @endif
                        <a href="{{ route('login') }}" class="inline-flex min-h-12 items-center justify-center rounded-xl border border-white px-5 py-2 text-sm font-bold text-white transition hover:bg-brand focus:outline-none focus-visible:ring-2 focus-visible:ring-white">Sign in</a>
                    @endauth
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-200 px-4 py-8 text-center text-sm text-body md:px-8">
            <p>Loan Verification System &copy; {{ date('Y') }} - Built for secure lending operations.</p>
        </footer>
    </body>
</html>
