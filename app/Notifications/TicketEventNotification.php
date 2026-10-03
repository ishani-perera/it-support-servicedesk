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
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, int|string> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'ticket_id' => $this->ticketId,
            'ticket_number' => $this->ticketNumber,
        ];
    }
}
