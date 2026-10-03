@extends('layouts.app')

@section('title', 'IT Support dashboard')
@section('topline', 'Assigned workload and ticket activity')

@section('content')
    <section class="mb-8 flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
        <div><p class="text-sm font-semibold text-indigo-700">IT Support workspace</p><h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">Good day, {{ auth()->user()->name }}</h1><p class="mt-1 text-xs text-slate-500">{{ auth()->user()->email }}</p><p class="mt-2 text-sm text-slate-500">Your authorized queue, current assignments, and tickets needing attention.</p></div>
        <a href="{{ route('tickets.index') }}" class="inline-flex items-center justify-center ui-button-primary px-5 py-3">Open ticket board</a>
    </section>

    <section aria-label="Ticket statistics" class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
        @foreach ($stats as $card)
            <a href="{{ route('tickets.index', array_filter(['status' => $card['status'] ?? null, 'assignment' => $card['assignment'] ?? null])) }}" class="ui-card p-4 shadow-sm transition hover:border-indigo-200 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600 sm:p-5">
                <span class="text-xs font-medium text-slate-500 sm:text-sm">{{ $card['label'] }}</span><p class="mt-4 text-3xl font-bold tracking-tight text-slate-950">{{ $card['count'] }}</p>
            </a>
        @endforeach
    </section>

    <div class="mt-9 grid gap-6 xl:grid-cols-2">
        <section class="ui-card overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><div><h2 class="font-bold text-slate-950">Recently assigned to me</h2><p class="mt-1 text-xs text-slate-500">Your latest current assignments</p></div><a href="{{ route('tickets.index', ['assignment' => 'mine']) }}" class="text-sm font-semibold text-indigo-700">View all</a></div>
            @forelse ($recentlyAssigned as $ticket)
                <a href="{{ route('tickets.show', $ticket) }}" class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4 last:border-0 hover:bg-slate-50"><span class="min-w-0"><span class="block truncate text-sm font-semibold text-slate-900">{{ $ticket->title }}</span><span class="mt-1 block text-xs text-slate-500">{{ $ticket->ticket_number }} · {{ $ticket->user->name }} · {{ $ticket->category->name }}</span></span><span class="shrink-0 text-right"><x-ticket-status-badge :status="$ticket->status" /><span class="mt-1 block text-xs text-slate-500">{{ $ticket->updated_at->diffForHumans() }}</span></span></a>
            @empty
                <p class="px-5 py-12 text-center text-sm text-slate-500">You have no current ticket assignments.</p>
            @endforelse
        </section>
        <section class="ui-card overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-bold text-slate-950">Priority attention</h2><p class="mt-1 text-xs text-slate-500">High and urgent tickets from your authorized queue</p></div>
            @forelse ($priorityTickets as $ticket)
                <a href="{{ route('tickets.show', $ticket) }}" class="flex items-center justify-between gap-4 border-b border-slate-100 px-5 py-4 last:border-0 hover:bg-slate-50"><span class="min-w-0"><span class="block truncate text-sm font-semibold text-slate-900">{{ $ticket->ticket_number }} · {{ $ticket->title }}</span><span class="mt-1 block text-xs text-slate-500">{{ $ticket->user->name }} · {{ $ticket->category->name }} · {{ $ticket->currentAssignment?->assignee?->name ?? 'Unassigned' }}</span></span><span class="shrink-0 text-right"><x-ticket-priority-badge :priority="$ticket->priority" /><span class="mt-1 block text-xs text-slate-500">{{ $ticket->updated_at->diffForHumans() }}</span></span></a>
            @empty
                <p class="px-5 py-12 text-center text-sm text-slate-500">No high-priority tickets need attention.</p>
            @endforelse
        </section>
    </div>
@endsection
