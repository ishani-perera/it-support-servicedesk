@extends('layouts.app')

@section('title', 'IT Support dashboard')
@section('topline', 'Assigned workload and ticket activity')

@section('content')
    @php($statusStats = collect($stats)->filter(fn ($card) => isset($card['status'])))
    <section class="ui-page-hero mb-7 flex flex-col justify-between gap-6 sm:flex-row sm:items-center"><div><p class="text-xs font-bold uppercase tracking-[.17em] text-violet-200">IT Support operations</p><h1 class="mt-2 text-3xl font-bold tracking-tight text-white sm:text-[2.65rem]">Welcome back, {{ auth()->user()->name }}</h1><p class="mt-2 text-sm text-indigo-100/65">{{ auth()->user()->email }}</p><p class="mt-3 max-w-xl text-sm leading-6 text-indigo-100/75">Your authorized queue, active assignments, and tickets that need attention.</p></div><a href="{{ route('tickets.index') }}" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-bold text-indigo-800 shadow-lg shadow-slate-950/10 transition hover:-translate-y-0.5 hover:bg-violet-50">Open ticket board <span aria-hidden="true">→</span></a></section>

    <section aria-label="Ticket statistics" class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
        @foreach ($stats as $card)
            <a href="{{ route('tickets.index', array_filter(['status' => $card['status'] ?? null, 'assignment' => $card['assignment'] ?? null])) }}" class="group ui-stat-card">
                <span class="flex items-center justify-between gap-2"><span class="ui-stat-label">{{ $card['label'] }}</span><span class="ui-icon-tile size-9" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-[18px]"><path d="M3 3v18h18 M8 15v-3 M13 15V6 M18 15V9" /></svg></span></span><p class="ui-stat-value">{{ number_format($card['count']) }}</p><span class="relative z-10 mt-1 inline-block text-[11px] font-semibold text-violet-700 opacity-0 transition group-hover:opacity-100">Open queue →</span>
            </a>
        @endforeach
    </section>

    <div class="mt-7 grid gap-5 xl:grid-cols-[minmax(0,1.15fr)_minmax(18rem,.85fr)]">
        <section class="ui-card p-5 sm:p-6" aria-labelledby="queue-pulse-heading"><div class="flex items-start justify-between gap-4"><div><p class="ui-eyebrow">Workload pulse</p><h2 id="queue-pulse-heading" class="mt-1 text-lg font-bold text-slate-950">Tickets by status</h2><p class="mt-1 text-sm text-slate-500">Live counts from the queue you can access.</p></div><span class="ui-icon-tile" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5"><path d="M3 3v18h18 M8 15v-3 M13 15V6 M18 15V9" /></svg></span></div>
            <div class="mt-6 space-y-4">@foreach ($statusStats as $card)@php($ratio = (int) round(($card['count'] / max(1, $statusStats->max('count'))) * 100))<a href="{{ route('tickets.index', ['status' => $card['status']]) }}" class="group block rounded-xl p-2 transition hover:bg-violet-50/70"><span class="mb-2 flex justify-between gap-3 text-sm"><span class="font-medium text-slate-700 group-hover:text-violet-800">{{ $card['label'] }}</span><span class="font-bold tabular-nums text-slate-900">{{ $card['count'] }}</span></span><span class="ui-chart-track block"><span class="ui-chart-fill block transition-[width] duration-500" style="width: {{ $ratio }}%"></span></span></a>@endforeach</div>
        </section>
        <section class="ui-card overflow-hidden" aria-labelledby="attention-heading"><div class="border-b border-slate-100 bg-gradient-to-r from-white to-rose-50/70 px-5 py-5"><p class="text-[11px] font-bold uppercase tracking-[.15em] text-rose-700">Priority attention</p><h2 id="attention-heading" class="mt-1 text-lg font-bold text-slate-950">High-priority tickets</h2><p class="mt-1 text-sm text-slate-500">Urgent items from your authorized queue.</p></div>
            @forelse ($priorityTickets as $ticket)
                <a href="{{ route('tickets.show', $ticket) }}" class="group flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4 last:border-0 transition hover:bg-rose-50/40"><span class="min-w-0"><span class="block font-mono text-[11px] font-semibold text-slate-500">{{ $ticket->ticket_number }}</span><span class="mt-1 block truncate text-sm font-semibold text-slate-900 group-hover:text-violet-800">{{ $ticket->title }}</span><span class="mt-1 block truncate text-xs text-slate-500">{{ $ticket->user->name }} · {{ $ticket->category->name }}</span><span class="mt-2 block text-[11px] text-slate-500">{{ $ticket->currentAssignment?->assignee?->name ?? 'Unassigned' }} · {{ $ticket->updated_at->diffForHumans() }}</span></span><span class="shrink-0 text-right"><x-ticket-priority-badge :priority="$ticket->priority" /><span class="mt-2 block"><x-ticket-status-badge :status="$ticket->status" /></span></span></a>
            @empty
                <div class="ui-empty m-4"><span class="mx-auto grid size-10 place-items-center rounded-xl bg-emerald-100 text-emerald-700" aria-hidden="true">✓</span><p class="mt-3 text-sm font-semibold text-slate-800">No urgent tickets</p><p class="mt-1 text-xs text-slate-500">There are no high-priority items in your queue right now.</p></div>
            @endforelse
        </section>
    </div>

    <section class="ui-card mt-5 overflow-hidden" aria-labelledby="my-assigned-heading"><div class="flex items-center justify-between gap-4 border-b border-slate-100 bg-gradient-to-r from-white to-indigo-50/50 px-5 py-5 sm:px-6"><div><p class="ui-eyebrow">Your workload</p><h2 id="my-assigned-heading" class="mt-1 text-lg font-bold text-slate-950">Recently assigned to me</h2><p class="mt-1 text-sm text-slate-500">Your latest active assignments.</p></div><a href="{{ route('tickets.index', ['assignment' => 'mine']) }}" class="ui-button-secondary">View all</a></div>
        @forelse ($recentlyAssigned as $ticket)
            <a href="{{ route('tickets.show', $ticket) }}" class="group flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4 last:border-0 transition hover:bg-violet-50/45 sm:px-6"><span class="min-w-0"><span class="block truncate text-sm font-semibold text-slate-900 group-hover:text-violet-800">{{ $ticket->title }}</span><span class="mt-1 block truncate text-xs text-slate-500">{{ $ticket->ticket_number }} · {{ $ticket->user->name }} · {{ $ticket->category->name }}</span></span><span class="shrink-0 text-right"><x-ticket-status-badge :status="$ticket->status" /><span class="mt-1 block text-xs text-slate-500">{{ $ticket->updated_at->diffForHumans() }}</span></span></a>
        @empty
            <div class="ui-empty m-4"><p class="text-sm font-semibold text-slate-800">No current assignments</p><p class="mt-1 text-xs text-slate-500">Use the ticket board to claim an unassigned request.</p><a href="{{ route('tickets.index', ['assignment' => 'unassigned']) }}" class="ui-button-secondary mt-4">Browse unassigned</a></div>
        @endforelse
    </section>
@endsection
