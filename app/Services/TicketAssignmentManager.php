<?php

namespace App\Services;

use App\Enums\TicketStatusSlug;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TicketAssignmentManager
{
    public function __construct(private readonly TicketAssignmentService $assignments, private readonly TicketWorkflowService $workflow) {}

    public function assign(Ticket $ticket, User $assignee, User $actor, ?string $note = null): TicketAssignment
    {
        $actor->can('create', [TicketAssignment::class, $ticket]) || abort(403);

        return DB::transaction(function () use ($ticket, $assignee, $actor, $note) {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->getKey());
            $actor->can('create', [TicketAssignment::class, $ticket]) || abort(403);
            $assignment = $this->assignments->assign($ticket, $assignee, $actor, $note);
            if ($ticket->status()->value('slug') === TicketStatusSlug::Open->value) {
                $this->workflow->transition($ticket, TicketStatusSlug::Assigned, $actor);
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
                $this->workflow->transition($ticket, TicketStatusSlug::Open, $actor);
            }

            return $this->assignments->unassign($ticket);
        });
    }
}
