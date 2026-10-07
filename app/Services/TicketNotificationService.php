<?php

namespace App\Services;

use App\Enums\TicketStatusSlug;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketEventNotification;

class TicketNotificationService
{
    public function ticketCreated(Ticket $ticket, User $actor): void
    {
        $recipients = User::query()->active()->supportAgents()->get();
        $this->send($ticket, $actor, $recipients, 'New ticket submitted', 'A new support request needs triage.');
    }

    public function assignmentChanged(Ticket $ticket, User $actor, User $assignee, ?User $previousAssignee = null): void
    {
        $this->send($ticket, $actor, collect([$assignee]), 'Ticket assigned to you', 'A ticket has been assigned to you.');

        if ($previousAssignee && $previousAssignee->isNot($assignee)) {
            $this->send($ticket, $actor, collect([$previousAssignee]), 'Ticket reassigned', 'A ticket has been reassigned to another support agent.');
        }
    }

    public function statusChanged(Ticket $ticket, User $actor, TicketStatusSlug $status): void
    {
        $ticket->loadMissing(['user', 'currentAssignment.assignee']);
        $recipients = collect([$ticket->user]);

        if ($status !== TicketStatusSlug::Assigned && $ticket->currentAssignment?->assignee) {
            $recipients->push($ticket->currentAssignment->assignee);
        }

        [$title, $message] = match ($status) {
            TicketStatusSlug::Resolved => ['Ticket resolved', 'The ticket has been marked as resolved.'],
            TicketStatusSlug::Closed => ['Ticket closed', 'The ticket has been closed.'],
            default => ['Ticket status updated', 'The ticket status changed to '.$status->label().'.'],
        };

        $this->send($ticket, $actor, $recipients, $title, $message);
    }

    public function publicCommentAdded(Ticket $ticket, User $actor): void
    {
        $ticket->loadMissing(['user', 'currentAssignment.assignee']);

        if ($ticket->user_id === $actor->getKey()) {
            $assignedAgent = $ticket->currentAssignment?->assignee;
            $recipients = $assignedAgent
                ? collect([$assignedAgent])
                : User::query()->active()->supportAgents()->get();
            $this->send($ticket, $actor, $recipients, 'New public reply', 'The requester added a reply to the ticket.');

            return;
        }

        $title = $actor->isTechnician() ? 'Technician replied' : 'IT Support replied';
        $this->send($ticket, $actor, collect([$ticket->user]), $title, 'A public response was added to your ticket.');
    }

    public function informationRequested(Ticket $ticket, User $technician): void
    {
        $ticket->loadMissing('user');
        $this->send($ticket, $technician, collect([$ticket->user]), 'More information needed', 'The Technician requested additional information on your ticket.');
    }

    public function workSubmittedForReview(Ticket $ticket, User $technician): void
    {
        $this->send($ticket, $technician, User::query()->active()->staff()->get(), 'Technician work ready for review', 'Completed technician work is waiting for IT Support review.');
    }

    public function workSentBack(Ticket $ticket, User $reviewer): void
    {
        $ticket->loadMissing('currentAssignment.assignee');
        $this->send($ticket, $reviewer, collect([$ticket->currentAssignment?->assignee]), 'Work needs changes', 'IT Support returned your work report with a review note.');
    }

    /** @param iterable<User|null> $recipients */
    private function send(Ticket $ticket, User $actor, iterable $recipients, string $title, string $message): void
    {
        $sent = [];
        foreach ($recipients as $recipient) {
            if (! $recipient instanceof User || ! $recipient->is_active || $recipient->is($actor)) {
                continue;
            }

            $id = (string) $recipient->getKey();
            if (isset($sent[$id]) || ! $recipient->can('view', $ticket)) {
                continue;
            }

            $sent[$id] = true;
            $recipient->notify(new TicketEventNotification($title, $message, (int) $ticket->getKey(), $ticket->ticket_number));
        }
    }
}
