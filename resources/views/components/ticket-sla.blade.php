@php
    $slaLabel = match ($sla['status']) {
        'met' => 'Met',
        'breached' => 'Missed',
        'overdue' => 'Overdue',
        default => 'On track',
    };
    $slaClass = match ($sla['status']) {
        'met', 'on_track' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/15',
        'breached', 'overdue' => 'bg-rose-50 text-rose-700 ring-rose-600/15',
        default => 'bg-slate-100 text-slate-700 ring-slate-600/15',
    };
@endphp
<section class="ui-card overflow-hidden" aria-labelledby="ticket-sla-heading">
    <div class="border-b border-slate-100 bg-gradient-to-r from-indigo-50/80 via-white to-violet-50/70 p-5"><div class="flex flex-wrap items-center justify-between gap-3"><div class="flex items-center gap-3"><span class="ui-icon-tile"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg></span><div><h2 id="ticket-sla-heading" class="text-sm font-bold text-slate-950">{{ auth()->user()->isStaff() ? 'Service level' : 'Resolution target' }}</h2><p class="mt-1 text-xs text-slate-500">{{ auth()->user()->isStaff() ? 'Based on the current priority target' : 'Estimated from the ticket priority' }}</p></div></div><span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold ring-1 ring-inset {{ $slaClass }}"><span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span>{{ $slaLabel }}</span></div></div>
    <dl class="space-y-3 p-5 text-sm"><div class="flex justify-between gap-3"><dt class="text-slate-500">{{ auth()->user()->isStaff() ? 'Resolution due' : 'Target date' }}</dt><dd class="text-right font-semibold text-slate-800">{{ $sla['resolution_due_at']->format('M j, Y g:i A') }}</dd></div><div class="flex justify-between gap-3 rounded-xl bg-slate-50 px-3 py-2.5"><dt class="text-slate-500">{{ $sla['completed_at'] ? 'Completed at' : ($sla['is_overdue'] ? 'Overdue by' : 'Time remaining') }}</dt><dd class="text-right font-bold text-slate-800">{{ $sla['completed_at'] ? $sla['completed_at']->format('M j, Y g:i A') : $sla['remaining'] }}</dd></div>
        @if (auth()->user()->isStaff())<div class="flex justify-between gap-3 border-t border-slate-100 pt-3"><dt class="text-slate-500">Response target</dt><dd class="text-right font-medium text-slate-700">{{ $sla['response_due_at']->format('M j, Y g:i A') }}</dd></div>@if ($ticket->resolved_at)<div class="flex justify-between gap-3"><dt class="text-slate-500">Resolved</dt><dd class="text-right font-medium text-slate-700">{{ $ticket->resolved_at->format('M j, Y g:i A') }}</dd></div>@endif @if ($ticket->closed_at)<div class="flex justify-between gap-3"><dt class="text-slate-500">Closed</dt><dd class="text-right font-medium text-slate-700">{{ $ticket->closed_at->format('M j, Y g:i A') }}</dd></div>@endif @endif
    </dl>
</section>
