<?php

namespace App\Services;

use App\Enums\TicketStatusSlug;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketWorkflowService
{
    public function __construct(private readonly TicketNotificationService $notifications) {}

    private const TRANSITIONS = [
        'open' => ['assigned'],
        'assigned' => ['open', 'in_progress'],
        'in_progress' => ['assigned', 'waiting_for_user'],
        'waiting_for_user' => ['in_progress', 'resolved'],
        'resolved' => ['waiting_for_user', 'closed'],
        'closed' => ['resolved'],
    ];

    public function transition(Ticket $ticket, TicketStatusSlug $target, User $actor): Ticket
    {
        return DB::transaction(function () use ($ticket, $target, $actor) {
            $ticket = Ticket::query()->lockForUpdate()->findOrFail($ticket->getKey());
            $actor->can('updateStatus', $ticket) || abort(403);

            $current = $ticket->status()->value('slug');
            if (! in_array($target->value, self::TRANSITIONS[$current] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "Cannot transition from {$current} to {$target->value}."]);
            }

            $ticket->status_id = TicketStatus::forSlug($target)->getKey();
            if ($target === TicketStatusSlug::Resolved && $current !== TicketStatusSlug::Closed->value) {
                $ticket->resolved_at = now();
            } elseif (! in_array($target, [TicketStatusSlug::Resolved, TicketStatusSlug::Closed], true)) {
                $ticket->resolved_at = null;
            }
            $ticket->closed_at = $target === TicketStatusSlug::Closed ? now() : null;
            $ticket->save();
            $this->notifications->statusChanged($ticket, $actor, $target);

            return $ticket;
        });
    }
}
