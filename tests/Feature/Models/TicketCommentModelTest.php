<?php

namespace Tests\Feature\Models;

use App\Enums\UserRole;
use App\Models\TicketComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTicketFixtures;
use Tests\Concerns\SeedsMasterData;
use Tests\TestCase;

class TicketCommentModelTest extends TestCase
{
    use BuildsTicketFixtures;
    use RefreshDatabase;
    use SeedsMasterData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMasterData();
    }

    public function test_ticket_user_and_attachment_relationships(): void
    {
        $ticket = $this->makeTicket();
        $author = $this->makeUser(UserRole::Support);
        $comment = $this->makeComment($ticket, $author);
        $attachment = $this->makeAttachment($ticket, $author, $comment);

        $comment = $comment->fresh();
        $this->assertTrue($comment->ticket->is($ticket));
        $this->assertTrue($comment->user->is($author));
        $this->assertTrue($comment->attachments->contains($attachment));
    }

    public function test_is_internal_is_cast_to_a_real_boolean(): void
    {
        $internal = TicketComment::factory()->internal()->create()->fresh();
        $public = TicketComment::factory()->create()->fresh();

        $this->assertTrue($internal->is_internal);
        $this->assertFalse($public->is_internal);
        $this->assertIsBool($internal->is_internal);
        $this->assertIsBool($public->is_internal);
    }

    public function test_internal_scope(): void
    {
        $ticket = $this->makeTicket();
        $staff = $this->makeUser(UserRole::Support);
        $note = $this->makeComment($ticket, $staff, internal: true);
        $this->makeComment($ticket, $ticket->user);

        $this->assertSame([$note->id], TicketComment::internal()->pluck('id')->all());
    }

    public function test_internal_notes_are_never_returned_for_employees(): void
    {
        $ticket = $this->makeTicket();
        $requester = $ticket->user;
        $staff = $this->makeUser(UserRole::Support);
        $admin = $this->makeUser(UserRole::Admin);

        $public = $this->makeComment($ticket, $requester);
        $reply = $this->makeComment($ticket, $staff);
        $note = $this->makeComment($ticket, $staff, internal: true);

        $employeeView = $ticket->comments()->visibleTo($requester)->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$public->id, $reply->id], $employeeView);
        $this->assertNotContains($note->id, $employeeView);

        $this->assertEqualsCanonicalizing([$public->id, $reply->id], TicketComment::visibleToRequester()->pluck('id')->all());

        foreach ([$staff, $admin] as $itStaff) {
            $this->assertContains($note->id, $ticket->comments()->visibleTo($itStaff)->pluck('id')->all());
        }
    }

    public function test_visible_to_fails_closed_for_a_user_without_a_staff_role(): void
    {
        $ticket = $this->makeTicket();
        $note = $this->makeComment($ticket, $this->makeUser(UserRole::Support), internal: true);

        $outsider = $this->makeUser(UserRole::Employee);

        $this->assertNotContains($note->id, TicketComment::visibleTo($outsider)->pluck('id')->all());
    }
}
