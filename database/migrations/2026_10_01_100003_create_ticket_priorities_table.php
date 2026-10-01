<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_priorities', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->text('description')->nullable();
            // Numeric ordering: higher level = more urgent. Unique so ordering is unambiguous.
            $table->unsignedSmallInteger('level')->unique();
            // Hex colour, e.g. #DC2626.
            $table->string('color', 7);
            $table->unsignedInteger('sla_response_minutes');
            $table->unsignedInteger('sla_resolution_minutes');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            // Soft delete: priorities are referenced by historical tickets.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_priorities');
    }
};
