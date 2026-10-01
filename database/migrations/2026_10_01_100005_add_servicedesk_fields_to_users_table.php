<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends Laravel's stock users table (0001_01_01_000000_create_users_table)
 * rather than editing it, so the original migration stays untouched and this
 * is safe to run against any database that already ran the default migrations.
 *
 * NOTE: `role` is a DB-level ENUM built from the UserRole enum so the database
 * itself rejects invalid roles. Adding a role later therefore requires BOTH a
 * new case in UserRole AND a new migration altering this column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // RESTRICT: deleting a department must never silently orphan/destroy users.
            // Nullable: administrators (or staff not yet placed) may have no department.
            $table->foreignId('department_id')
                ->nullable()
                ->after('password')
                ->constrained('departments')
                ->restrictOnDelete();

            $table->enum('role', UserRole::values())
                ->default(UserRole::Employee->value)
                ->after('department_id');

            // Unique (NULLs allowed, so multiple users may lack an ID) — doubles as the lookup index.
            $table->string('employee_id', 50)->nullable()->unique()->after('role');
            $table->string('phone', 30)->nullable()->after('employee_id');
            // Stores a relative storage path, never a URL or binary.
            $table->string('avatar')->nullable()->after('phone');
            $table->boolean('is_active')->default(true)->after('avatar');

            // Real query pattern: "active support/admin staff" for assignment pickers.
            // Its leading column also serves plain `where role = ?` lookups.
            $table->index(['role', 'is_active'], 'users_role_is_active_index');

            // Soft delete: users are referenced by tickets, comments and assignment history.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropIndex('users_role_is_active_index');
            $table->dropUnique(['employee_id']);
            $table->dropSoftDeletes();
            $table->dropColumn([
                'department_id',
                'role',
                'employee_id',
                'phone',
                'avatar',
                'is_active',
            ]);
        });
    }
};
