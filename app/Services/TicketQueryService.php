<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TicketQueryService
{
    public function paginate(User $user, array $filters)
    {
        $query = Ticket::query()->visibleTo($user)->withListRelations();
        foreach (['priority_id', 'category_id', 'department_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (isset($filters['status'])) {
            $query->whereHas('status', fn (Builder $q) => $q->where('slug', $filters['status']));
        }
        if (isset($filters['requester_id'])) {
            $query->where('user_id', $filters['requester_id']);
        }
        if (isset($filters['assigned_to'])) {
            $query->assignedTo((int) $filters['assigned_to']);
        }
        if (isset($filters['ticket_number'])) {
            $query->where('ticket_number', 'like', '%'.$filters['ticket_number'].'%');
        }
        if (isset($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (isset($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }
        if (isset($filters['search'])) {
            $term = $filters['search'];
            $query->where(fn (Builder $q) => $q->where('title', 'like', '%'.$term.'%')
                ->orWhere('description', 'like', '%'.$term.'%')->orWhere('ticket_number', 'like', '%'.$term.'%'));
        }

        return $query->latest()->paginate(min((int) ($filters['per_page'] ?? 15), 100))->withQueryString();
    }
}
