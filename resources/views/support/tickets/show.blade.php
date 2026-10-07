@extends('layouts.app')

@section('title', $ticket->ticket_number)
@section('topline', 'Ticket detail')

@section('content')
    @php
        $workflowNotice = match (request()->query('workflow_notice')) {
            'technician_assigned' => 'Technician assignment updated.',
            'work_approved' => 'Technician work approved and the ticket closed.',
            'work_sent_back' => 'The work report was sent back to the Technician.',
            default => null,
        };
    @endphp

    @if ($workflowNotice)
        <div class="ui-alert-success mb-5" role="status">{{ $workflowNotice }}</div>
    @endif

    <section class="ui-page-toolbar mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('tickets.index') }}" class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-violet-700 transition hover:text-violet-900">← Support queue</a>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <span class="rounded-lg bg-slate-100 px-2.5 py-1.5 font-mono text-xs font-bold text-slate-700">{{ $ticket->ticket_number }}</span>
                <x-ticket-status-badge :status="$ticket->status" />
                <x-ticket-priority-badge :priority="$ticket->priority" />
            </div>
            <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">{{ $ticket->title }}</h1>
            <p class="mt-1 text-sm text-slate-500">Requested by {{ $ticket->user->name }} · Updated {{ $ticket->updated_at->format('M j, Y g:i A') }}</p>
        </div>
        <div class="hidden items-center gap-2 rounded-xl border border-indigo-100 bg-indigo-50/70 px-4 py-3 text-xs font-semibold text-indigo-800 sm:flex">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            Service operations
        </div>
    </section>

    <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="min-w-0 space-y-6">
            <article class="ui-card p-5 shadow-sm sm:p-7">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm font-bold text-slate-500">{{ $ticket->ticket_number }}</span>
                    <x-ticket-status-badge :status="$ticket->status" />
                    <x-ticket-priority-badge :priority="$ticket->priority" />
                </div>
                <h2 class="mt-4 text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">{{ $ticket->title }}</h2>
                <p class="mt-2 text-sm text-slate-500">Requested by {{ $ticket->user->name }} · Submitted {{ $ticket->created_at->format('M j, Y g:i A') }} · Updated {{ $ticket->updated_at->format('M j, Y g:i A') }}</p>
                <div class="mt-7 border-t border-slate-100 pt-6">
                    <h3 class="text-sm font-bold text-slate-900">Description</h3>
                    <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $ticket->description }}</p>
                </div>
                @if ($ticket->resolution)
                    <div class="mt-6 rounded-xl bg-emerald-50 p-4"><h3 class="text-sm font-bold text-emerald-900">Resolution</h3><p class="mt-2 whitespace-pre-line text-sm text-emerald-800">{{ $ticket->resolution }}</p></div>
                @endif

                <div class="mt-7 border-t border-slate-100 pt-5">
                    <div class="flex items-center justify-between gap-3">
                        <div><p class="ui-eyebrow">Ownership history</p><h3 class="mt-1 text-sm font-bold text-slate-900">Assignment history</h3></div>
                        <span class="rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-bold tabular-nums text-slate-600">{{ $assignments->count() }}</span>
                    </div>
                    @forelse ($assignments as $assignment)
                        <article class="mt-3 flex gap-3 rounded-xl border border-slate-200/80 bg-gradient-to-r from-white to-slate-50/70 p-3.5">
                            <span class="grid size-9 shrink-0 place-items-center rounded-xl {{ $assignment->unassigned_at ? 'bg-slate-100 text-slate-500' : 'bg-violet-100 text-violet-700' }}" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2m6-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8-1v6m3-3h-6" /></svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-2"><p class="text-sm font-bold text-slate-900">{{ $assignment->assignee->name }}</p><span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $assignment->assignee->isTechnician() ? 'bg-indigo-100 text-indigo-800' : 'bg-sky-100 text-sky-800' }}">{{ $assignment->assignee->isTechnician() ? 'Technician' : 'IT Support' }}</span></div>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $assignment->unassigned_at ? 'bg-slate-100 text-slate-600' : 'bg-emerald-100 text-emerald-800' }}">{{ $assignment->unassigned_at ? 'Ended' : 'Current' }}</span>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">Assigned by {{ $assignment->assigner->name }} · {{ $assignment->assigned_at->format('M j, Y g:i A') }}</p>
                                @if ($assignment->note)<p class="mt-2 rounded-lg bg-slate-100/80 px-3 py-2 text-xs leading-5 text-slate-600">Handoff note: {{ $assignment->note }}</p>@endif
                                @if ($assignment->unassigned_at)<p class="mt-1 text-xs text-slate-500">Ended {{ $assignment->unassigned_at->format('M j, Y g:i A') }}</p>@endif
                            </div>
                        </article>
                    @empty
                        <div class="ui-empty mt-3 py-7"><p class="text-sm font-semibold text-slate-800">No assignment history</p><p class="mt-1 text-xs text-slate-500">Ownership changes will appear here.</p></div>
                    @endforelse
                </div>
            </article>

            <x-technician-workflow :ticket="$ticket" :work-reports="$workReports" :information-request="$technicianInformationRequest" />
            <x-ticket-conversation :ticket="$ticket" :comments="$comments" :attachments="$attachments" />
        </div>

        <aside class="space-y-5">
            <x-ticket-sla :ticket="$ticket" :sla="$sla" />
            <section class="ui-card p-5 shadow-sm">
                <h2 class="font-bold text-slate-900">Ticket information</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="text-xs text-slate-500">Category</dt><dd class="font-medium text-slate-800">{{ $ticket->category->name }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Requester</dt><dd class="font-medium text-slate-800">{{ $ticket->user->name }} · {{ $ticket->user->email }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Department</dt><dd class="font-medium text-slate-800">{{ $ticket->department->name }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Assigned owner</dt><dd class="font-medium text-slate-800">{{ $ticket->currentAssignment?->assignee?->name ?? 'Unassigned' }}<span class="mt-0.5 block text-xs text-slate-500">{{ $ticket->currentAssignment?->assignee?->isTechnician() ? 'Technician' : ($ticket->currentAssignment ? 'IT Support' : 'No current assignment') }}</span></dd></div>
                </dl>
            </section>

            <x-technician-assignment :ticket="$ticket" :technicians="$technicians" />

            @can('updateStatus', $ticket)
                <section class="ui-card p-5 shadow-sm">
                    <h2 class="font-bold text-slate-900">Update status</h2>
                    <form method="POST" action="{{ route('tickets.status', $ticket) }}" class="mt-3 space-y-3" data-resolution-form data-loading-form>
                        @csrf @method('PATCH')
                        <input type="hidden" name="_html_form" value="1">
                        <label class="block text-xs font-semibold text-slate-600" for="detail-status">Next status</label>
                        <select id="detail-status" name="status" required class="w-full rounded-lg border-slate-300 text-sm" data-resolution-status><option value="">Choose next status</option>@foreach ($nextStatuses as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach</select>
                        <div data-resolution-field class="hidden"><label class="mb-1.5 block text-xs font-semibold text-slate-600" for="detail-resolution">Solution <span class="text-rose-600">*</span></label><textarea id="detail-resolution" name="resolution" rows="4" maxlength="20000" data-resolution-input class="w-full rounded-lg border-slate-300 text-sm" placeholder="Describe what was done to resolve this ticket.">{{ old('resolution', $ticket->resolution) }}</textarea><p class="mt-1 text-xs text-slate-500">Add the steps taken so the requester and team have a clear record.</p></div>
                        <button type="submit" class="ui-button-primary w-full">Save status</button>
                    </form>
                </section>
            @endcan

            @can('create', [\App\Models\TicketAssignment::class, $ticket])
                @unless ($ticket->currentAssignment?->assignee?->isTechnician())
                    <section class="ui-card p-5 shadow-sm">
                        <h2 class="font-bold text-slate-900">IT Support assignment</h2>
                        @if ($ticket->currentAssignment && $ticket->currentAssignment->assigned_to === auth()->id())
                            <form method="POST" action="{{ route('tickets.assignments.store', $ticket) }}" class="mt-3 space-y-3" data-loading-form>
                                @csrf<input type="hidden" name="_html_form" value="1">
                                <label class="block text-xs font-semibold text-slate-600" for="detail-assignee">Reassign to IT Support</label>
                                <select id="detail-assignee" name="assigned_to" class="w-full rounded-lg border-slate-300 text-sm">@foreach ($assignees as $assignee)<option value="{{ $assignee->id }}" @selected($assignee->id === $ticket->currentAssignment->assigned_to)>{{ $assignee->name }}</option>@endforeach</select>
                                <button type="submit" class="ui-button-secondary w-full">Reassign ticket</button>
                            </form>
                            <form method="POST" action="{{ route('tickets.assignments.destroy', $ticket) }}" class="mt-2" data-loading-form>@csrf @method('DELETE')<input type="hidden" name="_html_form" value="1"><button type="submit" class="w-full rounded-lg px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-50">Return to unassigned queue</button></form>
                        @elseif (! $ticket->currentAssignment)
                            <form method="POST" action="{{ route('tickets.assignments.store', $ticket) }}" class="mt-3" data-loading-form>@csrf<input type="hidden" name="assigned_to" value="{{ auth()->id() }}"><input type="hidden" name="_html_form" value="1"><button type="submit" class="ui-button-primary w-full">Assign to me</button></form>
                        @endif
                    </section>
                @endunless
            @endcan
        </aside>
    </section>
@endsection
