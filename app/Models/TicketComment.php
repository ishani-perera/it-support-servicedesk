<?php

namespace App\Models;

use Database\Factories\TicketCommentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketComment extends Model
{
    /** @use HasFactory<TicketCommentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ticket_id',
        'user_id',
        'body',
        'is_internal',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
        ];
    }

    /**
     * Internal IT notes only.
     *
     * @param  Builder<TicketComment>  $query
     */
    public function scopeInternal(Builder $query): void
    {
        $query->where('is_internal', true);
    }

    /**
     * Comments the requester is allowed to see (everything that is NOT internal).
     *
     * @param  Builder<TicketComment>  $query
     */
    public function scopeVisibleToRequester(Builder $query): void
    {
        $query->where('is_internal', false);
    }

    /**
     * The safe default for ANY comment listing: staff see everything, everyone
     * else (employees) never sees internal notes. Fails closed for users
     * without a role. Always build comment queries for end users through this.
     *
     * @param  Builder<TicketComment>  $query
     */
    public function scopeVisibleTo(Builder $query, User $viewer): void
    {
        if (! $viewer->isStaff() && ! $viewer->isTechnician()) {
            $query->where('is_internal', false);
        }
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * Attachments attached to this comment (ticket_attachments.comment_id).
     *
     * @return HasMany<TicketAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class, 'comment_id');
    }
}
