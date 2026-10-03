@extends('layouts.app')

@section('title', 'Dashboard')
@section('topline', 'Your support at a glance')

@section('content')
    <section class="mb-8 flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
        <div><p class="text-sm font-semibold text-indigo-700">Welcome back, {{ auth()->user()->name }}</p><h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">How can we help?</h1><p class="mt-2 max-w-xl text-sm leading-6 text-slate-500">Track a request or tell our IT team what you need. We’ll keep everything in one place.</p></div>
        <a href="{{ route('tickets.create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"><span aria-hidden="true">＋</span> Create new ticket</a>
    </section>

    <section aria-label="Ticket summary" class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
        @foreach ($stats as $card)
            <a href="{{ route('tickets.index', isset($card['slug']) ? ['status' => $card['slug']] : []) }}" class="group ui-card p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600 sm:p-5">
                <div class="flex items-center justify-between gap-2"><span class="text-xs font-medium text-slate-500 sm:text-sm">{{ $card['label'] }}</span><span class="grid size-8 place-items-center rounded-lg bg-indigo-50 text-indigo-700" aria-hidden="true">▤</span></div>
                <p class="mt-4 text-3xl font-bold tracking-tight text-slate-950">{{ $card['count'] }}</p>
            </a>
        @endforeach
    </section>

    <section class="mt-9 ui-card overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div><h2 class="text-lg font-bold text-slate-950">Recent tickets</h2><p class="mt-1 text-sm text-slate-500">Your latest activity and requests.</p></div>
            <a href="{{ route('tickets.index') }}" class="text-sm font-semibold text-indigo-700 hover:text-indigo-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600">View all tickets <span aria-hidden="true">→</span></a>
        </div>
        @if ($recentTickets->isEmpty())
            <div class="px-6 py-14 text-center"><span class="mx-auto grid size-12 place-items-center rounded-2xl bg-indigo-50 text-xl text-indigo-700" aria-hidden="true">▤</span><h3 class="mt-4 font-semibold text-slate-900">No tickets yet</h3><p class="mt-1 text-sm text-slate-500">When you ask for help, your request will appear here.</p><a href="{{ route('tickets.create') }}" class="mt-5 inline-flex ui-button-primary focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Create your first ticket</a></div>
        @else
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full text-left text-sm"><thead class="ui-table-head"><tr><th class="px-6 py-3 font-semibold">Ticket</th><th class="px-4 py-3 font-semibold">Category</th><th class="px-4 py-3 font-semibold">Priority</th><th class="px-4 py-3 font-semibold">Status</th><th class="px-4 py-3 font-semibold">Updated</th><th class="px-6 py-3"><span class="sr-only">Action</span></th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($recentTickets as $ticket)
                            <tr class="transition hover:bg-slate-50/80"><td class="px-6 py-4"><a href="{{ route('tickets.show', $ticket) }}" class="font-semibold text-slate-900 hover:text-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600">{{ $ticket->title }}</a><p class="mt-1 text-xs font-medium text-slate-500">{{ $ticket->ticket_number }}</p></td><td class="px-4 py-4 text-slate-600">{{ $ticket->category->name }}</td><td class="px-4 py-4"><x-ticket-priority-badge :priority="$ticket->priority" /></td><td class="px-4 py-4"><x-ticket-status-badge :status="$ticket->status" /></td><td class="whitespace-nowrap px-4 py-4 text-slate-500">{{ $ticket->updated_at->format('M j, Y') }}</td><td class="px-6 py-4 text-right"><a href="{{ route('tickets.show', $ticket) }}" class="font-semibold text-indigo-700 hover:text-indigo-900">View <span aria-hidden="true">→</span></a></td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <ul class="divide-y divide-slate-100 md:hidden">
                @foreach ($recentTickets as $ticket)
                    <li class="p-4"><a href="{{ route('tickets.show', $ticket) }}" class="block rounded-xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600"><span class="flex items-start justify-between gap-3"><span class="font-semibold text-slate-900">{{ $ticket->title }}</span><x-ticket-status-badge :status="$ticket->status" /></span><span class="mt-1 block text-xs font-medium text-slate-500">{{ $ticket->ticket_number }} · {{ $ticket->category->name }}</span><span class="mt-3 flex items-center justify-between"><x-ticket-priority-badge :priority="$ticket->priority" /><span class="text-xs text-slate-500">Updated {{ $ticket->updated_at->format('M j') }}</span></span></a></li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
