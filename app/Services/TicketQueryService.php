<?php

namespace App\Services;

use App\Enums\TicketStatusSlug;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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
        if (($filters['assignment'] ?? null) === 'unassigned') {
            $query->unassigned();
        } elseif (($filters['assignment'] ?? null) === 'mine') {
            $query->assignedTo($user);
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
                ->orWhere('description', 'like', '%'.$term.'%')->orWhere('ticket_number', 'like', '%'.$term.'%')
                ->orWhereHas('user', fn (Builder $requester) => $requester->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%')));
        }

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->oldest('created_at')->orderBy('id'),
            'updated' => $query->latest('updated_at')->orderByDesc('id'),
            'priority' => $query->orderByDesc(DB::table('ticket_priorities')->select('level')->whereColumn('ticket_priorities.id', 'tickets.priority_id'))->latest('updated_at'),
            default => $query->latest('created_at')->orderByDesc('id'),
        };

        return $query->paginate(min((int) ($filters['per_page'] ?? 15), 100))->withQueryString();
    }

    /** @return array<string, Collection<int, Ticket>> */
    public function supportBoard(User $user, array $filters, int $perColumn = 20): array
    {
        $board = [];
        foreach (TicketStatusSlug::cases() as $status) {
            $board[$status->value] = $this->supportQuery($user, $filters)
                ->whereHas('status', fn (Builder $query) => $query->where('slug', $status->value))
                ->withListRelations()
                ->limit($perColumn)
                ->get();
        }

        return $board;
    }

    public function supportStats(User $user): array
    {
        $counts = Ticket::query()->visibleTo($user)
            ->join('ticket_statuses', 'tickets.status_id', '=', 'ticket_statuses.id')
            ->selectRaw('ticket_statuses.slug, COUNT(*) as aggregate')
            ->groupBy('ticket_statuses.slug')->pluck('aggregate', 'slug');
        $mine = Ticket::query()->visibleTo($user)->assignedTo($user)->count();
        $active = Ticket::query()->visibleTo($user)->whereNotIn('tickets.status_id', function ($query) {
            $query->select('id')->from('ticket_statuses')->whereIn('slug', [TicketStatusSlug::Resolved->value, TicketStatusSlug::Closed->value]);
        })->count();

        return [
            ['label' => 'Assigned to me', 'count' => $mine, 'assignment' => 'mine'],
            ['label' => 'Open tickets', 'count' => (int) ($counts[TicketStatusSlug::Open->value] ?? 0), 'status' => TicketStatusSlug::Open->value],
            ['label' => 'In progress', 'count' => (int) ($counts[TicketStatusSlug::InProgress->value] ?? 0), 'status' => TicketStatusSlug::InProgress->value],
            ['label' => 'Waiting for user', 'count' => (int) ($counts[TicketStatusSlug::WaitingForUser->value] ?? 0), 'status' => TicketStatusSlug::WaitingForUser->value],
            ['label' => 'Resolved', 'count' => (int) ($counts[TicketStatusSlug::Resolved->value] ?? 0), 'status' => TicketStatusSlug::Resolved->value],
            ['label' => 'Total active tickets', 'count' => $active],
        ];
    }

    private function supportQuery(User $user, array $filters): Builder
    {
        $query = Ticket::query()->visibleTo($user);
        foreach (['priority_id', 'category_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (isset($filters['assignment'])) {
            $filters['assignment'] === 'unassigned' ? $query->unassigned() : $query->assignedTo($user);
        }
        if (isset($filters['assigned_to'])) {
            $query->assignedTo((int) $filters['assigned_to']);
        }
        if (isset($filters['status'])) {
            $query->whereHas('status', fn (Builder $status) => $status->where('slug', $filters['status']));
        }
        if (isset($filters['search'])) {
            $term = $filters['search'];
            $query->where(fn (Builder $q) => $q->where('title', 'like', "%{$term}%")
                ->orWhere('ticket_number', 'like', "%{$term}%")
                ->orWhereHas('user', fn (Builder $requester) => $requester->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")));
        }

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->oldest('created_at')->orderBy('id'),
            'updated' => $query->latest('updated_at')->orderByDesc('id'),
            'priority' => $query->orderByDesc(DB::table('ticket_priorities')->select('level')->whereColumn('ticket_priorities.id', 'tickets.priority_id'))->latest('updated_at'),
            default => $query->latest('created_at')->orderByDesc('id'),
        };

        return $query;
    }
}
