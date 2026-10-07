<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Mass-assignable attributes.
     *
     * SECURITY: `role`, `is_active` and `email_verified_at` are deliberately NOT
     * fillable, so request input can never escalate privileges or re-activate an
     * account via ->create()/->update()/->fill(). Set them explicitly
     * (e.g. $user->role = UserRole::Support) in trusted code paths only.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'department_id',
        'employee_id',
        'phone',
        'avatar',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    /* ---------------------------------------------------------------------
     | Accessors & role helpers (all role logic delegates to UserRole)
     |---------------------------------------------------------------------*/

    /**
     * Initials of the first and last name for avatar placeholders,
     * e.g. "Priya Fernando" => "PF", "Chamara de Jayasinghe" => "CJ", "Madonna" => "M".
     */
    protected function initials(): Attribute
    {
        return Attribute::get(function (): string {
            $parts = Str::of($this->name)->squish()->explode(' ')->filter()->values();

            if ($parts->isEmpty()) {
                return '';
            }

            return collect([$parts->first(), $parts->count() > 1 ? $parts->last() : null])
                ->filter()
                ->map(fn (string $part) => Str::upper(Str::substr($part, 0, 1)))
                ->implode('');
        });
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isEmployee(): bool
    {
        return $this->role?->isEmployee() ?? false;
    }

    public function isSupport(): bool
    {
        return $this->role?->isSupport() ?? false;
    }

    public function isAdmin(): bool
    {
        return $this->role?->isAdmin() ?? false;
    }

    public function isTechnician(): bool
    {
        return $this->role?->isTechnician() ?? false;
    }

    /**
     * IT staff = support or admin.
     */
    public function isStaff(): bool
    {
        return $this->role?->isStaff() ?? false;
    }

    /* ---------------------------------------------------------------------
     | Scopes
     |---------------------------------------------------------------------*/

    /**
     * @param  Builder<User>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeEmployees(Builder $query): void
    {
        $query->where('role', UserRole::Employee);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeSupportAgents(Builder $query): void
    {
        $query->where('role', UserRole::Support);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeAdmins(Builder $query): void
    {
        $query->where('role', UserRole::Admin);
    }

    /**
     * IT staff (support + admin).
     *
     * @param  Builder<User>  $query
     */
    public function scopeStaff(Builder $query): void
    {
        $query->whereIn('role', UserRole::staff());
    }

    /**
     * Technicians only; this scope does not alter the existing staff scope.
     *
     * @param  Builder<User>  $query
     */
    public function scopeTechnicians(Builder $query): void
    {
        $query->where('role', UserRole::Technician);
    }

    /* ---------------------------------------------------------------------
     | Relationships
     |---------------------------------------------------------------------*/

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    /**
     * Tickets this user REQUESTED (tickets.user_id).
     *
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * @return HasMany<TicketComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    /**
     * Files this user uploaded (ticket_attachments.uploaded_by).
     *
     * @return HasMany<TicketAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class, 'uploaded_by');
    }

    /**
     * Full assignment history rows where this user was the assignee.
     *
     * @return HasMany<TicketAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TicketAssignment::class, 'assigned_to');
    }

    /**
     * Tickets CURRENTLY assigned to this user (open assignment: unassigned_at IS NULL).
     * Use assignments() for the complete history.
     *
     * @return BelongsToMany<Ticket, $this>
     */
    public function assignedTickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, 'ticket_assignments', 'assigned_to', 'ticket_id')
            ->as('assignment')
            ->withPivot(['assigned_by', 'assigned_at', 'note'])
            ->wherePivotNull('unassigned_at');
    }

    /**
     * Assignments this user made to others (ticket_assignments.assigned_by).
     *
     * @return HasMany<TicketAssignment, $this>
     */
    public function createdAssignments(): HasMany
    {
        return $this->hasMany(TicketAssignment::class, 'assigned_by');
    }

    /**
     * Work reports this user started as a Technician.
     *
     * @return HasMany<TicketWorkReport, $this>
     */
    public function startedWorkReports(): HasMany
    {
        return $this->hasMany(TicketWorkReport::class, 'started_by');
    }

    /**
     * Work reports this user reviewed.
     *
     * @return HasMany<TicketWorkReport, $this>
     */
    public function reviewedWorkReports(): HasMany
    {
        return $this->hasMany(TicketWorkReport::class, 'reviewed_by');
    }
}
