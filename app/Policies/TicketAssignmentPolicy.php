<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\User;

/**
 * Assignment HISTORY is internal (it names agents, reasons and who assigned
 * what), so requesters never see the rows — only IT staff who can see the
 * ticket. History is append-only: update/delete are denied for everyone.
 */
class TicketAssignmentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function view(User $user, TicketAssignment $assignment): bool
    {
        $ticket = $assignment->relationLoaded('ticket') ? $assignment->ticket : $assignment->ticket()->firstOrFail();

        if ($user->isTechnician()) {
            return $assignment->assigned_to === $user->getKey() && $user->can('view', $ticket);
        }
        if (! $user->isStaff()) {
            return false;
        }

        return $user->can('view', $ticket);
    }

    /**
     * Gate::authorize('create', [TicketAssignment::class, $ticket]) — i.e. may
     * this user assign / reassign / unassign the ticket.
     */
    public function create(User $user, Ticket $ticket): bool
    {
        return $user->can('assign', $ticket);
    }

    public function update(User $user, TicketAssignment $assignment): bool
    {
        return false;
    }

    public function delete(User $user, TicketAssignment $assignment): bool
    {
        return false;
    }
}
