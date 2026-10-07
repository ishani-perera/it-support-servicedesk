<?php

namespace App\Models;

use Database\Factories\TicketAssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketAssignment extends Model
{
    /** @use HasFactory<TicketAssignmentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ticket_id',
        'assigned_to',
        'assigned_by',
        'assigned_at',
        'unassigned_at',
        'note',
    ];

    /**
     * `is_current` is a database-generated column (see the Phase 01 migration)
     * used to enforce one open assignment per ticket. It is read-only, not
     * fillable, and only present on rows loaded from the database — use
     * isCurrent() / scopeCurrent() in application code.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'unassigned_at' => 'datetime',
            'is_current' => 'boolean',
        ];
    }

    /**
     * Whether this is the ticket's open assignment.
     */
    public function isCurrent(): bool
    {
        return $this->unassigned_at === null;
    }

    /**
     * Open assignments only.
     *
     * @param  Builder<TicketAssignment>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereNull('unassigned_at');
    }

    /**
     * Closed (historical) assignments only.
     *
     * @param  Builder<TicketAssignment>  $query
     */
    public function scopeHistorical(Builder $query): void
    {
        $query->whereNotNull('unassigned_at');
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * The staff member or Technician the ticket was assigned to.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    /**
     * The user who made the assignment.
     *
     * @return BelongsTo<User, $this>
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by')->withTrashed();
    }

    /**
     * Work reports belonging to this exact assignment-history row.
     *
     * @return HasMany<TicketWorkReport, $this>
     */
    public function workReports(): HasMany
    {
        return $this->hasMany(TicketWorkReport::class, 'ticket_assignment_id');
    }
}
