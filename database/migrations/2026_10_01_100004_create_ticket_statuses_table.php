<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            // Stable machine identifier used by application code (see TicketStatusSlug enum).
            $table->string('slug', 50)->unique();
            $table->text('description')->nullable();
            $table->string('color', 7);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            // No soft deletes: statuses drive workflow logic, so they are deactivated
            // (is_active = false) rather than deleted. FK RESTRICT protects referenced rows.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_statuses');
    }
};
