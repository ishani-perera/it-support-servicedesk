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
        if ($assignee->isTechnician()) {
            $this->send(
                $ticket,
                $actor,
                collect([$assignee]),
                'Ticket assigned to you',
                $actor->name.' assigned this ticket to you for investigation.',
                ['assigned_by' => $actor->name],
            );
            $supportRecipients = User::query()->active()->supportAgents()->get();
            if ($previousAssignee) {
                $supportRecipients = $supportRecipients->reject(fn (User $recipient): bool => $recipient->is($previousAssignee));
            }
            $this->send(
                $ticket,
                $actor,
                $supportRecipients,
                'Technician assigned',
                $actor->name.' assigned '.$assignee->name.' to this ticket.',
                ['assigned_to' => $assignee->name, 'assigned_by' => $actor->name],
            );
        } else {
            $this->send($ticket, $actor, collect([$assignee]), 'Ticket assigned to you', 'A ticket has been assigned to you.');
        }

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
        $this->send($ticket, $technician, collect([$ticket->user]), 'Action required: more information needed', 'The Technician needs information from you to continue work on this ticket.');
    }

    public function technicianStartedWork(Ticket $ticket, User $technician): void
    {
        $this->send(
            $ticket,
            $technician,
            User::query()->active()->supportAgents()->get(),
            'Technician started work',
            $technician->name.' started work on this ticket.',
            ['technician' => $technician->name],
        );
    }

    public function internalCommentAdded(Ticket $ticket, User $actor): void
    {
        $ticket->loadMissing('currentAssignment.assignee');
        $technician = $ticket->currentAssignment?->assignee;

        if ($actor->isTechnician()) {
            $this->send(
                $ticket,
                $actor,
                User::query()->active()->supportAgents()->get(),
                'Technician added an internal note',
                $actor->name.' added an internal note for IT Support.',
                ['technician' => $actor->name],
            );

            return;
        }

        if ($technician?->isTechnician()) {
            $this->send(
                $ticket,
                $actor,
                collect([$technician]),
                'IT Support added an internal note',
                'IT Support added an internal note for the Technician.',
            );
        }
    }

    public function workSubmittedForReview(Ticket $ticket, User $technician): void
    {
        $this->send(
            $ticket,
            $technician,
            User::query()->active()->supportAgents()->get(),
            'Technician work ready for review',
            'Completed Technician work is waiting for IT Support review.',
            ['technician' => $technician->name],
        );
    }

    public function workSentBack(Ticket $ticket, User $reviewer, ?string $reviewReason): void
    {
        $ticket->loadMissing('currentAssignment.assignee');
        $reason = trim((string) $reviewReason);
        $this->send(
            $ticket,
            $reviewer,
            collect([$ticket->currentAssignment?->assignee]),
            'Work needs changes',
            'IT Support returned your work report for revision. Review reason: '.$reason,
            ['reviewed_by' => $reviewer->name, 'review_reason' => $reason],
        );
    }

    /** @param iterable<User|null> $recipients */
    private function send(Ticket $ticket, User $actor, iterable $recipients, string $title, string $message, array $context = []): void
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
            $recipient->notify(new TicketEventNotification(
                $title,
                $message,
                (int) $ticket->getKey(),
                $ticket->ticket_number,
                $ticket->title,
                $context,
            ));
        }
    }
}
