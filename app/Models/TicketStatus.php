<?php

namespace App\Models;

use App\Enums\TicketStatusSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketStatus extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'color',
        'sort_order',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Resolve a built-in status row from its canonical slug.
     */
    public static function forSlug(TicketStatusSlug $slug): self
    {
        return static::query()->where('slug', $slug->value)->firstOrFail();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    public function tickets(): HasMany
    {
        // Tickets reference this table via tickets.status_id.
        return $this->hasMany(Ticket::class, 'status_id');
    }
}
