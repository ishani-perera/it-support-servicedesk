@extends('layouts.app')

@section('title', 'Dashboard')
@section('topline', 'Your support at a glance')

@section('content')
    @php($chartStats = $stats->reject(fn ($card) => $card['label'] === 'My tickets'))
    <section class="ui-page-hero mb-7 flex flex-col justify-between gap-6 sm:flex-row sm:items-center">
        <div class="max-w-2xl"><p class="text-xs font-bold uppercase tracking-[.17em] text-violet-200">Employee support portal</p><h1 class="mt-2 text-3xl font-bold tracking-tight text-white sm:text-[2.65rem]">How can we help?</h1><p class="mt-2 text-sm font-medium text-indigo-100/60">Welcome back, {{ auth()->user()->name }}</p><p class="mt-2 max-w-xl text-sm leading-6 text-indigo-100/75">Create a request, follow its progress, and keep all updates with your IT team in one place.</p></div>
        <a href="{{ route('tickets.create') }}" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-bold text-indigo-800 shadow-lg shadow-slate-950/10 transition hover:-translate-y-0.5 hover:bg-violet-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"><span class="grid size-6 place-items-center rounded-lg bg-violet-100 text-violet-700" aria-hidden="true">+</span> Create new ticket</a>
    </section>

    <section aria-label="Ticket summary" class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
        @foreach ($stats as $index => $card)
            @php($statIcons = ['M3 3v18h18 M8 15v-3 M13 15V6 M18 15V9', 'M4 5h16v14H4z M8 9h8 M8 13h5', 'M12 3v18 M3 12h18', 'M5 12l4 4L19 6', 'M12 8v4l3 2 M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20', 'M5 12h14 M12 5l7 7-7 7'])
            <a href="{{ route('tickets.index', isset($card['slug']) ? ['status' => $card['slug']] : []) }}" class="group ui-stat-card focus-visible:outline focus-visible:outline-2 focus-visible:outline-violet-600">
                <span class="flex items-center justify-between gap-2"><span class="ui-stat-label">{{ $card['label'] }}</span><span class="ui-icon-tile size-9" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-[18px]"><path d="{{ $statIcons[$index % count($statIcons)] }}" /></svg></span></span>
                <p class="ui-stat-value">{{ number_format($card['count']) }}</p>
                @if (isset($card['slug']))<span class="relative z-10 mt-1 inline-block text-[11px] font-semibold text-violet-700 opacity-0 transition group-hover:opacity-100">View matching tickets →</span>@endif
            </a>
        @endforeach
    </section>

    <div class="mt-7 grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(18rem,.65fr)]">
        <section class="ui-card p-5 sm:p-6" aria-labelledby="ticket-overview-heading">
            <div class="flex items-start justify-between gap-4"><div><p class="ui-eyebrow">Live overview</p><h2 id="ticket-overview-heading" class="mt-1 text-lg font-bold tracking-tight text-slate-950">Requests by status</h2><p class="mt-1 text-sm text-slate-500">A quick view of where your requests are in the workflow.</p></div><span class="ui-icon-tile" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-5"><path d="M3 3v18h18 M8 15v-3 M13 15V6 M18 15V9" /></svg></span></div>
            <div class="mt-6 grid gap-x-8 gap-y-4 sm:grid-cols-2">
                @foreach ($chartStats as $card)
                    @php($ratio = (int) round(($card['count'] / max(1, $chartStats->max('count'))) * 100))
                    <a href="{{ route('tickets.index', ['status' => $card['slug']]) }}" class="group min-w-0 rounded-xl p-2 transition hover:bg-violet-50/70 focus-visible:outline focus-visible:outline-2 focus-visible:outline-violet-500">
                        <span class="mb-2 flex items-center justify-between gap-3 text-sm"><span class="truncate font-medium text-slate-700 group-hover:text-violet-800">{{ $card['label'] }}</span><span class="font-bold tabular-nums text-slate-900">{{ $card['count'] }}</span></span><span class="ui-chart-track block" role="img" aria-label="{{ $card['count'] }} {{ Str::lower($card['label']) }} tickets"><span class="ui-chart-fill block transition-[width] duration-500" style="width: {{ $ratio }}%"></span></span>
                    </a>
                @endforeach
            </div>
        </section>
        <aside class="relative overflow-hidden rounded-2xl border border-violet-200/70 bg-gradient-to-br from-violet-50 via-white to-indigo-50 p-5 shadow-[0_8px_24px_rgb(67_56_202/0.06)] sm:p-6"><span class="absolute -right-5 -top-7 size-28 rounded-full bg-violet-100/80 blur-2xl" aria-hidden="true"></span><p class="ui-eyebrow relative">Need assistance?</p><h2 class="relative mt-2 text-lg font-bold text-slate-950">Give your IT team the details they need.</h2><p class="relative mt-2 text-sm leading-6 text-slate-600">Describe what happened, include any helpful context, and attach a screenshot if it helps explain the issue.</p><a href="{{ route('tickets.create') }}" class="ui-button-primary relative mt-5">Start a request <span aria-hidden="true">→</span></a></aside>
    </div>

    <section class="ui-card mt-7 overflow-hidden" aria-labelledby="recent-tickets-heading">
        <div class="flex flex-col gap-3 border-b border-slate-100 bg-gradient-to-r from-white to-slate-50/70 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6"><div><p class="ui-eyebrow">Your activity</p><h2 id="recent-tickets-heading" class="mt-1 text-lg font-bold text-slate-950">Recent tickets</h2><p class="mt-1 text-sm text-slate-500">Your latest requests and updates.</p></div><a href="{{ route('tickets.index') }}" class="ui-button-secondary">View all tickets <span aria-hidden="true">→</span></a></div>
        @if ($recentTickets->isEmpty())
            <div class="ui-empty m-5 sm:m-6"><span class="mx-auto grid size-12 place-items-center rounded-2xl bg-violet-100 text-violet-700" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v14H4z M8 9h8 M8 13h5" /></svg></span><h3 class="mt-4 font-semibold text-slate-900">No tickets yet</h3><p class="mt-1 text-sm text-slate-500">When you ask for help, your request will appear here.</p><a href="{{ route('tickets.create') }}" class="ui-button-primary mt-5">Create your first ticket</a></div>
        @else
            <div class="hidden overflow-x-auto md:block"><table class="w-full text-left text-sm"><thead class="ui-table-head"><tr><th class="px-6 py-3">Ticket</th><th class="px-4 py-3">Category</th><th class="px-4 py-3">Priority</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Updated</th><th class="px-6 py-3"><span class="sr-only">Action</span></th></tr></thead><tbody class="divide-y divide-slate-100">
                @foreach ($recentTickets as $ticket)
                    <tr class="ui-table-row"><td class="px-6 py-4"><a href="{{ route('tickets.show', $ticket) }}" class="font-semibold text-slate-900 hover:text-violet-700">{{ $ticket->title }}</a><p class="mt-1 font-mono text-[11px] text-slate-500">{{ $ticket->ticket_number }}</p></td><td class="px-4 py-4 text-slate-600">{{ $ticket->category->name }}</td><td class="px-4 py-4"><x-ticket-priority-badge :priority="$ticket->priority" /></td><td class="px-4 py-4"><x-ticket-status-badge :status="$ticket->status" /></td><td class="whitespace-nowrap px-4 py-4 text-xs text-slate-500">{{ $ticket->updated_at->format('M j, Y') }}</td><td class="px-6 py-4 text-right"><a href="{{ route('tickets.show', $ticket) }}" class="font-semibold text-violet-700 hover:text-violet-900">Open →</a></td></tr>
                @endforeach
            </tbody></table></div>
            <ul class="divide-y divide-slate-100 md:hidden">@foreach ($recentTickets as $ticket)<li class="p-4"><a href="{{ route('tickets.show', $ticket) }}" class="block rounded-xl p-1 focus-visible:outline focus-visible:outline-2 focus-visible:outline-violet-600"><span class="flex items-start justify-between gap-3"><span class="font-semibold text-slate-900">{{ $ticket->title }}</span><x-ticket-status-badge :status="$ticket->status" /></span><span class="mt-1 block text-xs text-slate-500">{{ $ticket->ticket_number }} · {{ $ticket->category->name }}</span><span class="mt-3 flex items-center justify-between"><x-ticket-priority-badge :priority="$ticket->priority" /><span class="text-xs text-slate-500">Updated {{ $ticket->updated_at->format('M j') }}</span></span></a></li>@endforeach</ul>
        @endif
    </section>
@endsection
