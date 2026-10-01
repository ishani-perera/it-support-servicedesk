<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DEVELOPMENT ONLY — fictional demo accounts with a KNOWN password.       ║
 * ║  Never seed these into staging/production. This seeder refuses to run    ║
 * ║  when APP_ENV=production.                                                ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * The password is hashed with Laravel's hasher (bcrypt/argon per config) —
 * only the hash is stored. It is applied ONLY when a user is first created,
 * so re-seeding never overwrites a password someone has since changed.
 */
class DemoUserSeeder extends Seeder
{
    /** DEVELOPMENT ONLY. Documented in README.md. */
    public const DEV_PASSWORD = 'ServiceDesk@2026';

    /**
     * email => [name, role, department, employee_id, phone]
     *
     * @var array<string, array{0: string, 1: UserRole, 2: string, 3: string, 4: string}>
     */
    public const USERS = [
        'admin@example.com' => ['Anita Wijesinghe', UserRole::Admin, 'IT', 'EMP-1001', '+94 11 555 0101'],

        'support1@example.com' => ['Ravi Kumar', UserRole::Support, 'IT', 'EMP-1002', '+94 11 555 0102'],
        'support2@example.com' => ['Emily Chen', UserRole::Support, 'IT', 'EMP-1003', '+94 11 555 0103'],
        'support3@example.com' => ['Marcus Johnson', UserRole::Support, 'IT', 'EMP-1004', '+94 11 555 0104'],

        'employee1@example.com' => ['Priya Fernando', UserRole::Employee, 'Finance', 'EMP-2001', '+94 11 555 0201'],
        'employee2@example.com' => ['Daniel Perera', UserRole::Employee, 'Finance', 'EMP-2002', '+94 11 555 0202'],
        'employee3@example.com' => ['Amira Hassan', UserRole::Employee, 'HR', 'EMP-2003', '+94 11 555 0203'],
        'employee4@example.com' => ['Kevin Silva', UserRole::Employee, 'Sales', 'EMP-2004', '+94 11 555 0204'],
        'employee5@example.com' => ['Nadia Rahman', UserRole::Employee, 'Sales', 'EMP-2005', '+94 11 555 0205'],
        'employee6@example.com' => ['Chamara Jayasinghe', UserRole::Employee, 'Marketing', 'EMP-2006', '+94 11 555 0206'],
        'employee7@example.com' => ['Sarah Mitchell', UserRole::Employee, 'Operations', 'EMP-2007', '+94 11 555 0207'],
        'employee8@example.com' => ['Thomas Wright', UserRole::Employee, 'Management', 'EMP-2008', '+94 11 555 0208'],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoUserSeeder creates accounts with a known password and must never run in production.');
        }

        $departments = Department::withTrashed()->pluck('id', 'name');

        DB::transaction(function () use ($departments) {
            foreach (self::USERS as $email => [$name, $role, $department, $employeeId, $phone]) {
                $user = User::withTrashed()->firstOrNew(['email' => $email]);

                $user->fill([
                    'name' => $name,
                    'department_id' => $departments[$department]
                        ?? throw new RuntimeException("Department [{$department}] is missing — run DepartmentSeeder first."),
                    'employee_id' => $employeeId,
                    'phone' => $phone,
                ]);

                // Privileged / system columns are intentionally NOT mass-assignable.
                $user->role = $role;
                $user->is_active = true;
                $user->email_verified_at ??= now();

                if (! $user->exists) {
                    $user->password = self::DEV_PASSWORD; // hashed by the model's "hashed" cast
                }

                $user->save();

                if ($user->trashed()) {
                    $user->restore();
                }
            }
        });
    }
}
