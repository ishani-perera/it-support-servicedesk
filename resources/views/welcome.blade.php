<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-800 antialiased flex items-center justify-center">
        {{-- Placeholder only: confirms Blade + Tailwind + Vite are wired. Real UI arrives in a later phase. --}}
        <main class="text-center">
            <h1 class="text-3xl font-semibold tracking-tight">{{ config('app.name') }}</h1>
            <p class="mt-2 text-sm text-slate-500">Foundation ready.</p>
        </main>
    </body>
</html>
