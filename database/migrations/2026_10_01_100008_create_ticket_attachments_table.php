<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();

            // CASCADE: attachments belong to the ticket.
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            // Nullable: an attachment may belong directly to the ticket (no comment).
            $table->unsignedBigInteger('comment_id')->nullable();
            // RESTRICT: keep who uploaded the file.
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();

            $table->string('original_name');            // name as uploaded by the user (display only)
            $table->string('file_name');                // server-generated stored name
            $table->string('file_path', 500)->unique(); // relative storage path; unique so two rows never share one file
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('file_size');    // bytes
            $table->timestamps();

            // Composite FK: when comment_id is set, that comment must belong to the same
            // ticket. (MySQL skips the check when comment_id is NULL, which is why the
            // plain ticket_id FK above is still required.)
            // CASCADE rather than SET NULL on purpose: SET NULL would silently turn an
            // attachment of an INTERNAL comment into a ticket-level attachment visible
            // to the requester. File cleanup on disk is handled by the later upload service.
            $table->foreign(['ticket_id', 'comment_id'], 'ticket_attachments_ticket_comment_foreign')
                ->references(['ticket_id', 'id'])
                ->on('ticket_comments')
                ->cascadeOnDelete();

            // ticket_id lookups use the composite FK index (ticket_id, comment_id) and
            // uploaded_by is indexed by its FK. comment_id needs its own index because
            // "attachments of comment X" queries cannot use the composite one.
            $table->index('comment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_attachments');
    }
};
