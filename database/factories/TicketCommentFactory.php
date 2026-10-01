<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketComment>
 */
class TicketCommentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(12),
            'is_internal' => false,
        ];
    }

    /**
     * An internal IT note (authored by IT staff).
     */
    public function internal(): static
    {
        return $this->state(fn () => [
            'user_id' => User::factory()->support(),
            'is_internal' => true,
        ]);
    }
}
