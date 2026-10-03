<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

class NotificationAccessService
{
    /**
     * Only notifications for tickets currently visible under Ticket::visibleTo
     * are returned. Notification rows are always scoped to the owning user.
     *
     * @return MorphMany<DatabaseNotification, User>
     */
    public function visibleTo(User $user): MorphMany
    {
        return $user->notifications()->whereIn(
            DB::raw("JSON_UNQUOTE(JSON_EXTRACT(notifications.data, '$.ticket_id'))"),
            Ticket::query()->visibleTo($user)->select('tickets.id'),
        );
    }
}
