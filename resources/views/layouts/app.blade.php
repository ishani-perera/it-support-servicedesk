<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ServiceDesk') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
    <div class="min-h-screen lg:flex">
        <button id="sidebar-backdrop" type="button" class="fixed inset-0 z-30 hidden bg-slate-950/40 lg:hidden" aria-label="Close navigation"></button>
        <aside id="app-sidebar" class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r border-slate-200 bg-white transition-transform duration-200 lg:static lg:translate-x-0">
            <a href="{{ auth()->user()->isSupport() ? route('support.dashboard') : route('employee.dashboard') }}" class="flex h-20 items-center gap-3 border-b border-slate-100 px-6 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600">
                <span class="grid size-10 place-items-center rounded-xl bg-indigo-600 text-lg font-bold text-white">S</span>
                <span><span class="block text-sm font-bold tracking-tight text-slate-900">ServiceDesk</span><span class="block text-xs text-slate-500">IT support portal</span></span>
            </a>
            @if (auth()->user()->isSupport())
                <nav aria-label="Main navigation" class="flex-1 space-y-1 px-4 py-6">
                    <a href="{{ route('support.dashboard') }}" @class(['flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600', 'bg-indigo-50 text-indigo-700' => request()->routeIs('support.dashboard'), 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('support.dashboard')])><span aria-hidden="true">▦</span> Support dashboard</a>
                    <a href="{{ route('tickets.index') }}" @class(['flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600', 'bg-indigo-50 text-indigo-700' => request()->routeIs('tickets.index'), 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('tickets.index')])><span aria-hidden="true">▤</span> Ticket board</a>
                </nav>
            @else
            <nav aria-label="Main navigation" class="flex-1 space-y-1 px-4 py-6">
                <a href="{{ route('employee.dashboard') }}" @class(['flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600', 'bg-indigo-50 text-indigo-700' => request()->routeIs('employee.dashboard', 'home'), 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('employee.dashboard', 'home')])>
                    <span aria-hidden="true">⌂</span> Dashboard
                </a>
                <a href="{{ route('tickets.index') }}" @class(['flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600', 'bg-indigo-50 text-indigo-700' => request()->routeIs('tickets.index'), 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('tickets.index')])>
                    <span aria-hidden="true">▤</span> My tickets
                </a>
                <a href="{{ route('tickets.create') }}" @class(['flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600', 'bg-indigo-50 text-indigo-700' => request()->routeIs('tickets.create'), 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('tickets.create')])>
                    <span aria-hidden="true">＋</span> Create a ticket
                </a>
            </nav>
            @endif
            <div class="border-t border-slate-100 p-4">
                <div class="flex items-center gap-3 rounded-xl bg-slate-50 p-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-700">{{ auth()->user()->initials }}</span>
                    <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-slate-900">{{ auth()->user()->name }}</p><p class="text-xs text-slate-500">{{ auth()->user()->role->label() }}</p></div>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="rounded-lg p-2 text-slate-500 hover:bg-white hover:text-slate-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600" aria-label="Sign out">↗</button></form>
                </div>
            </div>
        </aside>
        <div class="min-w-0 flex-1">
            <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-8">
                <div class="flex items-center gap-3">
                    <button id="sidebar-toggle" type="button" class="grid size-10 place-items-center rounded-lg text-slate-600 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600 lg:hidden" aria-controls="app-sidebar" aria-expanded="false" aria-label="Open navigation"><span aria-hidden="true">☰</span></button>
                    <div><p class="text-xs font-medium uppercase tracking-wider text-slate-400">{{ auth()->user()->isSupport() ? 'IT Support workspace' : 'Employee portal' }}</p><p class="text-sm font-semibold text-slate-800">@yield('topline', 'Support center')</p></div>
                </div>
                @unless (auth()->user()->isSupport())<a href="{{ route('tickets.create') }}" class="hidden rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:inline-flex">New ticket <span class="ml-2" aria-hidden="true">＋</span></a>@endunless
            </header>
            <main class="mx-auto w-full max-w-7xl px-4 py-7 sm:px-8 sm:py-9">
                @if (session('status'))
                    <div class="mb-6 flex items-start justify-between gap-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
                        <span>{{ session('status') }}</span><button type="button" data-dismiss class="rounded px-2 text-emerald-800 hover:bg-emerald-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-emerald-700" aria-label="Dismiss message">×</button>
                    </div>
                @endif
                @if ($errors->any())
                    <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900" role="alert">
                        <p class="font-semibold">Please check the highlighted fields.</p>
                        <ul class="mt-1 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                @yield('content')
            </main>
            <footer class="px-4 pb-8 text-center text-xs text-slate-400 sm:px-8">{{ auth()->user()->isSupport() ? 'ServiceDesk · IT Operations' : 'Need help? Your IT team is here for you.' }}</footer>
        </div>
    </div>
</body>
</html>
