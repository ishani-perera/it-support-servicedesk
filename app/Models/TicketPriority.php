<?php

namespace App\Models;

use App\Enums\TicketPriorityLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketPriority extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'level',
        'color',
        'sla_response_minutes',
        'sla_resolution_minutes',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'sla_response_minutes' => 'integer',
            'sla_resolution_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Resolve a built-in priority row from its canonical level.
     */
    public static function forLevel(TicketPriorityLevel $level): self
    {
        return static::query()->where('level', $level->value)->firstOrFail();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Order from least to most urgent.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('level');
    }

    public function tickets(): HasMany
    {
        // Tickets reference this table via tickets.priority_id.
        return $this->hasMany(Ticket::class, 'priority_id');
    }
}
