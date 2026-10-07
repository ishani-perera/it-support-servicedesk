@props(['ticket', 'technicians'])

@can('create', [\App\Models\TicketAssignment::class, $ticket])
    <section class="ui-card overflow-hidden" aria-labelledby="technician-assignment-heading">
        <div class="ui-panel-heading">
            <span class="ui-icon-tile" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2m6-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm8-1v6m3-3h-6" /></svg>
            </span>
            <div>
                <h2 id="technician-assignment-heading" class="text-base font-bold text-slate-950">Technician assignment</h2>
                <p class="mt-1 text-xs text-slate-500">Hand off technical work while keeping assignment history.</p>
            </div>
        </div>
        <div class="space-y-4 p-5">
            @if ($ticket->currentAssignment?->assignee?->isTechnician())
                <div class="rounded-xl border border-indigo-100 bg-gradient-to-br from-indigo-50 to-white p-4">
                    <p class="text-[10px] font-bold uppercase tracking-[.15em] text-indigo-700">Currently assigned Technician</p>
                    <p class="mt-1 text-base font-bold text-slate-950">{{ $ticket->currentAssignment->assignee->name }}</p>
                    <p class="mt-1 text-xs text-slate-500">Assigned {{ $ticket->currentAssignment->assigned_at->format('M j, Y · g:i A') }}</p>
                </div>
            @elseif ($ticket->currentAssignment)
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <p class="text-xs font-semibold text-slate-700">Current IT Support owner</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $ticket->currentAssignment->assignee->name }}</p>
                </div>
            @else
                <p class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-600">No current owner. Assign a Technician to begin the technical handoff.</p>
            @endif

            @if ($technicians->isEmpty())
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" role="status">No active Technicians are available to assign.</div>
            @else
                <form action="{{ route('tickets.technician-assignment.store', $ticket) }}" method="POST" class="space-y-3" data-async-workflow-form data-success-key="technician_assigned" data-success-message="Technician assignment saved." aria-busy="false">
                    @csrf
                    <div>
                        <label for="technician-assignee" class="ui-field-label">{{ $ticket->currentAssignment?->assignee?->isTechnician() ? 'Reassign to Technician' : 'Assign a Technician' }}</label>
                        <select id="technician-assignee" name="assigned_to" required class="w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Choose an active Technician</option>
                            @foreach ($technicians as $technician)
                                <option value="{{ $technician->id }}" @selected($ticket->currentAssignment?->assigned_to === $technician->id)>{{ $technician->name }}{{ $technician->department ? ' · '.$technician->department->name : '' }}</option>
                            @endforeach
                        </select>
                        <p data-field-error="assigned_to" class="mt-1 hidden text-xs font-medium text-rose-700" role="alert"></p>
                    </div>
                    <div>
                        <label for="technician-assignment-note" class="ui-field-label">Handoff note <span class="font-normal text-slate-400">(optional)</span></label>
                        <textarea id="technician-assignment-note" name="note" rows="2" maxlength="500" class="w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Add useful investigation context for the Technician."></textarea>
                        <p data-field-error="note" class="mt-1 hidden text-xs font-medium text-rose-700" role="alert"></p>
                    </div>
                    <div data-workflow-feedback class="hidden rounded-xl px-3 py-2 text-sm" role="status" aria-live="polite"></div>
                    <button type="submit" data-loading-label="Assigning…" class="ui-button-primary w-full" @disabled($technicians->isEmpty())>
                        {{ $ticket->currentAssignment?->assignee?->isTechnician() ? 'Reassign Technician' : 'Assign Technician' }}
                    </button>
                </form>
            @endif
        </div>
    </section>
@endcan
