<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            // The unique constraint also provides the index used for name lookups.
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            // Soft delete: departments are referenced by historical tickets/users.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
