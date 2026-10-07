@props(['ticket', 'workReports', 'informationRequest'])

@php
    $isTechnicianAssigned = $ticket->currentAssignment?->assignee?->isTechnician() ?? false;
    $latestWorkReport = $workReports->first();
    $awaitingReview = $ticket->status->slug === \App\Enums\TicketStatusSlug::ItSupportReview->value;
    $waitingForEmployee = $ticket->status->slug === \App\Enums\TicketStatusSlug::WaitingForUser->value && $informationRequest;
    $latestReportStatus = match (true) {
        ! $latestWorkReport => null,
        ! $latestWorkReport->work_completed_at => 'Work in progress',
        default => $latestWorkReport->review_status?->label() ?? 'Work in progress',
    };
@endphp

@if ($isTechnicianAssigned || $workReports->isNotEmpty())
    <section class="ui-card overflow-hidden" aria-labelledby="technician-work-heading">
        <div class="ui-panel-heading justify-between gap-4">
            <div class="flex items-start gap-3">
                <span class="ui-icon-tile" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></span>
                <div>
                    <p class="ui-eyebrow">Technical handoff</p>
                    <h2 id="technician-work-heading" class="mt-1 text-lg font-bold text-slate-950">Technician work</h2>
                    <p class="mt-1 text-sm text-slate-500">Progress, report history, and IT Support review.</p>
                </div>
            </div>
            @if ($awaitingReview)
                <span class="inline-flex items-center gap-2 rounded-full border border-violet-200 bg-violet-50 px-3 py-1.5 text-xs font-bold text-violet-800"><span class="size-2 rounded-full bg-violet-500"></span>IT Support review</span>
            @elseif ($waitingForEmployee)
                <span class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-900"><span class="size-2 rounded-full bg-amber-500"></span>Waiting for Employee</span>
            @else
                <span class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700">{{ $ticket->status->name }}</span>
            @endif
        </div>

        <div class="space-y-5 p-5 sm:p-6">
            @if ($isTechnicianAssigned)
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Assigned Technician</p><p class="mt-1 text-sm font-semibold text-slate-900">{{ $ticket->currentAssignment->assignee->name }}</p></div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Ticket work status</p><p class="mt-1 text-sm font-semibold text-slate-900">{{ $ticket->status->name }}</p></div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Started</p><p class="mt-1 text-sm font-semibold text-slate-900">{{ $latestWorkReport?->work_started_at?->format('M j, Y · g:i A') ?? 'Not started' }}</p></div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50/70 p-3"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Completed</p><p class="mt-1 text-sm font-semibold text-slate-900">{{ $latestWorkReport?->work_completed_at?->format('M j, Y · g:i A') ?? 'In progress' }}</p></div>
                </div>
            @else
                <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">No Technician is currently assigned. Historical work reports are retained below.</div>
            @endif

            @if ($waitingForEmployee)
                <div class="rounded-2xl border border-amber-200 bg-gradient-to-r from-amber-50 to-white p-4" role="status">
                    <p class="text-sm font-bold text-amber-950">Employee information requested</p>
                    <p class="mt-1 text-xs text-amber-800">The ticket is waiting for the requester. Their reply will appear in the conversation and return the workflow to active work.</p>
                    <blockquote class="mt-3 rounded-xl border border-amber-100 bg-white/80 p-3 text-sm leading-6 text-slate-700">{{ $informationRequest->body }}</blockquote>
                </div>
            @endif

            @if ($latestWorkReport)
                <article class="rounded-2xl border {{ $awaitingReview ? 'border-violet-200 bg-violet-50/40' : 'border-slate-200 bg-white' }} p-4 sm:p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div><p class="ui-eyebrow">Latest work report</p><p class="mt-1 text-xs text-slate-500">{{ $latestWorkReport->startedBy?->name ?? $latestWorkReport->assignment?->assignee?->name }} · {{ $latestWorkReport->created_at->format('M j, Y · g:i A') }}</p></div>
                        <span class="rounded-full border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $latestReportStatus }}</span>
                    </div>
                    <dl class="mt-4 grid gap-4 md:grid-cols-2">
                        <div><dt class="text-xs font-bold uppercase tracking-wider text-slate-500">Work summary</dt><dd class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-800">{{ $latestWorkReport->work_summary ?: 'The Technician has not submitted a completion summary yet.' }}</dd></div>
                        <div><dt class="text-xs font-bold uppercase tracking-wider text-slate-500">Root cause</dt><dd class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-800">{{ $latestWorkReport->root_cause ?: 'Not submitted yet.' }}</dd></div>
                        @if ($latestWorkReport->technician_notes)<div class="md:col-span-2"><dt class="text-xs font-bold uppercase tracking-wider text-slate-500">Technician notes</dt><dd class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-800">{{ $latestWorkReport->technician_notes }}</dd></div>@endif
                        @if ($latestWorkReport->review_note)<div class="md:col-span-2"><dt class="text-xs font-bold uppercase tracking-wider text-slate-500">Latest IT Support review note</dt><dd class="mt-1 whitespace-pre-line rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm leading-6 text-amber-950">{{ $latestWorkReport->review_note }}</dd></div>@endif
                    </dl>
                    <p class="mt-4 border-t border-slate-200/80 pt-3 text-xs text-slate-500">Internal work communication is shown in the conversation below and is not visible to the Employee.</p>
                </article>
            @else
                <div class="ui-empty py-7"><p class="text-sm font-semibold text-slate-800">Work has not started</p><p class="mt-1 text-xs text-slate-500">The Technician report will appear here when work begins.</p></div>
            @endif

            @if ($workReports->count() > 1)
                <details class="rounded-xl border border-slate-200 bg-slate-50/70 p-4">
                    <summary class="cursor-pointer text-sm font-semibold text-slate-800">Previous work reports ({{ $workReports->count() - 1 }})</summary>
                    <div class="mt-3 space-y-3">
                        @foreach ($workReports->skip(1) as $report)
                            <article class="rounded-xl border border-slate-200 bg-white p-3">
                                <div class="flex flex-wrap justify-between gap-2"><p class="text-xs font-semibold text-slate-800">{{ $report->assignment?->assignee?->name }} · {{ $report->created_at->format('M j, Y · g:i A') }}</p><span class="text-xs text-slate-500">{{ $report->review_status?->label() }}</span></div>
                                <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $report->work_summary ?: 'Work started; completion report not submitted.' }}</p>
                                @if ($report->review_note)<p class="mt-2 rounded-lg bg-amber-50 p-2 text-xs text-amber-950"><span class="font-semibold">Review note:</span> {{ $report->review_note }}</p>@endif
                            </article>
                        @endforeach
                    </div>
                </details>
            @endif
        </div>
    </section>
@endif

@if ($awaitingReview && $latestWorkReport)
    @can('reviewWork', $ticket)
        <section class="ui-card overflow-hidden border-violet-200" aria-labelledby="it-support-review-heading">
            <div class="border-b border-violet-100 bg-gradient-to-r from-violet-50 via-indigo-50/60 to-white px-5 py-5 sm:px-6">
                <p class="ui-eyebrow">Decision required</p>
                <h2 id="it-support-review-heading" class="mt-1 text-lg font-bold text-slate-950">IT Support review</h2>
                <p class="mt-1 text-sm text-slate-600">Review the completed Technician work before approving closure or requesting a revision.</p>
            </div>
            <div class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6">
                <form action="{{ route('tickets.work.approve', $ticket) }}" method="POST" class="space-y-3 rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4" data-async-workflow-form data-confirm="Approve this Technician report and close the ticket?" data-success-key="work_approved" data-success-message="Work approved and the ticket closed." aria-busy="false">
                    @csrf
                    <div><h3 class="text-sm font-bold text-emerald-950">Approve and close</h3><p class="mt-1 text-xs leading-5 text-emerald-900/80">This approves the report and closes the ticket through the existing workflow.</p></div>
                    <label for="review-approval-note" class="ui-field-label">Review note <span class="font-normal text-slate-400">(optional)</span></label>
                    <textarea id="review-approval-note" name="review_note" rows="2" maxlength="20000" class="w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" placeholder="Add a short internal review note."></textarea>
                    <p data-field-error="review_note" class="hidden text-xs font-medium text-rose-700" role="alert"></p>
                    <div data-workflow-feedback class="hidden rounded-xl px-3 py-2 text-sm" role="status" aria-live="polite"></div>
                    <button type="submit" data-loading-label="Approving…" class="inline-flex w-full items-center justify-center rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 disabled:cursor-wait disabled:opacity-60">Approve and close ticket</button>
                </form>

                <form action="{{ route('tickets.work.send-back', $ticket) }}" method="POST" class="space-y-3 rounded-2xl border border-amber-200 bg-amber-50/50 p-4" data-async-workflow-form data-confirm="Send this report back to the Technician for revision?" data-success-key="work_sent_back" data-success-message="The report was sent back to the Technician." aria-busy="false">
                    @csrf
                    <div><h3 class="text-sm font-bold text-amber-950">Send back for revision</h3><p class="mt-1 text-xs leading-5 text-amber-900/80">A reason is required and will be visible to the assigned Technician.</p></div>
                    <label for="review-send-back-note" class="ui-field-label">Revision reason <span class="text-rose-600">*</span></label>
                    <textarea id="review-send-back-note" name="review_note" rows="3" required minlength="1" maxlength="20000" data-trim-required class="w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500" placeholder="Explain what needs more investigation or correction."></textarea>
                    <p data-field-error="review_note" class="hidden text-xs font-medium text-rose-700" role="alert"></p>
                    <div data-workflow-feedback class="hidden rounded-xl px-3 py-2 text-sm" role="status" aria-live="polite"></div>
                    <button type="submit" data-loading-label="Sending back…" class="inline-flex w-full items-center justify-center rounded-xl border border-amber-300 bg-white px-4 py-2.5 text-sm font-semibold text-amber-900 shadow-sm transition hover:bg-amber-100 disabled:cursor-wait disabled:opacity-60">Send back to Technician</button>
                </form>
            </div>
        </section>
    @endcan
@endif
