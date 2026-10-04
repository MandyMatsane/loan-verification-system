<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Loan Verification System') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body x-data="theme()" :class="themeClass" class="min-h-screen bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
        <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top_left,_rgba(251,191,36,0.14),_transparent_28%),radial-gradient(circle_at_bottom_right,_rgba(59,130,246,0.12),_transparent_32%)]"></div>

        <header class="mx-auto flex max-w-7xl flex-col gap-4 px-6 py-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
            <div>
                <a href="/" class="text-xl font-semibold tracking-tight text-slate-900 dark:text-white">
                    <span class="font-semibold">Loan</span>
                    <span class="text-amber-500">Verify</span>
                </a>
            </div>

            @if (Route::has('login'))
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ url('/') }}" class="text-sm font-medium text-slate-700 transition hover:text-slate-900 dark:text-slate-300 dark:hover:text-white">Home</a>
                    <a href="#solutions" class="text-sm font-medium text-slate-700 transition hover:text-slate-900 dark:text-slate-300 dark:hover:text-white">Solutions</a>
                    <a href="#process" class="text-sm font-medium text-slate-700 transition hover:text-slate-900 dark:text-slate-300 dark:hover:text-white">Process</a>
                    <a href="#contact" class="text-sm font-medium text-slate-700 transition hover:text-slate-900 dark:text-slate-300 dark:hover:text-white">Contact</a>
                    <button @click="toggle" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-900 shadow-sm transition hover:bg-slate-100 dark:border-slate-700/70 dark:bg-slate-900/70 dark:text-slate-100 dark:hover:bg-slate-800">
                        <span x-text="theme === 'dark' ? 'Light mode' : 'Dark mode'"></span>
                    </button>
                    @auth
                        <a href="{{ url('/dashboard') }}" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-900 shadow-sm transition hover:bg-slate-50 dark:border-slate-700/70 dark:bg-slate-900/70 dark:text-slate-100 dark:hover:bg-slate-800">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="text-sm font-medium text-slate-700 transition hover:text-slate-900 dark:text-slate-300 dark:hover:text-white">Sign in</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="rounded-full bg-amber-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-amber-400">Book demo</a>
                        @endif
                    @endauth
                </div>
            @endif
        </header>

        <main class="mx-auto max-w-7xl px-6 pb-24 lg:px-8">
            <section class="relative overflow-hidden rounded-[2.5rem] border border-slate-200 bg-slate-950 px-8 py-14 text-white shadow-[0_25px_80px_-35px_rgba(15,23,42,0.5)] dark:border-slate-800 xl:grid xl:grid-cols-[1.2fr_0.8fr] xl:gap-10 xl:px-14 xl:py-16">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(251,191,36,0.20),_transparent_20%),radial-gradient(circle_at_bottom_right,_rgba(14,165,233,0.18),_transparent_30%)]"></div>

                <div class="relative z-10 max-w-2xl">
                    <div class="mb-6 inline-flex items-center rounded-full border border-amber-400/30 bg-amber-400/10 px-4 py-2 text-xs font-semibold uppercase tracking-[0.28em] text-amber-300">
                        Loan verification platform
                    </div>
                    <h1 class="max-w-xl text-5xl font-semibold leading-tight tracking-tight text-white sm:text-6xl xl:text-7xl">
                        Verify every loan with clarity, speed, and confidence.
                    </h1>
                    <p class="mt-6 max-w-xl text-lg leading-8 text-slate-300">
                        Streamline applicant review, validate identity and income, and surface risk indicators in a secure workflow built for lending teams.
                    </p>
                    <div class="mt-9 flex flex-col gap-4 sm:flex-row">
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-full bg-amber-400 px-7 py-3 text-sm font-semibold text-slate-950 shadow-lg shadow-amber-500/20 transition hover:bg-amber-300">
                                Start verification
                            </a>
                        @endif
                        <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-full border border-slate-700 bg-slate-900 px-7 py-3 text-sm font-semibold text-white transition hover:border-slate-500 hover:bg-slate-800">
                            View dashboard
                        </a>
                    </div>

                    <div class="mt-10 flex flex-wrap gap-3 text-sm text-slate-300">
                        <span class="rounded-full border border-slate-700 bg-slate-900/80 px-3 py-1.5">KYC check</span>
                        <span class="rounded-full border border-slate-700 bg-slate-900/80 px-3 py-1.5">Income validation</span>
                        <span class="rounded-full border border-slate-700 bg-slate-900/80 px-3 py-1.5">Fraud review</span>
                        <span class="rounded-full border border-slate-700 bg-slate-900/80 px-3 py-1.5">Compliance</span>
                    </div>
                </div>

                <div class="relative z-10 mt-10 rounded-[2rem] border border-slate-700 bg-slate-900/80 p-6 shadow-2xl shadow-slate-950/30 xl:mt-0">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-slate-300">Application pipeline</p>
                        <span class="rounded-full bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-300">Live</span>
                    </div>

                    <div class="mt-6 space-y-4">
                        <div class="rounded-2xl border border-slate-700 bg-slate-800 p-4">
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-sm text-slate-300">Document verification</span>
                                <span class="text-sm font-semibold text-emerald-300">96%</span>
                            </div>
                        </div>
                        <div class="rounded-2xl border border-slate-700 bg-slate-800 p-4">
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-sm text-slate-300">Income validation</span>
                                <span class="text-sm font-semibold text-amber-300">Passed</span>
                            </div>
                        </div>
                        <div class="rounded-2xl border border-slate-700 bg-slate-800 p-4">
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-sm text-slate-300">Risk review</span>
                                <span class="text-sm font-semibold text-cyan-300">In review</span>
                            </div>
                        </div>
                        <div class="rounded-2xl border border-slate-700 bg-slate-800 p-4">
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-sm text-slate-300">Compliance status</span>
                                <span class="text-sm font-semibold text-emerald-300">Verified</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="solutions" class="mt-16 grid gap-6 lg:grid-cols-4">
                <div class="rounded-[2rem] bg-white p-8 shadow-xl shadow-slate-900/5 dark:bg-slate-950 dark:shadow-slate-950/20">
                    <div class="mb-5 inline-flex rounded-2xl bg-amber-500/10 p-3 text-amber-500">01</div>
                    <h2 class="text-2xl font-semibold text-slate-900 dark:text-white">Identity checks</h2>
                    <p class="mt-4 text-sm leading-7 text-slate-600 dark:text-slate-400">Confirm applicant identity through structured document review and verification workflow.</p>
                </div>
                <div class="rounded-[2rem] bg-white p-8 shadow-xl shadow-slate-900/5 dark:bg-slate-950 dark:shadow-slate-950/20">
                    <div class="mb-5 inline-flex rounded-2xl bg-cyan-500/10 p-3 text-cyan-500">02</div>
                    <h2 class="text-2xl font-semibold text-slate-900 dark:text-white">Income validation</h2>
                    <p class="mt-4 text-sm leading-7 text-slate-600 dark:text-slate-400">Review earnings evidence and financial records to support responsible lending decisions.</p>
                </div>
                <div class="rounded-[2rem] bg-white p-8 shadow-xl shadow-slate-900/5 dark:bg-slate-950 dark:shadow-slate-950/20">
                    <div class="mb-5 inline-flex rounded-2xl bg-emerald-500/10 p-3 text-emerald-500">03</div>
                    <h2 class="text-2xl font-semibold text-slate-900 dark:text-white">Fraud review</h2>
                    <p class="mt-4 text-sm leading-7 text-slate-600 dark:text-slate-400">Reduce exposure with risk signals, document consistency checks, and audit-ready review trails.</p>
                </div>
                <div class="rounded-[2rem] bg-white p-8 shadow-xl shadow-slate-900/5 dark:bg-slate-950 dark:shadow-slate-950/20">
                    <div class="mb-5 inline-flex rounded-2xl bg-violet-500/10 p-3 text-violet-500">04</div>
                    <h2 class="text-2xl font-semibold text-slate-900 dark:text-white">Compliance</h2>
                    <p class="mt-4 text-sm leading-7 text-slate-600 dark:text-slate-400">Maintain oversight with transparent process tracking and structured approval documentation.</p>
                </div>
            </section>

            <section id="process" class="mt-16 grid gap-10 lg:grid-cols-[1.1fr_0.9fr]">
                <div class="space-y-6 rounded-[2rem] border border-slate-200/70 bg-slate-50 p-10 shadow-2xl shadow-slate-900/10 dark:border-slate-700/70 dark:bg-slate-950/80 dark:shadow-slate-950/30">
                    <div class="max-w-xl">
                        <p class="text-sm font-semibold uppercase tracking-[0.3em] text-amber-500">How it works</p>
                        <h2 class="mt-4 text-3xl font-semibold text-slate-900 dark:text-white">A structured verification process for modern lending teams</h2>
                        <p class="mt-4 text-slate-600 dark:text-slate-400">Every loan application follows a clear review path from intake to decision, with responsible checks and complete visibility.</p>
                    </div>

                    <div class="grid gap-4">
                        <div class="rounded-3xl border border-slate-200/70 bg-white p-6 dark:border-slate-700/70 dark:bg-slate-900/80">
                            <div class="flex items-center gap-4">
                                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-500 text-sm font-bold text-slate-950">1</span>
                                <div>
                                    <p class="text-lg font-semibold text-slate-900 dark:text-white">Application intake</p>
                                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Capture applicant details and supporting records in a secure digital workflow.</p>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-3xl border border-slate-200/70 bg-white p-6 dark:border-slate-700/70 dark:bg-slate-900/80">
                            <div class="flex items-center gap-4">
                                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-cyan-500 text-sm font-bold text-white">2</span>
                                <div>
                                    <p class="text-lg font-semibold text-slate-900 dark:text-white">Verification review</p>
                                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Validate identity, financial credentials, and risk indicators against policy rules.</p>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-3xl border border-slate-200/70 bg-white p-6 dark:border-slate-700/70 dark:bg-slate-900/80">
                            <div class="flex items-center gap-4">
                                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500 text-sm font-bold text-white">3</span>
                                <div>
                                    <p class="text-lg font-semibold text-slate-900 dark:text-white">Decision support</p>
                                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Review recommended outcomes, approve confidently, and maintain a clear audit trail.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="contact" class="rounded-[2rem] bg-white p-10 shadow-xl shadow-slate-900/10 dark:bg-slate-950 dark:shadow-slate-950/20">
                    <p class="text-sm font-semibold uppercase tracking-[0.3em] text-amber-500">Need a demo?</p>
                    <h3 class="mt-4 text-2xl font-semibold text-slate-900 dark:text-white">Talk to our lending operations team</h3>
                    <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-400">Book a consultation to see how the verification workflow can support your approval process.</p>

                    <form class="mt-8 space-y-4">
                        <input class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-900/80 dark:text-white" placeholder="Full name" />
                        <input class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-900/80 dark:text-white" placeholder="Work email" />
                        <input class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-900/80 dark:text-white" placeholder="Company" />
                        <button class="w-full rounded-full bg-amber-500 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-amber-400">Request demo</button>
                    </form>
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-200/70 px-6 py-8 text-center text-sm text-slate-600 dark:border-slate-800/70 dark:text-slate-400 lg:px-8">
            <p>Loan Verification System © {{ date('Y') }} — Built for secure lending operations.</p>
        </footer>
    </body>
</html>
