<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#202248">
    <title>@yield('title', 'ServiceDesk') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen text-slate-800 antialiased">
    @php
        $user = auth()->user();
        $homeRoute = $user->isAdmin() ? 'admin.dashboard' : ($user->isSupport() ? 'support.dashboard' : 'employee.dashboard');
    @endphp
    <a href="#main-content" class="sr-only z-50 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-indigo-700 focus:not-sr-only focus:fixed focus:left-4 focus:top-4">Skip to content</a>
    <div class="min-h-screen lg:flex">
        <button id="sidebar-backdrop" type="button" class="fixed inset-0 z-30 hidden bg-slate-950/65 backdrop-blur-sm lg:hidden" aria-label="Close navigation"></button>
        <aside id="app-sidebar" class="fixed inset-y-0 left-0 z-40 flex w-[17rem] -translate-x-full flex-col border-r border-white/5 bg-gradient-to-b from-[#1d1f42] via-[#232345] to-[#191a36] text-slate-200 shadow-2xl shadow-slate-950/20 transition-transform duration-200 lg:static lg:translate-x-0 lg:shadow-none">
            <a href="{{ route($homeRoute) }}" class="group flex h-[5.4rem] items-center gap-3 border-b border-white/10 px-5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-violet-300">
                <span class="grid size-10 place-items-center rounded-xl bg-gradient-to-br from-indigo-400 to-violet-600 text-xs font-black tracking-wide text-white shadow-lg shadow-violet-950/40 ring-1 ring-white/15" aria-hidden="true">SD</span>
                <span><span class="block text-sm font-bold tracking-wide text-white">ServiceDesk</span><span class="mt-0.5 block text-[10px] font-semibold tracking-[.16em] text-indigo-200/65">IT SERVICE PORTAL</span></span>
            </a>

            <div class="px-5 pb-2 pt-6"><p class="text-[10px] font-bold uppercase tracking-[.18em] text-indigo-200/45">{{ $user->isAdmin() ? 'Administration' : ($user->isSupport() ? 'Support workspace' : 'My workspace') }}</p></div>
            @if ($user->isAdmin())
                <nav aria-label="Admin navigation" class="flex-1 space-y-1 overflow-y-auto px-3 py-2">
                    @foreach ([['admin.dashboard', 'Dashboard', 'M3 3h7v7H3z M14 3h7v7h-7z M14 14h7v7h-7z M3 14h7v7H3z'], ['tickets.index', 'Tickets', 'M8 6h13 M8 12h13 M8 18h13 M3 6h.01 M3 12h.01 M3 18h.01'], ['admin.users.index', 'Users & technicians', 'M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2 M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M20 21v-2a4 4 0 0 0-3-3.87'], ['admin.departments.index', 'Departments', 'M3 21h18 M5 21V7l8-4v18 M19 21V11l-6-4 M9 9v.01 M9 12v.01 M9 15v.01'], ['admin.categories.index', 'Categories', 'M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v10H3z'], ['admin.priorities.index', 'Priorities & SLA', 'm12 3 2.4 5 5.6.8-4 3.9.9 5.5-4.9-2.6L7.1 18.2l.9-5.5-4-3.9 5.6-.8z'], ['admin.statuses.index', 'Workflow statuses', 'M4 7h11 M4 12h16 M4 17h8 M17 5l3 2-3 2 M12 15l-3 2 3 2'], ['admin.reports.index', 'Reports & analytics', 'M3 3v18h18 M8 15v-3 M13 15V6 M18 15V9']] as [$route, $label, $icon])
                        <a href="{{ route($route) }}" @class(['group flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] font-semibold transition duration-150 focus-visible:outline focus-visible:outline-2 focus-visible:outline-violet-300', 'bg-white/10 text-white shadow-sm ring-1 ring-inset ring-white/10' => request()->routeIs($route, str_replace('.index', '.*', $route)), 'text-slate-300/75 hover:bg-white/[.07] hover:text-white' => ! request()->routeIs($route, str_replace('.index', '.*', $route))])>
                            <span @class(['grid size-8 place-items-center rounded-lg transition', 'bg-gradient-to-br from-indigo-400/25 to-violet-400/20 text-violet-100' => request()->routeIs($route, str_replace('.index', '.*', $route)), 'text-slate-400 group-hover:text-violet-200' => ! request()->routeIs($route, str_replace('.index', '.*', $route))]) aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="size-[18px]"><path d="{{ $icon }}" /></svg></span>{{ $label }}
                        </a>
                    @endforeach
                </nav>
            @elseif ($user->isSupport())
                <nav aria-label="Main navigation" class="flex-1 space-y-1 px-3 py-2">
                    @foreach ([['support.dashboard', 'Support dashboard', 'M3 3v18h18 M8 15v-3 M13 15V6 M18 15V9'], ['tickets.index', 'Ticket board', 'M8 6h13 M8 12h13 M8 18h13 M3 6h.01 M3 12h.01 M3 18h.01']] as [$route, $label, $icon])
                        <a href="{{ route($route) }}" @class(['group flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] font-semibold transition duration-150 focus-visible:outline focus-visible:outline-2 focus-visible:outline-violet-300', 'bg-white/10 text-white shadow-sm ring-1 ring-inset ring-white/10' => request()->routeIs($route), 'text-slate-300/75 hover:bg-white/[.07] hover:text-white' => ! request()->routeIs($route)])><span @class(['grid size-8 place-items-center rounded-lg transition', 'bg-gradient-to-br from-indigo-400/25 to-violet-400/20 text-violet-100' => request()->routeIs($route), 'text-slate-400 group-hover:text-violet-200' => ! request()->routeIs($route)]) aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="size-[18px]"><path d="{{ $icon }}" /></svg></span>{{ $label }}</a>
                    @endforeach
                </nav>
            @else
                <nav aria-label="Main navigation" class="flex-1 space-y-1 px-3 py-2">
                    @foreach ([['employee.dashboard', 'Dashboard', 'M3 3v18h18 M8 15v-3 M13 15V6 M18 15V9'], ['tickets.index', 'My tickets', 'M8 6h13 M8 12h13 M8 18h13 M3 6h.01 M3 12h.01 M3 18h.01'], ['tickets.create', 'Create a ticket', 'M12 5v14 M5 12h14']] as [$route, $label, $icon])
                        <a href="{{ route($route) }}" @class(['group flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13px] font-semibold transition duration-150 focus-visible:outline focus-visible:outline-2 focus-visible:outline-violet-300', 'bg-white/10 text-white shadow-sm ring-1 ring-inset ring-white/10' => request()->routeIs($route, $route === 'employee.dashboard' ? 'home' : $route), 'text-slate-300/75 hover:bg-white/[.07] hover:text-white' => ! request()->routeIs($route, $route === 'employee.dashboard' ? 'home' : $route)])><span @class(['grid size-8 place-items-center rounded-lg transition', 'bg-gradient-to-br from-indigo-400/25 to-violet-400/20 text-violet-100' => request()->routeIs($route, $route === 'employee.dashboard' ? 'home' : $route), 'text-slate-400 group-hover:text-violet-200' => ! request()->routeIs($route, $route === 'employee.dashboard' ? 'home' : $route)]) aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="size-[18px]"><path d="{{ $icon }}" /></svg></span>{{ $label }}</a>
                    @endforeach
                </nav>
            @endif

            <div class="border-t border-white/10 p-3">
                <div class="flex items-center gap-3 rounded-2xl border border-white/[.07] bg-white/[.055] p-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-gradient-to-br from-indigo-300 to-violet-400 text-sm font-bold text-[#242344] ring-2 ring-white/10">{{ $user->initials }}</span>
                    <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-white">{{ $user->name }}</p><p class="mt-0.5 truncate text-[11px] text-slate-300/60">{{ $user->role->label() }} · {{ $user->email }}</p></div>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="grid size-9 place-items-center rounded-xl text-slate-300/70 transition hover:bg-white/10 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-violet-300" aria-label="Sign out"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 17l5-5-5-5m5 5H3m9-9h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/></svg></button></form>
                </div>
            </div>
        </aside>

        <div class="flex min-h-screen min-w-0 flex-1 flex-col">
            <header class="sticky top-0 z-20 flex h-[4.5rem] items-center justify-between border-b border-slate-200/70 bg-white/80 px-4 shadow-[0_4px_20px_rgb(30_34_72/0.035)] backdrop-blur-xl sm:px-7">
                <div class="flex min-w-0 items-center gap-3">
                    <button id="sidebar-toggle" type="button" class="grid size-10 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-violet-200 hover:bg-violet-50 hover:text-violet-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-violet-600 lg:hidden" aria-controls="app-sidebar" aria-expanded="false" aria-label="Open navigation"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg></button>
                    <div class="min-w-0"><p class="hidden text-[10px] font-bold uppercase tracking-[.16em] text-violet-700 sm:block">{{ $user->isAdmin() ? 'ServiceDesk administration' : ($user->isSupport() ? 'IT operations' : 'Employee portal') }}</p><p class="truncate text-sm font-semibold text-slate-800">@yield('topline', 'Support center')</p></div>
                </div>
                <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                    @if ($user->isEmployee())<a href="{{ route('tickets.create') }}" class="ui-button-primary hidden sm:inline-flex"><span aria-hidden="true">+</span> New ticket</a>@endif
                    <x-notification-center :recent-notifications="$recentNotifications" :unread-count="$unreadNotificationCount" />
                </div>
            </header>
            <main id="main-content" class="mx-auto w-full max-w-[1500px] flex-1 px-4 py-6 sm:px-7 sm:py-8 xl:px-10">
                @if (session('status'))
                    <div class="ui-alert-success mb-6 flex items-start justify-between gap-4" role="status"><span>{{ session('status') }}</span><button type="button" data-dismiss class="rounded-lg px-2 py-1 text-emerald-800 transition hover:bg-emerald-100" aria-label="Dismiss message">×</button></div>
                @endif
                @if ($errors->any())
                    <div class="ui-alert-error mb-6" role="alert"><p class="font-semibold">Please check the highlighted fields.</p><ul class="mt-1 list-inside list-disc text-rose-800">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif
                @yield('content')
            </main>
            <footer class="px-4 pb-5 text-center text-[11px] font-medium tracking-wide text-slate-400 sm:px-8">{{ $user->isAdmin() || $user->isSupport() ? 'ServiceDesk · IT Operations' : 'Need help? Your IT team is here for you.' }}</footer>
        </div>
    </div>
</body>
</html>
