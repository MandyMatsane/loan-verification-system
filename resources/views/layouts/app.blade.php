<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Loan Verification System') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body x-data="theme()" :class="themeClass" class="min-h-screen bg-surface font-sans text-body antialiased">
        <div class="absolute inset-0 -z-10 hidden overflow-hidden dark:block">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(34,211,238,0.18),_transparent_35%),radial-gradient(circle_at_bottom_right,_rgba(16,185,129,0.14),_transparent_35%)]"></div>
        </div>

        @include('layouts.navigation')

        <div class="min-h-screen md:pl-64">
            @isset($header)
                <header class="mx-auto max-w-6xl px-4 pt-6 md:px-8 md:pt-8">
                    {{ $header }}
                </header>
            @endisset

            <main class="mx-auto max-w-6xl px-4 pb-24 pt-6 md:px-8 md:pb-10">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
