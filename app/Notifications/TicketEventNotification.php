<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class TicketEventNotification extends Notification
{
    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly int $ticketId,
        public readonly string $ticketNumber,
        public readonly ?string $ticketTitle = null,
        public readonly array $context = [],
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return array_filter([
            'title' => $this->title,
            'message' => $this->message,
            'ticket_id' => $this->ticketId,
            'ticket_number' => $this->ticketNumber,
            'ticket_title' => $this->ticketTitle,
            'context' => $this->context === [] ? null : $this->context,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
