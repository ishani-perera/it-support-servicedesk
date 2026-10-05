<?php

namespace App\Services;

use App\Exceptions\InvalidAssignmentException;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Integrity-safe primitives for ticket assignment HISTORY.
 *
 * This service provides data-integrity operations only. TicketAssignmentManager
 * authorizes calls and coordinates workflow/notification behavior around it.
 *
 * Guarantees:
 *  - a reassignment CLOSES the previous row (unassigned_at) and inserts a new
 *    one — history is never overwritten or deleted;
 *  - close + open happen in one transaction while the ticket row is locked, so
 *    concurrent reassignments serialise (the database's unique index on
 *    ticket_id + is_current is the final safety net);
 *  - assignee and assigner must be ACTIVE IT staff (support or admin).
 */
class TicketAssignmentService
{
    /**
     * Assign (or reassign) a ticket.
     *
     * Assigning the user who already holds the ticket is a no-op and returns
     * the existing open assignment.
     *
     * @param  CarbonInterface|null  $at  When the change happened (defaults to now; seeders pass history dates)
     *
     * @throws InvalidAssignmentException
     */
    public function assign(
        Ticket $ticket,
        User $assignee,
        User $assigner,
        ?string $note = null,
        ?CarbonInterface $at = null,
    ): TicketAssignment {
        $this->ensureActiveStaff($assignee, 'assignee');
        $this->ensureActiveStaff($assigner, 'assigner');

        $at ??= now();

        return DB::transaction(function () use ($ticket, $assignee, $assigner, $note, $at) {
            $this->lockTicket($ticket);

            $current = $ticket->assignments()->current()->first();

            if ($current !== null) {
                if ($current->assigned_to === $assignee->getKey()) {
                    return $current;
                }

                $this->close($current, $at);
            }

            $assignment = new TicketAssignment([
                'ticket_id' => $ticket->getKey(),
                'assigned_to' => $assignee->getKey(),
                'assigned_by' => $assigner->getKey(),
                'assigned_at' => $at,
                'note' => $note,
            ]);
            $assignment->created_at = $at;
            $assignment->updated_at = $at;
            $assignment->save();

            $ticket->unsetRelation('currentAssignment');

            return $assignment;
        });
    }

    /**
     * Close the ticket's open assignment (return it to the unassigned queue).
     * Returns the closed row, or null when the ticket had no open assignment.
     *
     * @throws InvalidAssignmentException
     */
    public function unassign(Ticket $ticket, ?CarbonInterface $at = null): ?TicketAssignment
    {
        $at ??= now();

        return DB::transaction(function () use ($ticket, $at) {
            $this->lockTicket($ticket);

            $current = $ticket->assignments()->current()->first();

            if ($current === null) {
                return null;
            }

            $this->close($current, $at);
            $ticket->unsetRelation('currentAssignment');

            return $current;
        });
    }

    private function close(TicketAssignment $assignment, CarbonInterface $at): void
    {
        if ($at->lt($assignment->assigned_at)) {
            throw new InvalidAssignmentException(
                'An assignment cannot end before it started ('.$assignment->assigned_at->toDateTimeString().').'
            );
        }

        $assignment->unassigned_at = $at;
        $assignment->updated_at = $at;
        $assignment->save();
    }

    /**
     * Serialise concurrent changes to one ticket's assignments.
     */
    private function lockTicket(Ticket $ticket): void
    {
        Ticket::query()->whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();
    }

    /**
     * @throws InvalidAssignmentException
     */
    private function ensureActiveStaff(User $user, string $label): void
    {
        if (! $user->is_active || ! $user->isStaff()) {
            throw new InvalidAssignmentException(
                "The {$label} must be an active IT Support or Admin user."
            );
        }
    }
}
