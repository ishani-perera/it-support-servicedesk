<?php

namespace Tests\Feature\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTicketFixtures;
use Tests\Concerns\SeedsMasterData;
use Tests\TestCase;

class TicketAttachmentModelTest extends TestCase
{
    use BuildsTicketFixtures;
    use RefreshDatabase;
    use SeedsMasterData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMasterData();
    }

    public function test_file_size_is_always_an_integer_number_of_bytes(): void
    {
        $ticket = $this->makeTicket();
        $attachment = $this->makeAttachment($ticket, $ticket->user, null, ['file_size' => '2048'])->fresh();

        $this->assertSame(2048, $attachment->file_size);
    }

    public function test_human_readable_size_is_display_only(): void
    {
        $ticket = $this->makeTicket();
        $attachment = $this->makeAttachment($ticket, $ticket->user, null, ['file_size' => 1536])->fresh();

        $this->assertSame('1.5 KB', $attachment->size_for_humans);
        $this->assertSame(1536, $attachment->file_size);
    }

    public function test_private_storage_details_never_appear_in_serialised_output(): void
    {
        $ticket = $this->makeTicket();
        $attachment = $this->makeAttachment($ticket, $ticket->user)->fresh();

        $json = $attachment->toJson();

        $this->assertStringNotContainsString($attachment->file_path, $json);
        $this->assertStringNotContainsString($attachment->file_name, $json);
        $this->assertStringContainsString('report.pdf', $json);
    }

    public function test_relationships(): void
    {
        $ticket = $this->makeTicket();
        $comment = $this->makeComment($ticket, $ticket->user);
        $attachment = $this->makeAttachment($ticket, $ticket->user, $comment)->fresh();

        $this->assertTrue($attachment->ticket->is($ticket));
        $this->assertTrue($attachment->comment->is($comment));
        $this->assertTrue($attachment->uploader->is($ticket->user));
    }
}
