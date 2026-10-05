<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#1d1e3e">
    <title>@yield('title', 'Sign in') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="relative isolate flex min-h-screen items-center justify-center overflow-x-hidden bg-[#191a36] p-3 text-slate-800 antialiased sm:p-6">
    <span class="pointer-events-none absolute -left-32 -top-36 -z-10 size-[30rem] rounded-full bg-indigo-500/20 blur-[100px]" aria-hidden="true"></span><span class="pointer-events-none absolute -bottom-40 -right-24 -z-10 size-[32rem] rounded-full bg-violet-500/15 blur-[110px]" aria-hidden="true"></span>
    <main class="grid w-full max-w-5xl overflow-hidden rounded-[1.75rem] border border-white/10 bg-white shadow-[0_35px_100px_rgb(5_6_24/0.5)] md:min-h-[610px] md:grid-cols-[.88fr_1.12fr]">
        <aside class="relative hidden flex-col justify-between overflow-hidden bg-gradient-to-br from-[#25264f] via-[#28264e] to-[#45407a] p-8 text-white md:flex lg:p-10">
            <span class="pointer-events-none absolute -right-20 top-24 size-72 rounded-full border-[44px] border-white/[.045]" aria-hidden="true"></span><span class="pointer-events-none absolute -bottom-24 -left-24 size-72 rounded-full bg-violet-400/10 blur-3xl" aria-hidden="true"></span>
            <a href="{{ route('login') }}" class="relative flex items-center gap-3"><span class="grid size-11 place-items-center rounded-xl bg-gradient-to-br from-indigo-300 to-violet-400 text-xs font-black text-[#252448] shadow-lg shadow-slate-950/20">SD</span><span><span class="block text-sm font-bold tracking-wide">{{ config('app.name') }}</span><span class="mt-0.5 block text-[10px] font-semibold uppercase tracking-[.17em] text-indigo-100/60">IT Service portal</span></span></a>
            <div class="relative my-10"><span class="grid size-12 place-items-center rounded-2xl border border-white/10 bg-white/[.07] text-violet-100"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="size-6" aria-hidden="true"><path d="M4 5h16v12H8l-4 3V5Z M8 9h8 M8 13h5" /></svg></span><p class="mt-6 text-[11px] font-bold uppercase tracking-[.17em] text-violet-200">Reliable help, clear updates</p><h2 class="mt-2 max-w-sm text-3xl font-bold leading-tight tracking-tight lg:text-4xl">Your IT support, all in one place.</h2><p class="mt-4 max-w-sm text-sm leading-6 text-indigo-100/70">Submit a request, follow its progress, and keep your conversation with IT together.</p>
                <ul class="mt-8 space-y-4 text-sm text-indigo-50/80"><li class="flex items-center gap-3"><span class="grid size-6 place-items-center rounded-full bg-emerald-400/15 text-emerald-200">✓</span>Track your request from open to resolved</li><li class="flex items-center gap-3"><span class="grid size-6 place-items-center rounded-full bg-emerald-400/15 text-emerald-200">✓</span>Share screenshots and useful details</li><li class="flex items-center gap-3"><span class="grid size-6 place-items-center rounded-full bg-emerald-400/15 text-emerald-200">✓</span>Keep replies and updates in context</li></ul>
            </div>
            <p class="relative text-xs text-indigo-100/45">Secure access for employees, IT Support, and administrators.</p>
        </aside>
        <section class="flex min-w-0 flex-col justify-center p-6 sm:p-9 lg:p-12">
            <a href="{{ route('login') }}" class="mb-7 inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[.15em] text-violet-700 md:hidden"><span class="grid size-8 place-items-center rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 text-[10px] font-black text-white">SD</span>{{ config('app.name') }}</a>
            <div class="mb-6"><p class="text-[11px] font-bold uppercase tracking-[.16em] text-violet-700">Secure workspace</p><h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-950 sm:text-[1.75rem]">@yield('heading', 'Welcome back')</h1><p class="mt-2 text-sm leading-6 text-slate-500">Sign in to continue to your support workspace.</p></div>
            @if (session('status'))<p class="ui-alert-success mb-5" role="status">{{ session('status') }}</p>@endif
            @if ($errors->any())<div class="ui-alert-error mb-5" role="alert"><p class="font-semibold">Please check your details.</p><ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
            <p class="mt-8 border-t border-slate-100 pt-5 text-center text-xs text-slate-400">Your account and ticket information are protected.</p>
        </section>
    </main>
</body>
</html>
