@extends('layouts.app')

@section('title', $ticket->ticket_number)
@section('topline', 'Ticket details')

@section('content')
    <a href="{{ route('tickets.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600"><span aria-hidden="true">←</span> Back to my tickets</a>
    <div class="mt-5 grid gap-6 xl:grid-cols-[minmax(0,1fr)_300px]">
        <div class="min-w-0 space-y-6">
            <section class="ui-card p-5 shadow-sm sm:p-7">
                <div class="flex flex-wrap items-center gap-2"><span class="font-mono text-sm font-semibold text-slate-500">{{ $ticket->ticket_number }}</span><x-ticket-status-badge :status="$ticket->status" /><x-ticket-priority-badge :priority="$ticket->priority" /></div>
                <h1 class="mt-3 break-words text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">{{ $ticket->title }}</h1>
                <p class="mt-3 text-sm leading-6 text-slate-500">Submitted {{ $ticket->created_at->format('M j, Y \a\t g:i A') }} <span class="px-1 text-slate-300">·</span> Updated {{ $ticket->updated_at->format('M j, Y \a\t g:i A') }}</p>
                <div class="mt-6 border-t border-slate-100 pt-5"><h2 class="text-xs font-bold uppercase tracking-wider text-slate-500">Description</h2><div class="mt-3 whitespace-pre-line break-words text-sm leading-7 text-slate-700">{{ $ticket->description }}</div></div>
                @if ($ticket->resolution)<div class="mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4" aria-labelledby="ticket-resolution-heading"><h2 id="ticket-resolution-heading" class="text-sm font-bold text-emerald-950">Resolution from IT</h2><p class="mt-2 whitespace-pre-line break-words text-sm leading-6 text-emerald-900">{{ $ticket->resolution }}</p>@if ($ticket->resolved_at)<p class="mt-3 text-xs text-emerald-800">Resolved {{ $ticket->resolved_at->format('M j, Y \\a\\t g:i A') }}</p>@endif</div>@endif
            </section>

            <x-ticket-conversation :ticket="$ticket" :comments="$comments" :attachments="$attachments" />
        </div>

        <aside class="h-fit space-y-5 xl:sticky xl:top-24">
            <x-ticket-sla :ticket="$ticket" :sla="$sla" />
            <section class="ui-card overflow-hidden">
                <div class="ui-panel-heading"><span class="ui-icon-tile" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 4 7v5c0 5 3.5 8 8 9 4.5-1 8-4 8-9V7l-8-4Zm-3 9 2 2 4-4"/></svg></span><div><h2 class="text-sm font-bold text-slate-950">Ticket information</h2><p class="mt-1 text-xs text-slate-500">Request ownership and classification</p></div></div>
                <dl class="space-y-4 p-5">
                    <div class="flex items-start justify-between gap-3"><dt class="text-xs font-medium text-slate-500">Status</dt><dd><x-ticket-status-badge :status="$ticket->status" /></dd></div>
                    <div class="flex items-start justify-between gap-3"><dt class="text-xs font-medium text-slate-500">Priority</dt><dd><x-ticket-priority-badge :priority="$ticket->priority" /></dd></div>
                    <div class="border-t border-slate-100 pt-3"><dt class="text-xs font-medium text-slate-500">Category</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ $ticket->category->name }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Department</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ $ticket->department->name }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Requested by</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ $ticket->user->name }}</dd></div>
                    <div><dt class="text-xs font-medium text-slate-500">Assigned technician</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ $ticket->currentAssignment?->assignee?->name ?? 'Not assigned yet' }}</dd></div>
                    <div class="rounded-xl bg-slate-50 px-3 py-2.5"><dt class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Ticket number</dt><dd class="mt-1 font-mono text-sm font-semibold text-indigo-800">{{ $ticket->ticket_number }}</dd></div>
                </dl>
                <div class="mx-4 mb-4 rounded-xl border border-indigo-100 bg-gradient-to-r from-indigo-50 to-violet-50 p-4"><p class="text-sm font-semibold text-indigo-950">Need to add more detail?</p><p class="mt-1 text-xs leading-5 text-indigo-800">Reply in the conversation and the IT team will see your update.</p></div>
            </section>
        </aside>
    </div>
@endsection
