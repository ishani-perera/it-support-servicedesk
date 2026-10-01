<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;

/**
 * Attachments inherit the access of the ticket they REALLY belong to. An
 * attachment linked to an internal note is staff-only. `download` is a
 * separate ability from `view` so the two can diverge later (e.g. a
 * malware-scan "quarantine" state) without touching callers.
 */
class TicketAttachmentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function view(User $user, TicketAttachment $attachment): bool
    {
        $ticket = $this->ticketOf($attachment);

        if (! $user->can('view', $ticket)) {
            return false;
        }

        return ! $this->isOnInternalNote($attachment) || $user->can('viewInternalNotes', $ticket);
    }

    public function download(User $user, TicketAttachment $attachment): bool
    {
        return $this->view($user, $attachment);
    }

    /**
     * Gate::authorize('create', [TicketAttachment::class, $ticket])
     */
    public function create(User $user, Ticket $ticket): bool
    {
        return $user->can('uploadAttachment', $ticket);
    }

    private function ticketOf(TicketAttachment $attachment): Ticket
    {
        return $attachment->relationLoaded('ticket') ? $attachment->ticket : $attachment->ticket()->firstOrFail();
    }

    private function isOnInternalNote(TicketAttachment $attachment): bool
    {
        if ($attachment->comment_id === null) {
            return false;
        }

        $comment = $attachment->relationLoaded('comment') ? $attachment->comment : $attachment->comment()->first();

        return $comment?->is_internal ?? false;
    }
}
