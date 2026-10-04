<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Loan Verification System') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body x-data="theme()" :class="themeClass" class="min-h-screen bg-surface font-sans text-body antialiased">
        @php
            $navLink = 'inline-flex min-h-11 items-center rounded-xl px-3 text-sm font-medium text-brand-mist transition hover:bg-brand hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white';

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

        <div class="bg-brand-dark text-white">
            <header class="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-4 md:flex-row md:items-center md:justify-between md:px-8">
                <a href="/" class="self-start rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
                    <x-brand-mark />
                </a>

                @if (Route::has('login'))
                    <nav class="-mx-3 flex flex-wrap items-center gap-1 md:mx-0" aria-label="Main">
                        <a href="#solutions" class="{{ $navLink }}">Solutions</a>
                        <a href="#process" class="{{ $navLink }}">Process</a>
                        <a href="#contact" class="{{ $navLink }}">Contact</a>
                        <button type="button" @click="toggle" class="{{ $navLink }}">
                            <span x-text="theme === 'dark' ? 'Light mode' : 'Dark mode'">Dark mode</span>
                        </button>
                        @auth
                            <x-button-link variant="white" href="{{ url('/dashboard') }}" class="ml-3 md:ml-2">Dashboard</x-button-link>
                        @else
                            <a href="{{ route('login') }}" class="{{ $navLink }}">Sign in</a>
                            @if (Route::has('register'))
                                <x-button-link variant="white" href="{{ route('register') }}" class="ml-3 md:ml-2">Book demo</x-button-link>
                            @endif
                        @endauth
                    </nav>
                @endif
            </header>

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
            <section id="solutions" class="scroll-mt-6">
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

            <div class="grid gap-6 lg:grid-cols-5">
                <section id="process" class="scroll-mt-6 lg:col-span-3">
                    <p class="text-sm font-semibold text-brand">How it works</p>
                    <h2 class="mt-1 text-2xl font-extrabold text-ink">A structured verification process for modern lending teams</h2>
                    <p class="mt-2 text-sm leading-6 text-body">Every loan application follows a clear review path from intake to decision, with responsible checks and complete visibility.</p>

                    <ol class="mt-5 space-y-3">
                        @foreach ($process as $title => $text)
                            <li class="flex items-start gap-4 rounded-2xl border border-slate-200 bg-white p-5">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand text-sm font-bold text-white">{{ $loop->iteration }}</span>
                                <div>
                                    <h3 class="text-base font-bold text-ink">{{ $title }}</h3>
                                    <p class="mt-1 text-sm leading-6 text-body">{{ $text }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>

                <x-card id="contact" class="scroll-mt-6 self-start lg:col-span-2">
                    <p class="text-sm font-semibold text-brand">Need a demo?</p>
                    <h2 class="mt-1 text-xl font-extrabold text-ink">Talk to our lending operations team</h2>
                    <p class="mt-2 text-sm leading-6 text-body">Book a consultation to see how the verification workflow can support your approval process.</p>

                    <form class="mt-5 space-y-3">
                        <x-text-input class="block w-full" placeholder="Full name" aria-label="Full name" />
                        <x-text-input class="block w-full" placeholder="Work email" aria-label="Work email" />
                        <x-text-input class="block w-full" placeholder="Company" aria-label="Company" />
                        <x-primary-button class="w-full">Request demo</x-primary-button>
                    </form>
                </x-card>
            </div>
        </main>

        <footer class="border-t border-slate-200 px-4 py-8 text-center text-sm text-body md:px-8">
            <p>Loan Verification System &copy; {{ date('Y') }} - Built for secure lending operations.</p>
        </footer>
    </body>
</html>
