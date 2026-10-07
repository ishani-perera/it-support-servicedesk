<?php

namespace App\Policies;

use App\Models\TicketWorkReport;
use App\Models\User;

/**
 * Work report routes are not part of the database-foundation change. Deny
 * access until an explicit workflow defines technician and reviewer actions.
 */
class TicketWorkReportPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_active ? null : false;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, TicketWorkReport $report): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
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
