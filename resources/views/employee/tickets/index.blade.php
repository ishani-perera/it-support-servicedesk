@extends('layouts.app')

@section('title', 'My tickets')
@section('topline', 'Your requests')

@section('content')
    @php($hasFilters = collect(['search', 'status', 'priority_id', 'category_id'])->contains(fn ($key) => request()->filled($key)))
    <section class="mb-7 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div><p class="text-sm font-semibold text-indigo-700">Support requests</p><h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-950">My tickets</h1><p class="mt-2 text-sm text-slate-500">Follow progress and find any request you’ve sent to IT.</p></div>
        <a href="{{ route('tickets.create') }}" class="inline-flex items-center justify-center gap-2 ui-button-primary px-5 py-3">＋ Create ticket</a>
    </section>

    <section class="mb-5 ui-card p-4 shadow-sm sm:p-5" aria-label="Search and filter tickets">
        <form method="GET" action="{{ route('tickets.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-6">
            <label class="sm:col-span-2"><span class="mb-1.5 block text-xs font-semibold text-slate-600">Search tickets</span><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Ticket number or title" class="w-full rounded-lg border-slate-300 text-sm shadow-sm placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500"></label>
            <label><span class="mb-1.5 block text-xs font-semibold text-slate-600">Status</span><select name="status" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"><option value="">All statuses</option>@foreach ($statuses as $status)<option value="{{ $status->slug }}" @selected(($filters['status'] ?? '') === $status->slug)>{{ $status->name }}</option>@endforeach</select></label>
            <label><span class="mb-1.5 block text-xs font-semibold text-slate-600">Priority</span><select name="priority_id" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"><option value="">All priorities</option>@foreach ($priorities as $priority)<option value="{{ $priority->id }}" @selected((string) ($filters['priority_id'] ?? '') === (string) $priority->id)>{{ $priority->name }}</option>@endforeach</select></label>
            <label><span class="mb-1.5 block text-xs font-semibold text-slate-600">Category</span><select name="category_id" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"><option value="">All categories</option>@foreach ($categories as $category)<option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select></label>
            <label><span class="mb-1.5 block text-xs font-semibold text-slate-600">Sort by</span><select name="sort" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"><option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Newest first</option><option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Oldest first</option><option value="updated" @selected(($filters['sort'] ?? '') === 'updated')>Recently updated</option></select></label>
            <div class="flex items-end gap-2 sm:col-span-2 xl:col-span-6"><button type="submit" class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900 hover:bg-slate-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900">Apply filters</button><a href="{{ route('tickets.index') }}" class="ui-button-secondary focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600">Clear</a><span class="ml-auto pb-2 text-xs text-slate-500">{{ $tickets->total() }} {{ Str::plural('ticket', $tickets->total()) }}</span></div>
        </form>
    </section>

    <section class="ui-card overflow-hidden">
        @if ($tickets->isEmpty())
            <div class="px-6 py-16 text-center"><span class="mx-auto grid size-12 place-items-center rounded-2xl bg-slate-100 text-xl text-slate-600" aria-hidden="true">⌕</span><h2 class="mt-4 text-lg font-bold text-slate-900">{{ $hasFilters ? 'No matching tickets' : 'Nothing here yet' }}</h2><p class="mt-1 text-sm text-slate-500">{{ $hasFilters ? 'Try changing or clearing your filters.' : 'Create a ticket and your requests will be listed here.' }}</p>@if ($hasFilters)<a href="{{ route('tickets.index') }}" class="mt-5 inline-flex ui-button-secondary">Clear filters</a>@else<a href="{{ route('tickets.create') }}" class="mt-5 inline-flex ui-button-primary">Create a ticket</a>@endif</div>
        @else
            <div class="hidden overflow-x-auto md:block"><table class="w-full text-left text-sm"><thead class="ui-table-head"><tr><th class="px-6 py-3 font-semibold">Ticket</th><th class="px-4 py-3 font-semibold">Category</th><th class="px-4 py-3 font-semibold">Priority</th><th class="px-4 py-3 font-semibold">Status</th><th class="px-4 py-3 font-semibold">Created</th><th class="px-4 py-3 font-semibold">Updated</th><th class="px-6 py-3"><span class="sr-only">Action</span></th></tr></thead><tbody class="divide-y divide-slate-100">
                @foreach ($tickets as $ticket)
                    <tr class="hover:bg-slate-50/80"><td class="px-6 py-4"><a href="{{ route('tickets.show', $ticket) }}" class="font-semibold text-slate-900 hover:text-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600">{{ $ticket->title }}</a><p class="mt-1 text-xs font-medium text-slate-500">{{ $ticket->ticket_number }}</p></td><td class="px-4 py-4 text-slate-600">{{ $ticket->category->name }}</td><td class="px-4 py-4"><x-ticket-priority-badge :priority="$ticket->priority" /></td><td class="px-4 py-4"><x-ticket-status-badge :status="$ticket->status" /></td><td class="whitespace-nowrap px-4 py-4 text-slate-500">{{ $ticket->created_at->format('M j, Y') }}</td><td class="whitespace-nowrap px-4 py-4 text-slate-500">{{ $ticket->updated_at->format('M j, Y') }}</td><td class="px-6 py-4 text-right"><a href="{{ route('tickets.show', $ticket) }}" class="font-semibold text-indigo-700 hover:text-indigo-900">View <span aria-hidden="true">→</span></a></td></tr>
                @endforeach
            </tbody></table></div>
            <ul class="divide-y divide-slate-100 md:hidden">
                @foreach ($tickets as $ticket)
                    <li class="p-4"><a href="{{ route('tickets.show', $ticket) }}" class="block rounded-xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600"><span class="flex items-start justify-between gap-3"><span class="font-semibold text-slate-900">{{ $ticket->title }}</span><x-ticket-status-badge :status="$ticket->status" /></span><span class="mt-1 block text-xs font-medium text-slate-500">{{ $ticket->ticket_number }} · {{ $ticket->category->name }}</span><span class="mt-3 flex items-center justify-between"><x-ticket-priority-badge :priority="$ticket->priority" /><span class="text-xs text-slate-500">Updated {{ $ticket->updated_at->format('M j, Y') }}</span></span></a></li>
                @endforeach
            </ul>
            <div class="border-t border-slate-100 px-4 py-4 sm:px-6">{{ $tickets->links() }}</div>
        @endif
    </section>
@endsection
