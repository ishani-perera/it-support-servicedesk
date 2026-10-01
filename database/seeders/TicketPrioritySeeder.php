<?php

namespace Database\Seeders;

use App\Enums\TicketPriorityLevel;
use App\Models\TicketPriority;
use Illuminate\Database\Seeder;

class TicketPrioritySeeder extends Seeder
{
    /**
     * Values come from the TicketPriorityLevel enum (single source of truth).
     * Idempotent: rows are matched on the unique numeric level.
     */
    public function run(): void
    {
        foreach (TicketPriorityLevel::cases() as $level) {
            TicketPriority::withTrashed()->updateOrCreate(
                ['level' => $level->value],
                [
                    'name' => $level->label(),
                    'description' => $level->description(),
                    'color' => $level->color(),
                    'sla_response_minutes' => $level->slaResponseMinutes(),
                    'sla_resolution_minutes' => $level->slaResolutionMinutes(),
                    'is_active' => true,
                ],
            );
        }
    }
}
