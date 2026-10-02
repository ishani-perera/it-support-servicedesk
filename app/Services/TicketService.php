<?php

namespace App\Services;

use App\Enums\TicketStatusSlug;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TicketService
{
    public function __construct(private readonly TicketNumberService $numbers) {}

    public function create(User $requester, array $attributes): Ticket
    {
        return DB::transaction(function () use ($requester, $attributes) {
            $ticket = new Ticket;
            $ticket->ticket_number = $this->numbers->next();
            $ticket->user_id = $requester->getKey();
            $ticket->department_id = $requester->department_id ?? $attributes['department_id'];
            $ticket->category_id = $attributes['category_id'];
            $ticket->priority_id = $attributes['priority_id'];
            $ticket->status_id = TicketStatus::forSlug(TicketStatusSlug::Open)->getKey();
            $ticket->title = $attributes['title'];
            $ticket->description = $attributes['description'];
            $ticket->save();

            return $ticket;
        });
    }

    public function update(Ticket $ticket, array $attributes): Ticket
    {
        $ticket->fill($attributes);
        $ticket->save();

        return $ticket;
    }
}
