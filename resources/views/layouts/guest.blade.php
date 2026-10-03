<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f6f7fb">
    <title>@yield('title', 'Sign in') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-50 p-4 text-slate-800 antialiased">
    <main class="w-full max-w-md">
        <a href="{{ route('login') }}" class="mb-7 flex items-center justify-center gap-3 text-center focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600">
            <span class="grid size-11 place-items-center rounded-2xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-xs font-black text-white shadow-lg shadow-indigo-900/20" aria-hidden="true">SD</span>
            <span class="text-left"><span class="block text-lg font-bold tracking-tight text-slate-950">{{ config('app.name') }}</span><span class="block text-xs font-medium text-slate-500">IT SERVICE PORTAL</span></span>
        </a>
        <div class="ui-card overflow-hidden shadow-lg shadow-slate-900/5">
            <div class="border-b border-slate-100 bg-gradient-to-br from-indigo-50/80 to-white px-6 py-5 sm:px-8"><p class="ui-eyebrow">Secure access</p><h1 class="mt-1 text-xl font-bold tracking-tight text-slate-950">@yield('heading', 'Welcome back')</h1><p class="mt-1 text-sm text-slate-500">Sign in to continue to your support workspace.</p></div>
            <div class="p-6 sm:p-8">
                @if (session('status'))<p class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-3.5 text-sm text-emerald-900" role="status">{{ session('status') }}</p>@endif
                @if ($errors->any())<div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-3.5 text-sm text-rose-900" role="alert"><p class="font-semibold">Please check your details.</p><ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                @yield('content')
            </div>
        </div>
        <p class="mt-6 text-center text-xs text-slate-400">Your account and ticket information are protected.</p>
    </main>
</body>
</html>
