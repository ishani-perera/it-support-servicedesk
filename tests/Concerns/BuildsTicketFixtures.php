<?php

namespace Tests\Concerns;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\User;

/**
 * Thin helpers over the model factories. Requires master data to be seeded
 * (see SeedsMasterData). Attachments are plain database rows only — no files
 * are ever created (secure uploads belong to a later phase).
 */
trait BuildsTicketFixtures
{
    protected function makeUser(UserRole $role = UserRole::Employee, ?Department $department = null): User
    {
        return User::factory()->role($role)->inDepartment($department)->create();
    }

    protected function makeTicket(?User $requester = null, array $overrides = []): Ticket
    {
        $finance = Department::where('name', 'Finance')->firstOrFail();
        $requester ??= $this->makeUser(UserRole::Employee, $finance);

        return Ticket::factory()->create(array_merge([
            'user_id' => $requester->id,
            'department_id' => $requester->department_id ?? $finance->id,
        ], $overrides));
    }

    protected function makeComment(Ticket $ticket, User $author, bool $internal = false): TicketComment
    {
        return TicketComment::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $author->id,
            'is_internal' => $internal,
        ]);
    }

    protected function makeAttachment(Ticket $ticket, User $uploader, ?TicketComment $comment = null, array $overrides = []): TicketAttachment
    {
        $name = bin2hex(random_bytes(8)).'.pdf';

        return TicketAttachment::create(array_merge([
            'ticket_id' => $ticket->id,
            'comment_id' => $comment?->id,
            'uploaded_by' => $uploader->id,
            'original_name' => 'report.pdf',
            'file_name' => $name,
            'file_path' => 'tickets/'.$ticket->id.'/'.$name,
            'mime_type' => 'application/pdf',
            'file_size' => 12345,
        ], $overrides));
    }

    protected function makeAssignment(Ticket $ticket, User $assignee, User $assigner, array $overrides = []): TicketAssignment
    {
        return TicketAssignment::factory()->create(array_merge([
            'ticket_id' => $ticket->id,
            'assigned_to' => $assignee->id,
            'assigned_by' => $assigner->id,
            'assigned_at' => now(),
        ], $overrides));
    }
}
