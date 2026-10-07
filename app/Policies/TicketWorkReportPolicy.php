<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\TicketWorkReport;
use App\Models\User;

/**
 * Work reports are visible only to IT staff and the Technician on their
 * associated assignment. Report mutations go through dedicated workflow
 * actions; generic create/update/delete abilities are always denied.
 */
class TicketWorkReportPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return $user->isStaff();
    }

    public function view(User $user, TicketWorkReport $report): bool
    {
        $assignment = $report->relationLoaded('assignment') ? $report->assignment : $report->assignment()->with('ticket')->first();
        if ($assignment === null) {
            return false;
        }

        $ticket = $assignment->relationLoaded('ticket') ? $assignment->ticket : $assignment->ticket()->first();
        if ($ticket === null || ! $user->can('view', $ticket)) {
            return false;
        }

        return $user->isStaff()
            || ($user->isTechnician() && $assignment->assigned_to === $user->getKey());
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function startWork(User $user, Ticket $ticket): bool
    {
        return $user->can('startWork', $ticket);
    }

    public function requestInformation(User $user, Ticket $ticket): bool
    {
        return $user->can('requestInformation', $ticket);
    }

    public function completeWork(User $user, Ticket $ticket): bool
    {
        return $user->can('completeWork', $ticket);
    }

    public function reviewWork(User $user, Ticket $ticket): bool
    {
        return $user->can('reviewWork', $ticket);
    }

    public function update(User $user, TicketWorkReport $report): bool
    {
        return false;
    }

    public function delete(User $user, TicketWorkReport $report): bool
    {
        return false;
    }
}
