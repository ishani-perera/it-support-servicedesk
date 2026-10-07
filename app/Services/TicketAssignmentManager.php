<?php

namespace App\Services;

use App\Enums\TicketStatusSlug;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketAssignmentManager
{
    public function __construct(
        private readonly TicketAssignmentService $assignments,
        private readonly TicketWorkflowService $workflow,
        private readonly TicketNotificationService $notifications,
    ) {}

    public function assign(Ticket $ticket, User $assignee, User $actor, ?string $note = null): TicketAssignment
    {
        $actor->can('create', [TicketAssignment::class, $ticket]) || abort(403);

        return DB::transaction(function () use ($ticket, $assignee, $actor, $note) {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->getKey());
            $actor->can('create', [TicketAssignment::class, $ticket]) || abort(403);
            $previousAssignee = $ticket->currentAssignment()->with('assignee')->first()?->assignee;
            if ($previousAssignee?->isTechnician()
                && $previousAssignee->getKey() !== $assignee->getKey()
                && $ticket->status()->value('slug') === TicketStatusSlug::ItSupportReview->value) {
                throw ValidationException::withMessages(['assigned_to' => 'Review or send back the completed Technician work before reassigning this ticket.']);
            }
            if ($ticket->status()->value('slug') === TicketStatusSlug::Open->value) {
                $this->workflow->markAssigned($ticket, $actor);
            }
            $assignment = $this->assignments->assign($ticket, $assignee, $actor, $note);
            if ($assignment->wasRecentlyCreated) {
                $this->notifications->assignmentChanged($ticket, $actor, $assignee, $previousAssignee);
            }

            return $assignment;
        });
    }

    public function unassign(Ticket $ticket, User $actor): ?TicketAssignment
    {
        $actor->can('create', [TicketAssignment::class, $ticket]) || abort(403);

        return DB::transaction(function () use ($ticket, $actor) {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->getKey());
            $actor->can('create', [TicketAssignment::class, $ticket]) || abort(403);
            if ($ticket->status()->value('slug') === TicketStatusSlug::Assigned->value) {
                $this->workflow->returnToOpenQueue($ticket, $actor);
            }

            return $this->assignments->unassign($ticket);
        });
    }
}
