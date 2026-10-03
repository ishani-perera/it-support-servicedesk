@extends('layouts.app')

@section('title', 'Admin dashboard')
@section('topline', 'Admin dashboard')

@section('content')
    <div class="mb-7 flex flex-wrap items-end justify-between gap-4">
        <div><p class="text-sm font-semibold text-indigo-700">Operations overview</p><h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">ServiceDesk administration</h1><p class="mt-2 text-sm text-slate-500">Live totals from current users and tickets.</p></div>
        <a href="{{ route('admin.reports.index') }}" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">View reports</a>
    </div>
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="ServiceDesk statistics">
        @foreach ([['Total users', $totalUsers], ['Active employees', $activeEmployees], ['Active IT Support', $activeSupport], ['Open tickets', $statusCounts[\App\Enums\TicketStatusSlug::Open->value]], ['In Progress', $statusCounts[\App\Enums\TicketStatusSlug::InProgress->value]], ['Waiting for User', $statusCounts[\App\Enums\TicketStatusSlug::WaitingForUser->value]], ['Resolved', $statusCounts[\App\Enums\TicketStatusSlug::Resolved->value]], ['Closed', $statusCounts[\App\Enums\TicketStatusSlug::Closed->value]], ['High / Critical', $highPriorityCount]] as [$label, $value])
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm font-medium text-slate-500">{{ $label }}</p><p class="mt-2 text-3xl font-bold tracking-tight text-slate-950">{{ number_format($value) }}</p></article>
        @endforeach
    </section>
    <div class="mt-7 grid gap-6 xl:grid-cols-2">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 class="font-bold text-slate-950">Recent tickets</h2><p class="mt-1 text-xs text-slate-500">Latest submissions</p></div><a href="{{ route('tickets.index') }}" class="text-sm font-semibold text-indigo-700">Open tickets</a></div>
            @forelse ($recentTickets as $ticket)
                <a href="{{ route('tickets.show', $ticket) }}" class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 last:border-0 hover:bg-slate-50"><span class="min-w-0"><span class="block font-mono text-xs text-slate-500">{{ $ticket->ticket_number }}</span><span class="mt-1 block truncate text-sm font-semibold text-slate-900">{{ $ticket->title }}</span></span><span class="text-right"><span class="block text-xs font-semibold text-slate-700">{{ $ticket->status->name }}</span><span class="mt-1 block text-xs text-slate-500">{{ $ticket->created_at->format('M j, Y') }}</span></span></a>
            @empty
                <p class="px-5 py-10 text-center text-sm text-slate-500">No tickets yet.</p>
            @endforelse
        </section>
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 class="font-bold text-slate-950">Recent account activity</h2><p class="mt-1 text-xs text-slate-500">Most recently changed user records</p></div><a href="{{ route('admin.users.index') }}" class="text-sm font-semibold text-indigo-700">Manage users</a></div>
            @forelse ($recentUsers as $user)
                <a href="{{ route('admin.users.show', $user) }}" class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4 last:border-0 hover:bg-slate-50"><span class="min-w-0"><span class="block truncate text-sm font-semibold text-slate-900">{{ $user->name }}</span><span class="mt-1 block truncate text-xs text-slate-500">{{ $user->role->label() }}{{ $user->department ? ' · '.$user->department->name : '' }}</span></span><span class="shrink-0 text-xs text-slate-500">{{ $user->updated_at->diffForHumans() }}</span></a>
            @empty
                <p class="px-5 py-10 text-center text-sm text-slate-500">No users found.</p>
            @endforelse
        </section>
    </div>
    <div class="mt-7 grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-bold text-slate-950">Ticket workflow</h2><div class="mt-4 space-y-3">@foreach ($statuses as $status)<div class="flex items-center justify-between gap-4"><span class="text-sm text-slate-700">{{ $status->name }}</span><span class="text-xs text-slate-500">{{ $status->is_active ? 'Active' : 'Inactive' }}</span></div>@endforeach</div><a href="{{ route('admin.statuses.index') }}" class="mt-5 inline-flex text-sm font-semibold text-indigo-700">View workflow configuration</a></section>
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-bold text-slate-950">Priority levels</h2><div class="mt-4 space-y-3">@foreach ($priorities as $priority)<div class="flex items-center justify-between gap-4"><span class="text-sm text-slate-700">{{ $priority->name }}</span><span class="text-xs text-slate-500">Level {{ $priority->level }}</span></div>@endforeach</div><a href="{{ route('admin.priorities.index') }}" class="mt-5 inline-flex text-sm font-semibold text-indigo-700">Manage priority settings</a></section>
    </div>
@endsection
