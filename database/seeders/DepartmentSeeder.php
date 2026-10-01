<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * @var array<string, string> name => description
     */
    public const DEPARTMENTS = [
        'IT' => 'Information Technology — owns infrastructure, applications and end-user support.',
        'Finance' => 'Accounting, payroll, budgeting and financial reporting.',
        'HR' => 'Human Resources — recruitment, onboarding, employee relations and benefits.',
        'Sales' => 'Business development, account management and customer acquisition.',
        'Marketing' => 'Brand, campaigns, content and market research.',
        'Operations' => 'Day-to-day business operations, logistics and service delivery.',
        'Management' => 'Executive leadership and senior management.',
    ];

    /**
     * Idempotent: safe to run repeatedly; rows are matched on the unique name.
     */
    public function run(): void
    {
        foreach (self::DEPARTMENTS as $name => $description) {
            Department::withTrashed()->updateOrCreate(
                ['name' => $name],
                ['description' => $description, 'is_active' => true],
            );
        }
    }
}
