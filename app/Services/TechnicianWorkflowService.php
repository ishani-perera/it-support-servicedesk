<?php

namespace App\Services;

use App\Enums\TechnicianWorkReviewStatus;
use App\Enums\TicketStatusSlug;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketComment;
use App\Models\TicketWorkReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TechnicianWorkflowService
{
    public function __construct(
        private readonly TicketWorkflowService $workflow,
        private readonly TicketNotificationService $notifications,
    ) {}

    public function startWork(Ticket $ticket, User $technician): TicketWorkReport
    {
        return DB::transaction(function () use ($ticket, $technician): TicketWorkReport {
            $ticket = $this->lockTicket($ticket);
            $assignment = $this->currentTechnicianAssignment($ticket, $technician, 'startWork');
            $status = TicketStatusSlug::from($ticket->status()->value('slug'));
            $latest = $assignment->workReports()->latest('id')->first();

            $firstStart = in_array($status, [TicketStatusSlug::Assigned, TicketStatusSlug::InProgress], true) && $latest === null;
            $resubmission = $status === TicketStatusSlug::InProgress
                && $latest?->review_status === TechnicianWorkReviewStatus::ChangesRequested;
            if (! $firstStart && ! $resubmission) {
                throw ValidationException::withMessages(['work' => 'Work can only be started on a new assignment or after IT Support sends a report back.']);
            }

            $report = $assignment->workReports()->create();
            $report->started_by = $technician->getKey();
            $report->work_started_at = now();
            $report->review_status = TechnicianWorkReviewStatus::Pending;
            $report->save();

            if ($status === TicketStatusSlug::Assigned) {
                $this->workflow->transitionForTechnician($ticket, TicketStatusSlug::InProgress, $technician);
            }

            return $report->load(['assignment.assignee', 'assignment.assigner', 'startedBy', 'reviewer']);
        });
    }

    public function requestInformation(Ticket $ticket, User $technician, string $body): TicketComment
    {
        return DB::transaction(function () use ($ticket, $technician, $body): TicketComment {
            $ticket = $this->lockTicket($ticket);
            $assignment = $this->currentTechnicianAssignment($ticket, $technician, 'requestInformation');
            $this->assertCurrentWorkIsActive($ticket, $assignment);

            $comment = $ticket->comments()->create([
                'user_id' => $technician->getKey(),
                'body' => trim($body),
                'is_internal' => false,
            ]);
            $this->workflow->transitionForTechnician($ticket, TicketStatusSlug::WaitingForUser, $technician, false);
            $this->notifications->informationRequested($ticket, $technician);

            return $comment;
        });
    }

    /** @param array{work_summary:string, root_cause:string, technician_notes?:?string} $data */
    public function completeWork(Ticket $ticket, User $technician, array $data): TicketWorkReport
    {
        return DB::transaction(function () use ($ticket, $technician, $data): TicketWorkReport {
            $ticket = $this->lockTicket($ticket);
            $assignment = $this->currentTechnicianAssignment($ticket, $technician, 'completeWork');
            $report = $this->assertCurrentWorkIsActive($ticket, $assignment);

            $report->work_summary = trim($data['work_summary']);
            $report->root_cause = trim($data['root_cause']);
            $report->technician_notes = isset($data['technician_notes']) ? trim($data['technician_notes']) : null;
            $report->work_completed_at = now();
            $report->review_status = TechnicianWorkReviewStatus::Pending;
            $report->reviewed_by = null;
            $report->reviewed_at = null;
            $report->review_note = null;
            $report->save();

            $this->workflow->transitionForTechnician($ticket, TicketStatusSlug::ItSupportReview, $technician, false);
            $this->notifications->workSubmittedForReview($ticket, $technician);

            return $report->load(['assignment.assignee', 'assignment.assigner', 'startedBy', 'reviewer']);
        });
    }

    public function approveWork(Ticket $ticket, User $reviewer, ?string $note = null): TicketWorkReport
    {
        return $this->review($ticket, $reviewer, TechnicianWorkReviewStatus::Approved, $note);
    }

    public function sendBackWork(Ticket $ticket, User $reviewer, string $reason): TicketWorkReport
    {
        return $this->review($ticket, $reviewer, TechnicianWorkReviewStatus::ChangesRequested, trim($reason));
    }

    /** @return Collection<int, TicketWorkReport> */
    public function reportsVisibleTo(Ticket $ticket, User $viewer)
    {
        $reports = $ticket->workReports()
            ->with(['assignment.assignee', 'assignment.assigner', 'assignment.ticket', 'startedBy', 'reviewer'])
            ->latest('ticket_work_reports.id')
            ->get();

        return $reports->filter(fn (TicketWorkReport $report) => $viewer->can('view', $report))->values();
    }

    private function review(Ticket $ticket, User $reviewer, TechnicianWorkReviewStatus $decision, ?string $note): TicketWorkReport
    {
        return DB::transaction(function () use ($ticket, $reviewer, $decision, $note): TicketWorkReport {
            $ticket = $this->lockTicket($ticket);
            $reviewer->can('reviewWork', $ticket) || abort(403);
            $assignment = $ticket->currentAssignment()->with('assignee')->first();
            if (! $assignment?->assignee?->isTechnician()) {
                abort(403);
            }

            $report = $assignment->workReports()->latest('id')->first();
            if ($ticket->status()->value('slug') !== TicketStatusSlug::ItSupportReview->value
                || ! $report
                || $report->work_completed_at === null
                || $report->review_status !== TechnicianWorkReviewStatus::Pending) {
                throw ValidationException::withMessages(['work' => 'There is no completed Technician report awaiting review.']);
            }

            $report->review_status = $decision;
            $report->reviewed_by = $reviewer->getKey();
            $report->reviewed_at = now();
            $report->review_note = $note === null || trim($note) === '' ? null : trim($note);
            $report->save();

            if ($decision === TechnicianWorkReviewStatus::Approved) {
                $this->workflow->transitionAfterTechnicianReview($ticket, TicketStatusSlug::Closed, $reviewer);
            } else {
                $this->workflow->transitionAfterTechnicianReview($ticket, TicketStatusSlug::InProgress, $reviewer);
                $this->notifications->workSentBack($ticket, $reviewer);
            }

            return $report->load(['assignment.assignee', 'assignment.assigner', 'startedBy', 'reviewer']);
        });
    }

    private function currentTechnicianAssignment(Ticket $ticket, User $technician, string $ability): TicketAssignment
    {
        $technician->can($ability, $ticket) || abort(403);
        $assignment = $ticket->currentAssignment()->with('assignee')->first();
        if (! $technician->is_active || ! $technician->isTechnician()
            || ! $assignment || $assignment->assigned_to !== $technician->getKey()) {
            abort(403);
        }

        return $assignment;
    }

    private function assertCurrentWorkIsActive(Ticket $ticket, TicketAssignment $assignment): TicketWorkReport
    {
        $report = $assignment->workReports()->latest('id')->first();
        if ($ticket->status()->value('slug') !== TicketStatusSlug::InProgress->value
            || ! $report
            || $report->started_by !== $assignment->assigned_to
            || $report->work_started_at === null
            || $report->work_completed_at !== null
            || $report->review_status !== TechnicianWorkReviewStatus::Pending) {
            throw ValidationException::withMessages(['work' => 'Start or resume work before using this action.']);
        }

        return $report;
    }

    private function lockTicket(Ticket $ticket): Ticket
    {
        return Ticket::query()->lockForUpdate()->findOrFail($ticket->getKey());
    }
}
