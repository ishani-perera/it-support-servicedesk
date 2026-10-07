<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', UserRole::values())
                ->default(UserRole::Employee->value)
                ->change();
        });
    }

    public function down(): void
    {
        if (DB::table('users')->where('role', UserRole::Technician->value)->exists()) {
            throw new RuntimeException(
                'Technician accounts exist. Reassign them to another role before rolling back the Technician role migration.'
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [UserRole::Employee->value, UserRole::Support->value, UserRole::Admin->value])
                ->default(UserRole::Employee->value)
                ->change();
        });
    }
};
