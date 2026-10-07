<?php

namespace App\Models;

use App\Enums\TicketStatusSlug;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * Mass-assignable attributes.
     *
     * SYSTEM-MANAGED columns are deliberately NOT fillable: ticket number,
     * requester, status, and lifecycle timestamps. Trusted services assign
     * those columns explicitly.
     *
     * @var list<string>
     */
    protected $fillable = [
        'department_id',
        'category_id',
        'priority_id',
        'title',
        'description',
        'resolution',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /* ---------------------------------------------------------------------
     | Accessors / helpers
     |---------------------------------------------------------------------*/

    /**
     * Whether the ticket is currently in the given status.
     *
     * Uses the `status` relation — eager load it when checking many tickets
     * (see scopeWithListRelations) to avoid N+1 queries.
     */
    public function hasStatus(TicketStatusSlug $slug): bool
    {
        return $this->status->slug === $slug->value;
    }

    /* ---------------------------------------------------------------------
     | Scopes
     | Status scopes filter with an indexed sub-select on ticket_statuses.slug
     | (no join, no per-row relationship query), so they compose with anything.
     |---------------------------------------------------------------------*/

    /**
     * @param  Builder<Ticket>  $query
     */
    public function scopeWithStatus(Builder $query, TicketStatusSlug ...$slugs): void
    {
        $query->whereIn('tickets.status_id', function ($sub) use ($slugs) {
            $sub->select('id')
                ->from('ticket_statuses')
                ->whereIn('slug', array_map(fn (TicketStatusSlug $s) => $s->value, $slugs));
        });
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->withStatus(TicketStatusSlug::Open);
    }

    /**
     * Status = "Assigned" (not to be confused with scopeAssignedTo / scopeUnassigned).
     *
     * @param  Builder<Ticket>  $query
     */
    public function scopeAssigned(Builder $query): void
    {
        $query->withStatus(TicketStatusSlug::Assigned);
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    public function scopeInProgress(Builder $query): void
    {
        $query->withStatus(TicketStatusSlug::InProgress);
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    public function scopeWaitingForUser(Builder $query): void
    {
        $query->withStatus(TicketStatusSlug::WaitingForUser);
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    public function scopeResolved(Builder $query): void
    {
        $query->withStatus(TicketStatusSlug::Resolved);
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    public function scopeClosed(Builder $query): void
    {
        $query->withStatus(TicketStatusSlug::Closed);
    }

    /**
     * Tickets with no open assignment (nobody currently owns them).
     * Uses the one-open-assignment-per-ticket index via is_current.
     *
     * @param  Builder<Ticket>  $query
     */
    public function scopeUnassigned(Builder $query): void
    {
        $query->whereNotExists(function ($sub) {
            $sub->selectRaw('1')
                ->from('ticket_assignments')
                ->whereColumn('ticket_assignments.ticket_id', 'tickets.id')
                ->where('ticket_assignments.is_current', true);
        });
    }

    /**
     * Tickets whose CURRENT assignee is the given user.
     *
     * @param  Builder<Ticket>  $query
     */
    public function scopeAssignedTo(Builder $query, User|int $user): void
    {
        $userId = $user instanceof User ? $user->getKey() : $user;

        $query->whereExists(function ($sub) use ($userId) {
            $sub->selectRaw('1')
                ->from('ticket_assignments')
                ->whereColumn('ticket_assignments.ticket_id', 'tickets.id')
                ->where('ticket_assignments.is_current', true)
                ->where('ticket_assignments.assigned_to', $userId);
        });
    }

    /**
     * Tickets requested by the given user.
     *
     * @param  Builder<Ticket>  $query
     */
    public function scopeCreatedBy(Builder $query, User|int $user): void
    {
        $query->where('tickets.user_id', $user instanceof User ? $user->getKey() : $user);
    }

    /**
     * Tickets the given user is allowed to SEE — the query-side twin of
     * TicketPolicy::view(). Use it for every ticket list so a list can never
     * show more than the policy would allow for a single record
     * (a test asserts both agree).
     *
     *  - employee: tickets they requested
     *  - support:  all tickets (view access is shared across the support team)
     *  - admin:    everything
     *  - technician: tickets currently assigned to them
     *  - inactive users: nothing
     *
     * @param  Builder<Ticket>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->is_active) {
            $query->whereRaw('1 = 0');

            return;
        }

        match (true) {
            $user->isAdmin() => null,
            $user->isSupport() => null,
            $user->isEmployee() => $query->createdBy($user),
            $user->isTechnician() => $query->assignedTo($user),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * Eager-loads everything a ticket LIST row needs in a fixed number of
     * queries (no N+1). Models never load these automatically.
     *
     * @param  Builder<Ticket>  $query
     */
    public function scopeWithListRelations(Builder $query): void
    {
        $query->with([
            'user',
            'department',
            'category',
            'priority',
            'status',
            'currentAssignment.assignee',
        ]);
    }

    /* ---------------------------------------------------------------------
     | Relationships
     | Parents that support soft deletes use withTrashed() so historical
     | tickets still resolve their requester/department/category/priority.
     |---------------------------------------------------------------------*/

    /**
     * The requester (employee who raised the ticket).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    /**
     * @return BelongsTo<TicketCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id')->withTrashed();
    }

    /**
     * @return BelongsTo<TicketPriority, $this>
     */
    public function priority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'priority_id')->withTrashed();
    }

    /**
     * @return BelongsTo<TicketStatus, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(TicketStatus::class, 'status_id');
    }

    /**
     * @return HasMany<TicketComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    /**
     * ALL attachments of the ticket, including those attached to comments.
     *
     * @return HasMany<TicketAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    /**
     * Complete assignment history.
     *
     * @return HasMany<TicketAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TicketAssignment::class);
    }

    /**
     * Work reports recorded against assignment history for this ticket.
     *
     * @return HasManyThrough<TicketWorkReport, TicketAssignment, $this>
     */
    public function workReports(): HasManyThrough
    {
        return $this->hasManyThrough(TicketWorkReport::class, TicketAssignment::class);
    }

    /**
     * The open assignment (unassigned_at IS NULL), if any.
     *
     * The database guarantees at most ONE open row per ticket (unique index on
     * ticket_id + is_current), so a plain hasOne is exact — no "latest of many"
     * aggregate sub-query is needed and eager loading stays cheap.
     *
     * @return HasOne<TicketAssignment, $this>
     */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(TicketAssignment::class)->whereNull('unassigned_at');
    }
}
