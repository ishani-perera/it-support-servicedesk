@extends('layouts.app')

@section('title', 'Tickets')
@section('topline', 'Ticket administration')

@section('content')
    <section class="ui-page-toolbar mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div><p class="ui-eyebrow">Service operations</p><h1 class="ui-page-title text-3xl">All tickets</h1><p class="mt-2 text-sm text-slate-500">Search and review tickets across the organization.</p></div>
        <p class="rounded-xl bg-indigo-50 px-4 py-3 text-sm font-semibold text-indigo-800">{{ $tickets->total() }} {{ Str::plural('ticket', $tickets->total()) }}</p>
    </section>

    <section class="mb-5 ui-card p-4 shadow-sm sm:p-5" aria-label="Search and filter tickets">
        <form method="GET" action="{{ route('tickets.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <label class="sm:col-span-2"><span class="mb-1.5 block text-xs font-semibold text-slate-600">Search</span><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Ticket number, title, requester" class="w-full text-sm"></label>
            <label><span class="mb-1.5 block text-xs font-semibold text-slate-600">Status</span><select name="status" class="w-full text-sm"><option value="">All statuses</option>@foreach ($statuses as $status)<option value="{{ $status->slug }}" @selected(($filters['status'] ?? '') === $status->slug)>{{ $status->name }}</option>@endforeach</select></label>
            <label><span class="mb-1.5 block text-xs font-semibold text-slate-600">Priority</span><select name="priority_id" class="w-full text-sm"><option value="">All priorities</option>@foreach ($priorities as $priority)<option value="{{ $priority->id }}" @selected((string) ($filters['priority_id'] ?? '') === (string) $priority->id)>{{ $priority->name }}</option>@endforeach</select></label>
            <label><span class="mb-1.5 block text-xs font-semibold text-slate-600">Category</span><select name="category_id" class="w-full text-sm"><option value="">All categories</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></label>
            <label><span class="mb-1.5 block text-xs font-semibold text-slate-600">Sort by</span><select name="sort" class="w-full text-sm"><option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Newest first</option><option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Oldest first</option><option value="updated" @selected(($filters['sort'] ?? '') === 'updated')>Recently updated</option><option value="priority" @selected(($filters['sort'] ?? '') === 'priority')>Highest priority</option></select></label>
            <div class="flex items-end gap-2 sm:col-span-2 xl:col-span-5"><button type="submit" class="ui-button-primary">Apply filters</button><a href="{{ route('tickets.index') }}" class="ui-button-secondary">Clear</a></div>
        </form>
    </section>

    <section class="ui-card overflow-hidden">
        @if ($tickets->isEmpty())
            <div class="ui-empty m-4"><span class="mx-auto grid size-12 place-items-center rounded-2xl bg-white text-slate-500 shadow-sm" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16M7 12h10m-7 7h4" /></svg></span><h2 class="mt-4 text-sm font-bold text-slate-900">No tickets match these filters</h2><p class="mt-1 text-sm text-slate-500">Try broadening your search or clearing the filters.</p><a href="{{ route('tickets.index') }}" class="mt-4 inline-flex ui-button-secondary">Clear filters</a></div>
        @else
            <div class="overflow-x-auto"><table class="w-full min-w-[850px] text-left text-sm"><thead class="ui-table-head"><tr><th class="px-6 py-3">Ticket / requester</th><th class="px-4 py-3">Category</th><th class="px-4 py-3">Priority</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Updated</th><th class="px-6 py-3"><span class="sr-only">Open ticket</span></th></tr></thead><tbody class="divide-y divide-slate-100">
                @foreach ($tickets as $ticket)
                    <tr class="transition hover:bg-slate-50"><td class="px-6 py-4"><a href="{{ route('tickets.show', $ticket) }}" class="font-semibold text-slate-900 hover:text-indigo-700">{{ $ticket->ticket_number }} · {{ $ticket->title }}</a><span class="mt-1 block text-xs text-slate-500">{{ $ticket->user->name }}</span></td><td class="px-4 py-4 text-slate-600">{{ $ticket->category->name }}</td><td class="px-4 py-4"><x-ticket-priority-badge :priority="$ticket->priority" /></td><td class="px-4 py-4"><x-ticket-status-badge :status="$ticket->status" /></td><td class="whitespace-nowrap px-4 py-4 text-slate-500">{{ $ticket->updated_at->format('M j, Y') }}</td><td class="whitespace-nowrap px-6 py-4 text-right"><a href="{{ route('tickets.show', $ticket) }}" class="font-semibold text-indigo-700 hover:text-indigo-900">Open ticket <span aria-hidden="true">→</span></a></td></tr>
                @endforeach
            </tbody></table></div>
            <div class="border-t border-slate-100 px-4 py-4 sm:px-6">{{ $tickets->links() }}</div>
        @endif
    </section>
@endsection
