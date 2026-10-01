<?php

namespace Database\Seeders;

use App\Enums\TicketStatusSlug;
use App\Models\TicketStatus;
use Illuminate\Database\Seeder;

class TicketStatusSeeder extends Seeder
{
    /**
     * Values come from the TicketStatusSlug enum (single source of truth).
     * Idempotent: rows are matched on the unique slug.
     */
    public function run(): void
    {
        foreach (TicketStatusSlug::cases() as $status) {
            TicketStatus::updateOrCreate(
                ['slug' => $status->value],
                [
                    'name' => $status->label(),
                    'description' => $status->description(),
                    'color' => $status->color(),
                    'sort_order' => $status->sortOrder(),
                    'is_active' => true,
                ],
            );
        }
    }
}
