<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per assignment: this table is the full assignment HISTORY.
     * The current assignee is the row whose unassigned_at IS NULL.
     */
    public function up(): void
    {
        Schema::create('ticket_assignments', function (Blueprint $table) {
            $table->id();

            // CASCADE: history belongs to the ticket.
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            // RESTRICT: never lose who worked on / made an assignment.
            $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();

            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('unassigned_at')->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            // Derived (not writable) column: 1 while the assignment is open, NULL once closed.
            // Combined with the unique index below, the DATABASE guarantees a ticket can have
            // at most ONE open assignment (NULLs never collide in a unique index), so a
            // reassignment must close the previous row first. It also serves as the
            // ticket_id FK index.
            $table->boolean('is_current')
                ->nullable()
                ->virtualAs('CASE WHEN unassigned_at IS NULL THEN 1 ELSE NULL END');
            $table->unique(['ticket_id', 'is_current'], 'ticket_assignments_one_open_per_ticket_unique');

            // Query pattern: "tickets currently assigned to staff member X"
            // (assigned_to = ? AND unassigned_at IS NULL). Also serves the assigned_to FK.
            $table->index(['assigned_to', 'unassigned_at'], 'ticket_assignments_assigned_to_unassigned_at_index');
            // assigned_by is indexed by its FK constraint.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_assignments');
    }
};
