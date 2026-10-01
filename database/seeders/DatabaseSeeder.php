<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Master data first (safe in every environment), then DEVELOPMENT-ONLY demo data.
     * Order respects foreign keys: departments/categories/priorities/statuses ->
     * users -> tickets -> assignments -> comments.
     *
     * Every seeder is idempotent, so `php artisan db:seed` can be re-run safely.
     */
    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            TicketCategorySeeder::class,
            TicketPrioritySeeder::class,
            TicketStatusSeeder::class,
        ]);

        // Demo accounts use a KNOWN password — never seed them in production.
        if (app()->isProduction()) {
            $this->command?->warn('Production environment: demo data skipped.');

            return;
        }

        $this->call([
            DemoUserSeeder::class,
            DemoTicketSeeder::class,
            DemoAssignmentSeeder::class,
            DemoCommentSeeder::class,
        ]);
    }
}
