<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Loan Verification System') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body x-data="theme()" :class="themeClass" class="min-h-screen bg-surface font-sans text-body antialiased">
        <script>if (localStorage.getItem('theme') === 'dark') document.body.classList.add('dark');</script>
        <div class="flex min-h-screen flex-col md:flex-row">
            {{-- Teal panel: header block on phones, left panel from md up --}}
            <div class="bg-brand-dark px-6 py-6 text-white md:flex md:w-1/2 md:flex-col md:px-8 md:py-10 lg:w-[45%] lg:px-12">
                <div class="flex items-center justify-between gap-4">
                    <a href="/" class="rounded-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
                        <x-brand-mark />
                    </a>
                    <button type="button" @click="toggle" class="inline-flex min-h-11 items-center gap-2 whitespace-nowrap rounded-xl px-3 text-sm font-medium text-brand-light transition hover:bg-brand hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white">
                        <x-icon name="moon" x-show="theme !== 'dark'" />
                        <x-icon name="sun" x-show="theme === 'dark'" x-cloak />
                        <span x-text="theme === 'dark' ? 'Light mode' : 'Dark mode'">Dark mode</span>
                    </button>
                </div>

                <div class="mt-6 md:my-auto md:py-10">
                    <h1 class="max-w-md text-2xl font-extrabold leading-tight text-white md:text-4xl">Faster, clearer loan decisions</h1>
                    <p class="mt-3 max-w-md text-sm leading-6 text-brand-mist md:text-base md:leading-7">
                        Upload your documents once. We read them, check them and give you a clear result.
                    </p>

                    <ul class="mt-8 hidden space-y-4 md:block">
                        @foreach (['Upload your documents once', 'Automatic checks, clear results', 'A person reviews unclear cases'] as $point)
                            <li class="flex items-center gap-3 text-sm font-medium text-white">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-light text-slate-900">
                                    <x-icon name="check" class="h-4 w-4" />
                                </span>
                                {{ $point }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <p class="hidden text-sm text-brand-light md:block">Your documents are used only to assess this application.</p>
            </div>

            <main class="flex flex-1 items-start justify-center px-6 py-8 md:items-center md:px-12">
                <div class="w-full max-w-md">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
