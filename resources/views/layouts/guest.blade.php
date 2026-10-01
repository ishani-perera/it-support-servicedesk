<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', 'Sign in') — {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-800 antialiased flex items-center justify-center p-4">
        {{-- Minimal authentication layout (server-rendered, no JS framework). The real UI arrives in a later phase. --}}
        <main class="w-full max-w-sm">
            <h1 class="mb-6 text-center text-xl font-semibold tracking-tight">{{ config('app.name') }}</h1>
            <div class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200">
                @if (session('status'))
                    <p class="mb-4 rounded bg-emerald-50 p-3 text-sm text-emerald-800" role="status">{{ session('status') }}</p>
                @endif

                @yield('content')
            </div>
        </main>
    </body>
</html>
