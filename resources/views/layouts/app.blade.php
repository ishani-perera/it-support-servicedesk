<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f6f7fb">
    <title>@yield('title', 'ServiceDesk') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <div class="min-h-screen lg:flex">
        <button id="sidebar-backdrop" type="button" class="fixed inset-0 z-30 hidden bg-slate-950/50 backdrop-blur-sm lg:hidden" aria-label="Close navigation"></button>
        <aside id="app-sidebar" class="fixed inset-y-0 left-0 z-40 flex w-[17rem] -translate-x-full flex-col border-r border-slate-200/80 bg-white shadow-xl transition-transform duration-200 lg:static lg:translate-x-0 lg:shadow-none">
            <a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : (auth()->user()->isSupport() ? route('support.dashboard') : route('employee.dashboard')) }}" class="flex h-[5.25rem] items-center gap-3 border-b border-slate-100 px-5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600">
                <span class="grid size-10 place-items-center rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 text-sm font-black text-white shadow-md shadow-indigo-900/15" aria-hidden="true">SD</span>
                <span><span class="block text-sm font-bold tracking-tight text-slate-950">ServiceDesk</span><span class="mt-0.5 block text-[11px] font-medium tracking-wide text-slate-500">IT SERVICE PORTAL</span></span>
            </a>
            <div class="px-5 pt-6 pb-2"><p class="text-[10px] font-bold uppercase tracking-[.16em] text-slate-400">{{ auth()->user()->isAdmin() ? 'Administration' : (auth()->user()->isSupport() ? 'Workspace' : 'My workspace') }}</p></div>
            @if (auth()->user()->isAdmin())
                <nav aria-label="Admin navigation" class="flex-1 space-y-1 overflow-y-auto px-3 py-2">
                    @foreach ([['admin.dashboard', 'Dashboard'], ['admin.users.*', 'Users'], ['admin.departments.*', 'Departments'], ['admin.categories.*', 'Categories'], ['admin.priorities.*', 'Priorities'], ['admin.statuses.*', 'Workflow statuses'], ['admin.reports.*', 'Reports']] as [$pattern, $label])
                        <a href="{{ $pattern === 'admin.dashboard' ? route('admin.dashboard') : route(str_replace('.*', '.index', $pattern)) }}" @class(['flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600', 'bg-indigo-50 text-indigo-800 ring-1 ring-inset ring-indigo-100' => request()->routeIs($pattern), 'text-slate-600 hover:bg-slate-50 hover:text-slate-950' => ! request()->routeIs($pattern)])>
                            <span class="grid size-7 place-items-center rounded-lg {{ request()->routeIs($pattern) ? 'bg-white text-indigo-700 shadow-sm' : 'bg-slate-100 text-slate-500' }}" aria-hidden="true">{{ mb_substr($label, 0, 1) }}</span>{{ $label }}
                        </a>
                    @endforeach
                </nav>
            @elseif (auth()->user()->isSupport())
                <nav aria-label="Main navigation" class="flex-1 space-y-1 px-3 py-2">
                    <a href="{{ route('support.dashboard') }}" @class(['flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600', 'bg-indigo-50 text-indigo-800 ring-1 ring-inset ring-indigo-100' => request()->routeIs('support.dashboard'), 'text-slate-600 hover:bg-slate-50 hover:text-slate-950' => ! request()->routeIs('support.dashboard')])><span class="grid size-7 place-items-center rounded-lg bg-slate-100 text-slate-500" aria-hidden="true">D</span>Support dashboard</a>
                    <a href="{{ route('tickets.index') }}" @class(['flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600', 'bg-indigo-50 text-indigo-800 ring-1 ring-inset ring-indigo-100' => request()->routeIs('tickets.index'), 'text-slate-600 hover:bg-slate-50 hover:text-slate-950' => ! request()->routeIs('tickets.index')])><span class="grid size-7 place-items-center rounded-lg bg-slate-100 text-slate-500" aria-hidden="true">T</span>Ticket board</a>
                </nav>
            @else
                <nav aria-label="Main navigation" class="flex-1 space-y-1 px-3 py-2">
                    <a href="{{ route('employee.dashboard') }}" @class(['flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600', 'bg-indigo-50 text-indigo-800 ring-1 ring-inset ring-indigo-100' => request()->routeIs('employee.dashboard', 'home'), 'text-slate-600 hover:bg-slate-50 hover:text-slate-950' => ! request()->routeIs('employee.dashboard', 'home')])><span class="grid size-7 place-items-center rounded-lg bg-slate-100 text-slate-500" aria-hidden="true">D</span>Dashboard</a>
                    <a href="{{ route('tickets.index') }}" @class(['flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600', 'bg-indigo-50 text-indigo-800 ring-1 ring-inset ring-indigo-100' => request()->routeIs('tickets.index'), 'text-slate-600 hover:bg-slate-50 hover:text-slate-950' => ! request()->routeIs('tickets.index')])><span class="grid size-7 place-items-center rounded-lg bg-slate-100 text-slate-500" aria-hidden="true">T</span>My tickets</a>
                    <a href="{{ route('tickets.create') }}" @class(['flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-sm font-semibold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600', 'bg-indigo-50 text-indigo-800 ring-1 ring-inset ring-indigo-100' => request()->routeIs('tickets.create'), 'text-slate-600 hover:bg-slate-50 hover:text-slate-950' => ! request()->routeIs('tickets.create')])><span class="grid size-7 place-items-center rounded-lg bg-slate-100 text-slate-500" aria-hidden="true">+</span>Create a ticket</a>
                </nav>
            @endif
            <div class="border-t border-slate-100 p-3">
                <div class="flex items-center gap-3 rounded-xl bg-slate-50 p-3 ring-1 ring-inset ring-slate-100">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-800 ring-2 ring-white">{{ auth()->user()->initials }}</span>
                    <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-slate-900">{{ auth()->user()->name }}</p><p class="mt-0.5 text-xs text-slate-500">{{ auth()->user()->role->label() }}</p></div>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="grid size-9 place-items-center rounded-lg text-slate-500 transition hover:bg-white hover:text-rose-700" aria-label="Sign out"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 17l5-5-5-5m5 5H3m9-9h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/></svg></button></form>
                </div>
            </div>
        </aside>
        <div class="flex min-h-screen min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-20 flex h-[4.25rem] items-center justify-between border-b border-slate-200/80 bg-white/90 px-4 backdrop-blur-xl sm:px-7">
                <div class="flex min-w-0 items-center gap-3">
                    <button id="sidebar-toggle" type="button" class="grid size-10 shrink-0 place-items-center rounded-xl text-slate-600 transition hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600 lg:hidden" aria-controls="app-sidebar" aria-expanded="false" aria-label="Open navigation"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg></button>
                    <div class="min-w-0"><p class="hidden text-[10px] font-bold uppercase tracking-[.15em] text-slate-400 sm:block">{{ auth()->user()->isAdmin() ? 'ServiceDesk administration' : (auth()->user()->isSupport() ? 'IT Support workspace' : 'Employee portal') }}</p><p class="truncate text-sm font-semibold text-slate-800">@yield('topline', 'Support center')</p></div>
                </div>
                <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                    @if (auth()->user()->isEmployee())<a href="{{ route('tickets.create') }}" class="ui-button-primary hidden sm:inline-flex">New ticket <span aria-hidden="true">+</span></a>@endif
                    <x-notification-center :recent-notifications="$recentNotifications" :unread-count="$unreadNotificationCount" />
                </div>
            </header>
            <main class="mx-auto w-full max-w-[1440px] flex-1 px-4 py-6 sm:px-7 sm:py-8 lg:px-9">
                @if (session('status'))
                    <div class="mb-6 flex items-start justify-between gap-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3.5 text-sm text-emerald-950 shadow-sm" role="status"><span>{{ session('status') }}</span><button type="button" data-dismiss class="rounded-lg px-2 py-1 text-emerald-800 transition hover:bg-emerald-100" aria-label="Dismiss message">×</button></div>
                @endif
                @if ($errors->any())
                    <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3.5 text-sm text-rose-950 shadow-sm" role="alert"><p class="font-semibold">Please check the highlighted fields.</p><ul class="mt-1 list-inside list-disc text-rose-800">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif
                @yield('content')
            </main>
            <footer class="px-4 pb-6 text-center text-xs text-slate-400 sm:px-8">{{ auth()->user()->isAdmin() || auth()->user()->isSupport() ? 'ServiceDesk · IT Operations' : 'Need help? Your IT team is here for you.' }}</footer>
        </div>
    </div>
</body>
</html>
