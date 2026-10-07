<?php

namespace App\Services;

use App\Enums\TicketStatusSlug;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketWorkflowService
{
    public function __construct(private readonly TicketNotificationService $notifications) {}

    /** @return array<string, list<TicketStatusSlug>> */
    private function transitions(): array
    {
        return [
            TicketStatusSlug::Open->value => [TicketStatusSlug::Assigned],
            TicketStatusSlug::Assigned->value => [TicketStatusSlug::Open, TicketStatusSlug::InProgress],
            TicketStatusSlug::InProgress->value => [TicketStatusSlug::Assigned, TicketStatusSlug::WaitingForUser],
            TicketStatusSlug::WaitingForUser->value => [TicketStatusSlug::InProgress, TicketStatusSlug::Resolved],
            TicketStatusSlug::Resolved->value => [TicketStatusSlug::WaitingForUser, TicketStatusSlug::Closed],
            TicketStatusSlug::ItSupportReview->value => [],
            TicketStatusSlug::Closed->value => [TicketStatusSlug::Resolved],
        ];
    }

    /** @return list<TicketStatusSlug> */
    public function availableTransitions(TicketStatusSlug $current): array
    {
        return $this->transitions()[$current->value] ?? [];
    }

    /** Existing Employee/Support workflow. Technician actions use dedicated methods below. */
    public function transition(Ticket $ticket, TicketStatusSlug $target, User $actor, ?string $resolution = null): Ticket
    {
        return DB::transaction(function () use ($ticket, $target, $actor, $resolution) {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->getKey());
            $actor->can('updateStatus', $ticket) || abort(403);

            $current = TicketStatusSlug::from($ticket->status()->value('slug'));
            $assignee = $ticket->currentAssignment()->with('assignee')->first()?->assignee;
            if ($assignee?->isTechnician() && in_array($target, [TicketStatusSlug::Resolved, TicketStatusSlug::Closed], true)) {
                abort(403, 'Technician-assigned tickets must pass IT Support review before closure.');
            }

            $this->assertTransitionAllowed($current, $target);
            $this->assertResolution($target, $resolution);

            return $this->persistTransition($ticket, $target, $actor, $resolution);
        });
    }

    /**
     * Restricted transitions invoked only from the Technician work actions.
     */
    public function transitionForTechnician(Ticket $ticket, TicketStatusSlug $target, User $actor, bool $notify = true): Ticket
    {
        return DB::transaction(function () use ($ticket, $target, $actor, $notify) {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->getKey());
            $ability = match ($target) {
                TicketStatusSlug::InProgress => 'startWork',
                TicketStatusSlug::WaitingForUser => 'requestInformation',
                TicketStatusSlug::ItSupportReview => 'completeWork',
                default => null,
            };
            if ($ability === null) {
                abort(403);
            }
            $actor->can($ability, $ticket) || abort(403);

            $current = TicketStatusSlug::from($ticket->status()->value('slug'));
            $allowed = match ($target) {
                TicketStatusSlug::InProgress => [TicketStatusSlug::Assigned],
                TicketStatusSlug::WaitingForUser => [TicketStatusSlug::InProgress],
                TicketStatusSlug::ItSupportReview => [TicketStatusSlug::InProgress],
                default => [],
            };
            if (! in_array($current, $allowed, true)) {
                throw ValidationException::withMessages(['status' => 'This technician action is not valid from the current ticket status.']);
            }

            return $this->persistTransition($ticket, $target, $actor, null, $notify);
        });
    }

    /** Called by TicketAssignmentManager after it has authorized an assignment operation. */
    public function markAssigned(Ticket $ticket, User $actor): Ticket
    {
        return DB::transaction(function () use ($ticket, $actor) {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->getKey());
            $actor->can('assign', $ticket) || abort(403);
            $current = TicketStatusSlug::from($ticket->status()->value('slug'));
            $this->assertTransitionAllowed($current, TicketStatusSlug::Assigned);

            return $this->persistTransition($ticket, TicketStatusSlug::Assigned, $actor, null);
        });
    }

    /** Return a newly unassigned ticket to the triage queue after authorized unassignment. */
    public function returnToOpenQueue(Ticket $ticket, User $actor): Ticket
    {
        return DB::transaction(function () use ($ticket, $actor) {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->getKey());
            $actor->can('assign', $ticket) || abort(403);
            $current = TicketStatusSlug::from($ticket->status()->value('slug'));
            $this->assertTransitionAllowed($current, TicketStatusSlug::Open);

            return $this->persistTransition($ticket, TicketStatusSlug::Open, $actor, null);
        });
    }

    /** Resume technician work after the requester replies to an information request. */
    public function resumeForRequesterReply(Ticket $ticket, User $requester): bool
    {
        return DB::transaction(function () use ($ticket, $requester): bool {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->getKey());
            if (! $requester->isEmployee() || $ticket->user_id !== $requester->getKey()) {
                abort(403);
            }

            $assignment = $ticket->currentAssignment()->with('assignee')->first();
            if ($ticket->status()->value('slug') !== TicketStatusSlug::WaitingForUser->value
                || ! $assignment?->assignee?->isTechnician()) {
                return false;
            }

            $this->persistTransition($ticket, TicketStatusSlug::InProgress, $requester, null, false);

            return true;
        });
    }

    /** Close an IT Support-reviewed report or return it to the assigned Technician. */
    public function transitionAfterTechnicianReview(Ticket $ticket, TicketStatusSlug $target, User $reviewer): Ticket
    {
        return DB::transaction(function () use ($ticket, $target, $reviewer) {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->getKey());
            $reviewer->can('reviewWork', $ticket) || abort(403);
            if ($ticket->status()->value('slug') !== TicketStatusSlug::ItSupportReview->value
                || ! in_array($target, [TicketStatusSlug::Closed, TicketStatusSlug::InProgress], true)) {
                throw ValidationException::withMessages(['status' => 'Only work awaiting IT Support review can be approved or sent back.']);
            }

            return $this->persistTransition($ticket, $target, $reviewer, null, $target === TicketStatusSlug::Closed);
        });
    }

    private function assertTransitionAllowed(TicketStatusSlug $current, TicketStatusSlug $target): void
    {
        if (! in_array($target, $this->availableTransitions($current), true)) {
            throw ValidationException::withMessages(['status' => "Cannot transition from {$current->value} to {$target->value}."]);
        }
    }

    private function assertResolution(TicketStatusSlug $target, ?string $resolution): void
    {
        if ($target === TicketStatusSlug::Resolved && trim((string) $resolution) === '') {
            throw ValidationException::withMessages(['resolution' => 'Add the solution before resolving this ticket.']);
        }
    }

    private function persistTransition(
        Ticket $ticket,
        TicketStatusSlug $target,
        User $actor,
        ?string $resolution,
        bool $notify = true,
    ): Ticket {
        $current = TicketStatusSlug::from($ticket->status()->value('slug'));
        $ticket->status_id = TicketStatus::forSlug($target)->getKey();
        if ($target === TicketStatusSlug::Resolved) {
            $ticket->resolution = trim((string) $resolution);
        }
        if ($target === TicketStatusSlug::Resolved && $current !== TicketStatusSlug::Closed) {
            $ticket->resolved_at = now();
        } elseif (! in_array($target, [TicketStatusSlug::Resolved, TicketStatusSlug::Closed], true)) {
            $ticket->resolved_at = null;
        }
        $ticket->closed_at = $target === TicketStatusSlug::Closed ? now() : null;
        $ticket->save();
        if ($notify) {
            $this->notifications->statusChanged($ticket, $actor, $target);
        }

        return $ticket;
    }
}
