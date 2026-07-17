<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Loan Verification System') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body x-data="theme()" :class="themeClass" class="min-h-screen bg-slate-50 text-slate-900 font-sans antialiased dark:bg-slate-950 dark:text-slate-100">
        <div class="absolute inset-0 -z-10 hidden overflow-hidden dark:block">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(34,211,238,0.22),_transparent_38%),radial-gradient(circle_at_bottom_right,_rgba(16,185,129,0.18),_transparent_36%)]"></div>
        </div>

        <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10 sm:px-6 lg:px-8">
            <div class="mb-6 flex w-full max-w-md flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <a href="/" class="inline-flex items-center rounded-full border border-slate-200/70 bg-white px-4 py-2 text-sm font-semibold tracking-[0.2em] text-slate-900 shadow-sm transition hover:bg-slate-50 dark:border-slate-700/70 dark:bg-slate-900/70 dark:text-slate-100 dark:hover:bg-slate-800">
                    LOAN VERIFICATION
                </a>
                <button @click="toggle" class="inline-flex items-center justify-center rounded-full border border-slate-200/70 bg-white px-4 py-2 text-sm font-semibold text-slate-900 shadow-sm transition hover:bg-slate-50 dark:border-slate-700/70 dark:bg-slate-900/70 dark:text-slate-100 dark:hover:bg-slate-800">
                    <span x-text="theme === 'dark' ? 'Light mode' : 'Dark mode'"></span>
                </button>
            </div>

            <div class="w-full max-w-md rounded-[2rem] border border-slate-200/70 bg-white p-6 shadow-2xl shadow-slate-900/10 dark:border-slate-800/70 dark:bg-slate-950/90 dark:shadow-black/30 sm:p-8">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
