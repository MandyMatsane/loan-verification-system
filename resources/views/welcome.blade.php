<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Loan Verification System') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body x-data="theme()" :class="themeClass" class="min-h-screen bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
        <div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top_left,_rgba(253,186,116,0.18),_transparent_30%),radial-gradient(circle_at_bottom_right,_rgba(56,189,248,0.14),_transparent_35%)]"></div>

        <header class="mx-auto flex max-w-7xl flex-col gap-4 px-6 py-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
            <div>
                <a href="/" class="text-xl font-semibold tracking-tight text-slate-900 dark:text-white">
                    <span class="font-medium">Loan Verification</span>
                    <span class="text-cyan-500"> System</span>
                </a>
            </div>

            @if (Route::has('login'))
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ url('/') }}" class="text-sm font-medium text-slate-700 transition hover:text-slate-900 dark:text-slate-300 dark:hover:text-white">Home</a>
                    <a href="#services" class="text-sm font-medium text-slate-700 transition hover:text-slate-900 dark:text-slate-300 dark:hover:text-white">Services</a>
                    <a href="#process" class="text-sm font-medium text-slate-700 transition hover:text-slate-900 dark:text-slate-300 dark:hover:text-white">Process</a>
                    <a href="#" class="text-sm font-medium text-slate-700 transition hover:text-slate-900 dark:text-slate-300 dark:hover:text-white">Contact</a>
                    <button @click="toggle" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-900 shadow-sm transition hover:bg-slate-100 dark:border-slate-700/70 dark:bg-slate-900/70 dark:text-slate-100 dark:hover:bg-slate-800">
                        <span x-text="theme === 'dark' ? 'Light mode' : 'Dark mode'"></span>
                    </button>
                    @auth
                        <a href="{{ url('/dashboard') }}" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-900 shadow-sm transition hover:bg-slate-50 dark:border-slate-700/70 dark:bg-slate-900/70 dark:text-slate-100 dark:hover:bg-slate-800">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="text-sm font-medium text-slate-700 transition hover:text-slate-900 dark:text-slate-300 dark:hover:text-white">Sign in</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="rounded-full bg-amber-500 px-4 py-2 text-sm font-semibold text-slate-950 transition hover:bg-amber-400">Schedule Consultation</a>
                        @endif
                    @endauth
                </div>
            @endif
        </header>

        <main class="mx-auto max-w-7xl px-6 pb-24 lg:px-8">
            <section class="relative overflow-hidden rounded-[2.5rem] border border-slate-200 bg-white px-8 py-14 shadow-[0_25px_60px_-35px_rgba(15,23,42,0.12)] dark:border-slate-800 dark:bg-slate-950 dark:shadow-black/20 xl:grid xl:grid-cols-[1.25fr_0.95fr] xl:gap-10 xl:px-14 xl:py-16">
                <div class="absolute left-0 top-0 h-64 w-64 -translate-x-1/2 rounded-full bg-amber-400/15 blur-3xl"></div>
                <div class="absolute right-0 bottom-0 h-72 w-72 translate-x-1/3 rounded-full bg-cyan-400/15 blur-3xl"></div>

                <div class="relative z-10 max-w-2xl">
                    <div class="mb-6 inline-flex items-center rounded-full border border-amber-200/80 bg-amber-50 px-4 py-2 text-sm font-semibold uppercase tracking-[0.3em] text-amber-600 dark:border-amber-400/20 dark:bg-amber-500/10 dark:text-amber-300">
                        Premium lending workflows
                    </div>
                    <h1 class="font-serif text-5xl font-semibold leading-tight tracking-tight text-slate-950 sm:text-6xl xl:text-7xl dark:text-white">
                        Secure loan verification with a calm, confident experience.
                    </h1>
                    <p class="mt-6 max-w-xl text-lg leading-8 text-slate-600 dark:text-slate-300">
                        Modernize document intake, automate risk review, and give your team a polished lending workflow they can trust.
                    </p>
                    <div class="mt-9 flex flex-col gap-4 sm:flex-row">
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-full bg-amber-500 px-7 py-3 text-sm font-semibold text-slate-950 shadow-lg shadow-amber-500/20 transition hover:bg-amber-400">
                                Get started
                            </a>
                        @endif
                        <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-full border border-slate-300 bg-white px-7 py-3 text-sm font-semibold text-slate-900 transition hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700/70 dark:bg-slate-900/80 dark:text-white dark:hover:border-slate-500 dark:hover:bg-slate-800">
                            View dashboard
                        </a>
                    </div>
                    <div class="mt-10 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-3xl border border-slate-200/70 bg-slate-50 p-5 text-center dark:border-slate-700/70 dark:bg-slate-900/80">
                            <p class="text-3xl font-semibold text-slate-950 dark:text-white">99.9%</p>
                            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">System availability</p>
                        </div>
                        <div class="rounded-3xl border border-slate-200/70 bg-slate-50 p-5 text-center dark:border-slate-700/70 dark:bg-slate-900/80">
                            <p class="text-3xl font-semibold text-slate-950 dark:text-white">120+</p>
                            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Applications reviewed</p>
                        </div>
                        <div class="rounded-3xl border border-slate-200/70 bg-slate-50 p-5 text-center dark:border-slate-700/70 dark:bg-slate-900/80">
                            <p class="text-3xl font-semibold text-slate-950 dark:text-white">24/7</p>
                            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Secure access</p>
                        </div>
                    </div>
                </div>

                <div class="relative hidden rounded-[2rem] border border-slate-200 bg-slate-50 p-8 shadow-xl dark:border-slate-700/70 dark:bg-slate-900/80 xl:block">
                    <div class="text-slate-700 dark:text-slate-300">Today’s workflow</div>
                    <div class="mt-5 rounded-[2rem] bg-white p-6 shadow-sm dark:bg-slate-950/90">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-slate-500 dark:text-slate-400">Applications reviewed</p>
                                <p class="mt-2 text-3xl font-semibold text-slate-950 dark:text-white">120</p>
                            </div>
                            <div class="rounded-full bg-amber-500/15 px-3 py-2 text-sm font-semibold text-amber-700 dark:text-amber-200">+18%</div>
                        </div>
                        <div class="mt-6 space-y-4">
                            <div class="rounded-3xl bg-slate-100 p-5 dark:bg-slate-950/90">
                                <p class="font-semibold text-slate-950 dark:text-white">Document validation</p>
                                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Collect and verify ID and financial records instantly.</p>
                            </div>
                            <div class="rounded-3xl bg-slate-100 p-5 dark:bg-slate-950/90">
                                <p class="font-semibold text-slate-950 dark:text-white">AI review</p>
                                <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Informed decisions with risk signals and verification status.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="services" class="grid gap-6 lg:grid-cols-3">
                <div class="rounded-[2rem] bg-white p-8 shadow-xl shadow-slate-900/5 dark:bg-slate-950 dark:shadow-slate-950/20">
                    <p class="mb-4 text-sm font-semibold uppercase tracking-[0.3em] text-amber-500">Wealth Building</p>
                    <h2 class="text-2xl font-semibold text-slate-900 dark:text-white">Build confidence in every application</h2>
                    <p class="mt-4 text-sm leading-7 text-slate-600 dark:text-slate-400">Automatically track applicant documents and review status in a single premium workflow.</p>
                    <a href="#" class="mt-6 inline-flex items-center text-sm font-semibold text-amber-500">Discover More →</a>
                </div>
                <div class="rounded-[2rem] bg-white p-8 shadow-xl shadow-slate-900/5 dark:bg-slate-950 dark:shadow-slate-950/20">
                    <p class="mb-4 text-sm font-semibold uppercase tracking-[0.3em] text-amber-500">Investment Growth</p>
                    <h2 class="text-2xl font-semibold text-slate-900 dark:text-white">Grow faster with smarter decisions</h2>
                    <p class="mt-4 text-sm leading-7 text-slate-600 dark:text-slate-400">Use insights to speed up approvals and reduce manual review time.</p>
                    <a href="#" class="mt-6 inline-flex items-center text-sm font-semibold text-amber-500">Discover More →</a>
                </div>
                <div class="rounded-[2rem] bg-white p-8 shadow-xl shadow-slate-900/5 dark:bg-slate-950 dark:shadow-slate-950/20">
                    <p class="mb-4 text-sm font-semibold uppercase tracking-[0.3em] text-amber-500">Financial Protection</p>
                    <h2 class="text-2xl font-semibold text-slate-900 dark:text-white">Keep your data safe and compliant</h2>
                    <p class="mt-4 text-sm leading-7 text-slate-600 dark:text-slate-400">Secure document handling and audit-ready review from start to finish.</p>
                    <a href="#" class="mt-6 inline-flex items-center text-sm font-semibold text-amber-500">Discover More →</a>
                </div>
            </section>

            <section id="process" class="grid gap-10 lg:grid-cols-[1.12fr_0.88fr]">
                <div class="space-y-6 rounded-[2rem] border border-slate-200/70 bg-slate-50 p-10 shadow-2xl shadow-slate-900/10 dark:border-slate-700/70 dark:bg-slate-950/80 dark:shadow-slate-950/30">
                    <div class="max-w-xl">
                        <p class="text-sm font-semibold uppercase tracking-[0.3em] text-amber-500">How We Work</p>
                        <h2 class="mt-4 text-3xl font-semibold text-slate-900 dark:text-white">A premium process for modern lending teams</h2>
                        <p class="mt-4 text-slate-600 dark:text-slate-400">From first contact to final decision, the platform keeps every loan application moving with clarity and speed.</p>
                    </div>

                    <div class="grid gap-4">
                        <div class="rounded-3xl border border-slate-200/70 bg-white p-6 dark:border-slate-700/70 dark:bg-slate-900/80">
                            <p class="text-2xl font-semibold text-slate-900 dark:text-white">1</p>
                            <p class="mt-3 text-lg font-semibold text-slate-900 dark:text-white">Initial consultation</p>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Collect applicant details, verify documents, and prepare a secure file.</p>
                        </div>
                        <div class="rounded-3xl border border-slate-200/70 bg-white p-6 dark:border-slate-700/70 dark:bg-slate-900/80">
                            <p class="text-2xl font-semibold text-slate-900 dark:text-white">2</p>
                            <p class="mt-3 text-lg font-semibold text-slate-900 dark:text-white">Strategic review</p>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Leverage automation and expert checks to identify risk efficiently.</p>
                        </div>
                        <div class="rounded-3xl border border-slate-200/70 bg-white p-6 dark:border-slate-700/70 dark:bg-slate-900/80">
                            <p class="text-2xl font-semibold text-slate-900 dark:text-white">3</p>
                            <p class="mt-3 text-lg font-semibold text-slate-900 dark:text-white">Execution</p>
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Finalize decisions, secure approvals, and keep your team aligned.</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-[2rem] bg-white p-10 shadow-xl shadow-slate-900/10 dark:bg-slate-950 dark:shadow-slate-950/20">
                    <p class="text-sm font-semibold uppercase tracking-[0.3em] text-amber-500">Speak with an expert</p>
                    <h3 class="mt-4 text-2xl font-semibold text-slate-900 dark:text-white">Schedule your free consultation</h3>
                    <p class="mt-3 text-sm leading-7 text-slate-600 dark:text-slate-400">Leave your details and our team will connect you with the right advisor.</p>

                    <form class="mt-8 space-y-4">
                        <input class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-900/80 dark:text-white" placeholder="Name" />
                        <input class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-900/80 dark:text-white" placeholder="Email" />
                        <input class="w-full rounded-3xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 dark:border-slate-700 dark:bg-slate-900/80 dark:text-white" placeholder="Phone" />
                        <button class="w-full rounded-full bg-amber-500 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-amber-400">Send request</button>
                    </form>
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-200/70 px-6 py-8 text-center text-sm text-slate-600 dark:border-slate-800/70 dark:text-slate-400 lg:px-8">
            <p>Loan Verification System © {{ date('Y') }} — Designed for modern lending operations.</p>
        </footer>
    </body>
</html>
