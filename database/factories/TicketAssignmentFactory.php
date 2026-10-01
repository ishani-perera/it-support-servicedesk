<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Creates an OPEN assignment by default. Factories write rows directly, so
 * creating a second open assignment for one ticket is rejected by the database
 * (by design). Use TicketAssignmentService for real reassignment flows.
 *
 * @extends Factory<TicketAssignment>
 */
class TicketAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'assigned_to' => User::factory()->support(),
            'assigned_by' => User::factory()->admin(),
            'assigned_at' => now()->subHours(2),
            'unassigned_at' => null,
            'note' => null,
        ];
    }

    /**
     * A closed (historical) assignment.
     */
    public function ended(): static
    {
        return $this->state(fn () => [
            'assigned_at' => now()->subHours(5),
            'unassigned_at' => now()->subHours(3),
        ]);
    }
}
