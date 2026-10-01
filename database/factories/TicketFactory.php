<?php

namespace Database\Factories;

use App\Enums\TicketPriorityLevel;
use App\Enums\TicketStatusSlug;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/**
 * Builds tickets against the SEEDED master data (categories, priorities,
 * statuses, departments), so call the master-data seeders first.
 * `ticket_number` is system-managed (not fillable); factories bypass
 * mass-assignment, so a unique placeholder is generated here — the real
 * concurrency-safe generator arrives in a later phase.
 *
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ticket_number' => sprintf('INC-%d-%06d', now()->year, fake()->unique()->numberBetween(1, 999_999)),
            // Requester: its department is copied onto the ticket (see the tickets migration).
            'user_id' => User::factory(),
            'department_id' => fn (array $attributes) => User::query()->find($attributes['user_id'])?->department_id
                ?? self::anyId(Department::query()),
            'category_id' => fn () => self::anyId(TicketCategory::query()),
            'priority_id' => fn () => TicketPriority::forLevel(TicketPriorityLevel::Medium)->getKey(),
            'status_id' => fn () => TicketStatus::forSlug(TicketStatusSlug::Open)->getKey(),
            'title' => fake()->randomElement([
                'Unable to connect to company VPN',
                'Outlook is not syncing emails',
                'Laptop running extremely slowly',
                'Printer not responding',
            ]),
            'description' => fake()->paragraph(),
            'resolution' => null,
            'resolved_at' => null,
            'closed_at' => null,
        ];
    }

    public function withStatus(TicketStatusSlug $slug): static
    {
        return $this->state(fn () => ['status_id' => TicketStatus::forSlug($slug)->getKey()]);
    }

    public function withPriority(TicketPriorityLevel $level): static
    {
        return $this->state(fn () => ['priority_id' => TicketPriority::forLevel($level)->getKey()]);
    }

    public function resolved(): static
    {
        return $this->withStatus(TicketStatusSlug::Resolved)->state(fn () => [
            'resolution' => fake()->sentence(),
            'resolved_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->withStatus(TicketStatusSlug::Closed)->state(fn () => [
            'resolution' => fake()->sentence(),
            'resolved_at' => now()->subHour(),
            'closed_at' => now(),
        ]);
    }

    /**
     * @param  Builder<*>  $query
     */
    private static function anyId(Builder $query): int
    {
        return $query->orderBy('id')->value('id')
            ?? throw new LogicException('Master data is missing — run the master-data seeders before using TicketFactory.');
    }
}
