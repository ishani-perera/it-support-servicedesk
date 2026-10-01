<?php

namespace Tests\Feature\Database;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTicketFixtures;
use Tests\Concerns\SeedsMasterData;
use Tests\TestCase;

class RelationshipsTest extends TestCase
{
    use BuildsTicketFixtures;
    use RefreshDatabase;
    use SeedsMasterData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMasterData();
    }

    public function test_department_has_many_users_and_tickets(): void
    {
        $ticket = $this->makeTicket();
        $department = $ticket->department;

        $this->assertTrue($department->users->contains($ticket->user));
        $this->assertTrue($department->tickets->contains($ticket));
    }

    public function test_ticket_belongs_to_its_reference_data_and_requester(): void
    {
        $ticket = $this->makeTicket()->fresh();

        $this->assertInstanceOf(User::class, $ticket->user);
        $this->assertInstanceOf(Department::class, $ticket->department);
        $this->assertInstanceOf(TicketCategory::class, $ticket->category);
        $this->assertInstanceOf(TicketPriority::class, $ticket->priority);
        $this->assertInstanceOf(TicketStatus::class, $ticket->status);

        // Inverse sides.
        $this->assertTrue($ticket->category->tickets->contains($ticket));
        $this->assertTrue($ticket->priority->tickets->contains($ticket));
        $this->assertTrue($ticket->status->tickets->contains($ticket));
        $this->assertTrue($ticket->user->tickets->contains($ticket));
    }

    public function test_user_belongs_to_department(): void
    {
        $department = Department::where('name', 'IT')->firstOrFail();
        $user = $this->makeUser(UserRole::Support, $department);

        $this->assertTrue($user->department->is($department));
    }

    public function test_comment_relationships(): void
    {
        $ticket = $this->makeTicket();
        $author = $this->makeUser(UserRole::Support);
        $comment = $this->makeComment($ticket, $author, internal: true);

        $this->assertTrue($ticket->comments->contains($comment));
        $this->assertTrue($author->comments->contains($comment));
        $this->assertTrue($comment->ticket->is($ticket));
        $this->assertTrue($comment->user->is($author));
        $this->assertTrue($comment->is_internal);
    }

    public function test_attachment_relationships_for_ticket_and_comment_files(): void
    {
        $ticket = $this->makeTicket();
        $uploader = $ticket->user;
        $comment = $this->makeComment($ticket, $uploader);

        $ticketFile = $this->makeAttachment($ticket, $uploader);
        $commentFile = $this->makeAttachment($ticket, $uploader, $comment);

        $this->assertNull($ticketFile->comment);
        $this->assertTrue($commentFile->comment->is($comment));
        $this->assertTrue($commentFile->ticket->is($ticket));
        $this->assertTrue($commentFile->uploader->is($uploader));

        $this->assertCount(2, $ticket->attachments);
        $this->assertSame([$commentFile->id], $comment->attachments->pluck('id')->all());
        $this->assertCount(2, $uploader->attachments);
    }

    public function test_assignment_relationships_and_history(): void
    {
        $ticket = $this->makeTicket();
        $admin = $this->makeUser(UserRole::Admin);
        $first = $this->makeUser(UserRole::Support);
        $second = $this->makeUser(UserRole::Support);

        // Assign to $first, then reassign to $second — history must be preserved.
        $old = $this->makeAssignment($ticket, $first, $admin, ['assigned_at' => now()->subHour()]);
        $old->update(['unassigned_at' => now()]);
        $current = $this->makeAssignment($ticket, $second, $admin, ['note' => 'Escalated']);

        $this->assertCount(2, $ticket->assignments);
        $this->assertTrue($current->assignee->is($second));
        $this->assertTrue($current->assigner->is($admin));
        $this->assertTrue($current->ticket->is($ticket));

        $this->assertTrue($ticket->currentAssignment->is($current));
        $this->assertCount(2, $admin->createdAssignments);

        // User side: history vs. currently assigned tickets.
        $this->assertCount(1, $first->assignments);
        $this->assertCount(0, $first->assignedTickets, 'Past assignee no longer has the ticket');
        $this->assertSame([$ticket->id], $second->assignedTickets->pluck('id')->all());
    }

    public function test_soft_deleted_parents_still_resolve_on_historical_tickets(): void
    {
        $ticket = $this->makeTicket();
        $ticket->category->delete();
        $ticket->priority->delete();
        $ticket->user->delete();

        $ticket = Ticket::findOrFail($ticket->id);

        $this->assertNotNull($ticket->category);
        $this->assertNotNull($ticket->priority);
        $this->assertNotNull($ticket->user);
        $this->assertTrue($ticket->user->trashed());
    }
}
