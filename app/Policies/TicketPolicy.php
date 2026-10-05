<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

/**
 * Authorization rules for tickets (server-side; Blade @can is only a UI hint).
 *
 * Role summary
 *  - Employee: only tickets they REQUESTED (tickets.user_id). Can view them,
 *    comment on them and attach files. Cannot edit, re-status, re-prioritise,
 *    assign, or see internal notes.
 *  - Support:  may view all tickets for shared team visibility, including
 *    internal notes and attachments. They may change status/priority and
 *    core fields only on tickets assigned to them. Assignment controls remain
 *    limited to their own tickets and the unassigned queue.
 *  - Admin:    everything.
 *
 * Design notes
 *  - before() ONLY ever denies (deactivated accounts, e.g. a stale Sanctum
 *    token). It never grants: a blanket "admin => true" would also grant
 *    abilities that were never defined.
 *  - Every ability is explicit. There is deliberately no delete ability:
 *    tickets are never deleted.
 *  - This class decides WHO may act. TicketWorkflowService decides which
 *    status transitions are valid. Comment/upload writes currently do not
 *    depend on ticket status (see docs/SECURITY.md).
 *  - Keep Ticket::scopeVisibleTo() in sync with view().
 */
class TicketPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_active ? null : false;
    }

    /** Listing is allowed; WHICH rows appear is decided by Ticket::visibleTo(). */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return match (true) {
            $user->isAdmin() => true,
            $user->isSupport() => true,
            $user->isEmployee() => $this->isRequester($user, $ticket),
            default => false,
        };
    }

    /** Any active user may raise a ticket (it is always owned by its creator). */
    public function create(User $user): bool
    {
        return true;
    }

    /** Edit core fields (title, description, category...). Staff only. */
    public function update(User $user, Ticket $ticket): bool
    {
        return $this->canWork($user, $ticket);
    }

    public function updateStatus(User $user, Ticket $ticket): bool
    {
        return $this->canWork($user, $ticket);
    }

    public function updatePriority(User $user, Ticket $ticket): bool
    {
        return $this->canWork($user, $ticket);
    }

    /**
     * Assign / reassign / unassign (via TicketAssignmentService).
     * Support may claim an unassigned ticket or hand over one they hold;
     * they may NOT take a ticket away from a colleague. Admin may do anything.
     */
    public function assign(User $user, Ticket $ticket): bool
    {
        return match (true) {
            $user->isAdmin() => true,
            $user->isSupport() => $this->inSupportScope($user, $ticket),
            default => false,
        };
    }

    /** Add a public (requester-visible) comment. */
    public function comment(User $user, Ticket $ticket): bool
    {
        return match (true) {
            $user->isAdmin() => true,
            $user->isSupport() => $this->inSupportScope($user, $ticket),
            $user->isEmployee() => $this->isRequester($user, $ticket),
            default => false,
        };
    }

    /** Add an internal note (never visible to the requester). Staff only. */
    public function addInternalNote(User $user, Ticket $ticket): bool
    {
        return $user->isStaff() && $this->comment($user, $ticket);
    }

    /** Whether internal notes (and attachments on them) may be seen. Staff only. */
    public function viewInternalNotes(User $user, Ticket $ticket): bool
    {
        return $user->isStaff() && $this->view($user, $ticket);
    }

    public function uploadAttachment(User $user, Ticket $ticket): bool
    {
        return $this->comment($user, $ticket);
    }

    /* ---------------------------------------------------------------------
     | Helpers
     |---------------------------------------------------------------------*/

    private function isRequester(User $user, Ticket $ticket): bool
    {
        return $ticket->user_id === $user->getKey();
    }

    /** Support agent may see tickets they currently hold, or nobody holds. */
    private function inSupportScope(User $user, Ticket $ticket): bool
    {
        return $this->isAssignedTo($user, $ticket) || $this->isUnassigned($ticket);
    }

    /** May change the ticket's working state: admin, or the agent who holds it. */
    private function canWork(User $user, Ticket $ticket): bool
    {
        return match (true) {
            $user->isAdmin() => true,
            $user->isSupport() => $this->isAssignedTo($user, $ticket),
            default => false,
        };
    }

    private function isAssignedTo(User $user, Ticket $ticket): bool
    {
        return $ticket->assignments()->current()->where('assigned_to', $user->getKey())->exists();
    }

    private function isUnassigned(Ticket $ticket): bool
    {
        return ! $ticket->assignments()->current()->exists();
    }
}
