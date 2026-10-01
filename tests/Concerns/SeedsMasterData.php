<?php

namespace Tests\Concerns;

use Database\Seeders\DepartmentSeeder;
use Database\Seeders\TicketCategorySeeder;
use Database\Seeders\TicketPrioritySeeder;
use Database\Seeders\TicketStatusSeeder;

trait SeedsMasterData
{
    /**
     * Seeds ONLY the reference data (departments, categories, priorities, statuses) —
     * no demo users/tickets — so tests control exactly which rows exist.
     */
    protected function seedMasterData(): void
    {
        $this->seed([
            DepartmentSeeder::class,
            TicketCategorySeeder::class,
            TicketPrioritySeeder::class,
            TicketStatusSeeder::class,
        ]);
    }
}
