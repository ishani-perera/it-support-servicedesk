@extends('layouts.app')

@section('title', $ticket->ticket_number)
@section('topline', 'Ticket detail')

@section('content')
    <a href="{{ route('tickets.index') }}" class="text-sm font-semibold text-indigo-700 hover:text-indigo-900">← Back to support queue</a>
    <section class="mt-4 grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="min-w-0 space-y-6">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="flex flex-wrap items-center gap-2"><span class="text-sm font-bold text-slate-500">{{ $ticket->ticket_number }}</span><x-ticket-status-badge :status="$ticket->status" /><x-ticket-priority-badge :priority="$ticket->priority" /></div>
            <h1 class="mt-4 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">{{ $ticket->title }}</h1>
            <p class="mt-2 text-sm text-slate-500">Requested by {{ $ticket->user->name }} · Submitted {{ $ticket->created_at->format('M j, Y g:i A') }} · Updated {{ $ticket->updated_at->format('M j, Y g:i A') }}</p>
            <div class="mt-7 border-t border-slate-100 pt-6"><h2 class="text-sm font-bold text-slate-900">Description</h2><p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $ticket->description }}</p></div>
            @if ($ticket->resolution)<div class="mt-6 rounded-xl bg-emerald-50 p-4"><h2 class="text-sm font-bold text-emerald-900">Resolution</h2><p class="mt-2 whitespace-pre-line text-sm text-emerald-800">{{ $ticket->resolution }}</p></div>@endif
            <div class="mt-7 border-t border-slate-100 pt-5"><h2 class="text-sm font-bold text-slate-900">Assignment history</h2>
                @forelse ($assignments as $assignment)<p class="mt-3 text-sm text-slate-600">{{ $assignment->assignee->name }} · assigned by {{ $assignment->assigner->name }} · {{ $assignment->assigned_at->format('M j, Y g:i a') }}{{ $assignment->unassigned_at ? ' · ended '.$assignment->unassigned_at->format('M j, Y g:i a') : ' · current' }}</p>@empty<p class="mt-2 text-sm text-slate-500">No assignment history.</p>@endforelse
            </div>
        </article>
        <x-ticket-conversation :ticket="$ticket" :comments="$comments" :attachments="$attachments" />
        </div>
        <aside class="space-y-5">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-bold text-slate-900">Ticket information</h2><dl class="mt-4 space-y-3 text-sm"><div><dt class="text-xs text-slate-500">Category</dt><dd class="font-medium text-slate-800">{{ $ticket->category->name }}</dd></div><div><dt class="text-xs text-slate-500">Requester</dt><dd class="font-medium text-slate-800">{{ $ticket->user->name }} · {{ $ticket->user->email }}</dd></div><div><dt class="text-xs text-slate-500">Department</dt><dd class="font-medium text-slate-800">{{ $ticket->department->name }}</dd></div><div><dt class="text-xs text-slate-500">Assigned to</dt><dd class="font-medium text-slate-800">{{ $ticket->currentAssignment?->assignee?->name ?? 'Unassigned' }}</dd></div></dl></section>
            @can('updateStatus', $ticket)<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-bold text-slate-900">Update status</h2><form method="POST" action="{{ route('tickets.status', $ticket) }}" class="mt-3 space-y-3">@csrf @method('PATCH')<input type="hidden" name="_html_form" value="1"><label class="block text-xs font-semibold text-slate-600" for="detail-status">New status</label><select id="detail-status" name="status" class="w-full rounded-lg border-slate-300 text-sm">@foreach ($statuses as $status)<option value="{{ $status->slug }}" @selected($status->id === $ticket->status_id)>{{ $status->name }}</option>@endforeach</select><button class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white">Save status</button></form></section>@endcan
            @can('create', [\App\Models\TicketAssignment::class, $ticket])<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-bold text-slate-900">Assignment</h2>
                @if ($ticket->currentAssignment && $ticket->currentAssignment->assigned_to === auth()->id())
                    <form method="POST" action="{{ route('tickets.assignments.store', $ticket) }}" class="mt-3 space-y-3">@csrf<input type="hidden" name="_html_form" value="1"><label class="block text-xs font-semibold text-slate-600" for="detail-assignee">Reassign to</label><select id="detail-assignee" name="assigned_to" class="w-full rounded-lg border-slate-300 text-sm">@foreach ($assignees as $assignee)<option value="{{ $assignee->id }}" @selected($assignee->id === $ticket->currentAssignment->assigned_to)>{{ $assignee->name }}</option>@endforeach</select><button class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Reassign ticket</button></form>
                    <form method="POST" action="{{ route('tickets.assignments.destroy', $ticket) }}" class="mt-2">@csrf @method('DELETE')<input type="hidden" name="_html_form" value="1"><button class="w-full rounded-lg px-4 py-2 text-sm font-semibold text-rose-700">Return to unassigned queue</button></form>
                @elseif (! $ticket->currentAssignment)
                    <form method="POST" action="{{ route('tickets.assignments.store', $ticket) }}" class="mt-3">@csrf<input type="hidden" name="assigned_to" value="{{ auth()->id() }}"><input type="hidden" name="_html_form" value="1"><button class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white">Assign to me</button></form>
                @endif
            </section>@endcan
        </aside>
    </section>
@endsection
