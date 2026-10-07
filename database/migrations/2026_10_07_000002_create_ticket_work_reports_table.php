<?php

use App\Enums\TechnicianWorkReviewStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_work_reports', function (Blueprint $table) {
            $table->id();

            // A report belongs to one assignment-history row, retaining the
            // exact technician assignment even after a later reassignment.
            $table->foreignId('ticket_assignment_id')
                ->constrained('ticket_assignments')
                ->cascadeOnDelete();

            $table->foreignId('started_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('work_started_at')->nullable();
            $table->timestamp('work_completed_at')->nullable();
            $table->text('work_summary')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('technician_notes')->nullable();

            $table->enum('review_status', TechnicianWorkReviewStatus::values())
                ->default(TechnicianWorkReviewStatus::Pending->value);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['ticket_assignment_id', 'work_started_at'], 'ticket_work_reports_assignment_started_index');
            $table->index(['review_status', 'reviewed_at'], 'ticket_work_reports_review_status_reviewed_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_work_reports');
    }
};
