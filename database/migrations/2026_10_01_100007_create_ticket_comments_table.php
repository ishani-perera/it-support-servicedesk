<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->id();

            // CASCADE: comments have no meaning without their ticket.
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            // RESTRICT: preserve authorship of the conversation history.
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

            $table->text('body');
            // true = internal IT Support/Admin note (visibility enforced in a later phase).
            $table->boolean('is_internal')->default(false);
            $table->timestamps();

            // Query pattern: a ticket's comments in chronological order. Its leading
            // column also serves as the ticket_id FK index. user_id is indexed by its FK.
            $table->index(['ticket_id', 'created_at'], 'ticket_comments_ticket_id_created_at_index');

            // Allows ticket_attachments to reference (ticket_id, id) with a composite FK,
            // guaranteeing an attachment's comment belongs to the SAME ticket.
            $table->unique(['ticket_id', 'id'], 'ticket_comments_ticket_id_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_comments');
    }
};
