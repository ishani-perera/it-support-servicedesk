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
<section class="ui-card p-5 shadow-sm" aria-labelledby="ticket-sla-heading">
    <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 id="ticket-sla-heading" class="text-sm font-bold text-slate-950">{{ auth()->user()->isStaff() ? 'Service level' : 'Resolution target' }}</h2><p class="mt-1 text-xs text-slate-500">{{ auth()->user()->isStaff() ? 'Based on the current priority target' : 'Estimated from the ticket priority' }}</p></div><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $slaClass }}">{{ $slaLabel }}</span></div>
    <dl class="mt-4 space-y-3 text-sm"><div class="flex justify-between gap-3"><dt class="text-slate-500">{{ auth()->user()->isStaff() ? 'Resolution due' : 'Target date' }}</dt><dd class="text-right font-semibold text-slate-800">{{ $sla['resolution_due_at']->format('M j, Y g:i A') }}</dd></div><div class="flex justify-between gap-3"><dt class="text-slate-500">{{ $sla['completed_at'] ? 'Completed at' : ($sla['is_overdue'] ? 'Overdue by' : 'Time remaining') }}</dt><dd class="text-right font-medium text-slate-700">{{ $sla['completed_at'] ? $sla['completed_at']->format('M j, Y g:i A') : $sla['remaining'] }}</dd></div>
        @if (auth()->user()->isStaff())<div class="flex justify-between gap-3 border-t border-slate-100 pt-3"><dt class="text-slate-500">Response target</dt><dd class="text-right font-medium text-slate-700">{{ $sla['response_due_at']->format('M j, Y g:i A') }}</dd></div>@if ($ticket->resolved_at)<div class="flex justify-between gap-3"><dt class="text-slate-500">Resolved</dt><dd class="text-right font-medium text-slate-700">{{ $ticket->resolved_at->format('M j, Y g:i A') }}</dd></div>@endif @if ($ticket->closed_at)<div class="flex justify-between gap-3"><dt class="text-slate-500">Closed</dt><dd class="text-right font-medium text-slate-700">{{ $ticket->closed_at->format('M j, Y g:i A') }}</dd></div>@endif @endif
    </dl>
</section>
