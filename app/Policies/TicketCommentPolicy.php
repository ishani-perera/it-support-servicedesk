<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;

/**
 * Comments inherit the access of their parent ticket (the ticket the comment
 * REALLY belongs to — never a ticket id taken from the URL), with one extra
 * rule: internal notes are staff-only.
 *
 * Comments remain immutable: no edit or delete ability is defined.
 */
class TicketCommentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function view(User $user, TicketComment $comment): bool
    {
        $ticket = $this->ticketOf($comment);

        if (! $user->can('view', $ticket)) {
            return false;
        }

        return ! $comment->is_internal || $user->can('viewInternalNotes', $ticket);
    }

    /**
     * Gate::authorize('create', [TicketComment::class, $ticket, $internal])
     */
    public function create(User $user, Ticket $ticket, bool $internal = false): bool
    {
        return $internal
            ? $user->can('addInternalNote', $ticket)
            : $user->can('comment', $ticket);
    }

    private function ticketOf(TicketComment $comment): Ticket
    {
        return $comment->relationLoaded('ticket') ? $comment->ticket : $comment->ticket()->firstOrFail();
    }
}
