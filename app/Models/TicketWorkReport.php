<?php

namespace App\Models;

use App\Enums\TechnicianWorkReviewStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketWorkReport extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'work_summary',
        'root_cause',
        'technician_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'work_started_at' => 'datetime',
            'work_completed_at' => 'datetime',
            'review_status' => TechnicianWorkReviewStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<TicketWorkReport>  $query
     */
    public function scopePendingReview(Builder $query): void
    {
        $query->where('review_status', TechnicianWorkReviewStatus::Pending);
    }

    /**
     * The assignment row this work report records.
     *
     * @return BelongsTo<TicketAssignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TicketAssignment::class, 'ticket_assignment_id');
    }

    /**
     * The technician who started the recorded work, when work has started.
     *
     * @return BelongsTo<User, $this>
     */
    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by')->withTrashed();
    }

    /**
     * The IT Support/Admin reviewer, when the report has been reviewed.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }
}
