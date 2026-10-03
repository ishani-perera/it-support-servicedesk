<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsAuthScenario;
use Tests\Concerns\BuildsTicketFixtures;
use Tests\Concerns\SeedsMasterData;
use Tests\TestCase;

class TicketConversationTest extends TestCase
{
    use BuildsAuthScenario, BuildsTicketFixtures, RefreshDatabase, SeedsMasterData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildAuthScenario();
        Storage::fake('local');
        Storage::disk('local')->put($this->attA->file_path, 'ticket file');
        Storage::disk('local')->put($this->attInternalA->file_path, 'internal file');
        Storage::disk('local')->put($this->attB->file_path, 'colleague file');
    }

    public function test_employee_can_post_a_public_reply_but_cannot_create_or_view_internal_notes(): void
    {
        $this->actingAs($this->employeeA)->withHeader('Accept', 'text/html')
            ->post('/tickets/'.$this->ticketA->id.'/comments', [
                'body' => 'Employee public update',
                '_html_form' => '1',
            ])->assertRedirect(route('tickets.show', $this->ticketA));

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $this->ticketA->id,
            'user_id' => $this->employeeA->id,
            'body' => 'Employee public update',
            'is_internal' => false,
        ]);

        $this->actingAs($this->employeeA)->postJson('/tickets/'.$this->ticketA->id.'/comments', [
            'body' => 'Employee attempted internal note',
            'is_internal' => true,
        ])->assertForbidden();
        $this->actingAs($this->employeeA)->postJson('/tickets/'.$this->ticketA->id.'/comments', ['body' => ''])
            ->assertUnprocessable()->assertJsonValidationErrors('body');

        $this->actingAs($this->employeeA)->get('/tickets/'.$this->ticketA->id)
            ->assertOk()->assertSee($this->publicA->body)->assertSee('Employee public update')
            ->assertDontSee($this->internalA->body)->assertDontSee('Internal note');
        $this->actingAs($this->employeeA)->getJson('/tickets/'.$this->ticketA->id.'/comments/'.$this->internalA->id)
            ->assertForbidden()->assertJsonMissing(['body' => $this->internalA->body]);
    }

    public function test_support_can_write_and_view_internal_notes_only_in_its_existing_action_scope(): void
    {
        $this->actingAs($this->supportOne)->postJson('/tickets/'.$this->ticketA->id.'/comments', [
            'body' => 'Internal support investigation',
            'is_internal' => true,
        ])->assertCreated()->assertJsonPath('data.is_internal', true);

        $this->actingAs($this->employeeA)->getJson('/tickets/'.$this->ticketA->id)
            ->assertOk()->assertJsonMissing(['body' => 'Internal support investigation']);
        $this->actingAs($this->supportTwo)->getJson('/tickets/'.$this->ticketA->id)
            ->assertOk()->assertJsonFragment(['body' => 'Internal support investigation']);

        $this->actingAs($this->supportOne)->postJson('/tickets/'.$this->ticketB->id.'/comments', [
            'body' => 'Cannot write to colleague assignment',
            'is_internal' => true,
        ])->assertForbidden();
    }

    public function test_employee_details_render_safe_ticket_fields_and_only_authorized_attachments(): void
    {
        $this->attInternalA->update(['original_name' => 'internal-proof.pdf']);
        $this->ticketA->update(['description' => '<script>alert("xss")</script> Safe description']);

        $this->actingAs($this->employeeA)->get('/tickets/'.$this->ticketA->id)
            ->assertOk()
            ->assertSee($this->ticketA->ticket_number)
            ->assertSee($this->ticketA->title)
            ->assertSee($this->ticketA->status->name)
            ->assertSee($this->ticketA->priority->name)
            ->assertSee($this->ticketA->category->name)
            ->assertSee('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt; Safe description', false)
            ->assertDontSee('<script>alert("xss")</script>', false)
            ->assertSee($this->publicA->body)
            ->assertSee($this->employeeA->name)
            ->assertSee('Public reply')
            ->assertSee($this->publicA->created_at->format('M j, Y'))
            ->assertSee($this->attA->original_name)
            ->assertSee($this->employeeA->name)
            ->assertDontSee($this->internalA->body)
            ->assertDontSee($this->attInternalA->original_name)
            ->assertDontSee($this->attA->file_path)
            ->assertDontSee($this->attA->file_name);

        $this->actingAs($this->employeeA)->get('/tickets/'.$this->ticketB->id)->assertForbidden();
    }

    public function test_support_details_render_colleague_ticket_and_internal_note_attachments(): void
    {
        $this->attInternalA->update(['original_name' => 'internal-proof.pdf']);

        $this->actingAs($this->supportTwo)->get('/tickets/'.$this->ticketA->id)
            ->assertOk()
            ->assertSee($this->ticketA->ticket_number)
            ->assertSee($this->ticketA->created_at->format('M j, Y'))
            ->assertSee($this->ticketA->description)
            ->assertSee($this->internalA->body)
            ->assertSee('Internal note')
            ->assertSee('internal-proof.pdf')
            ->assertDontSee($this->attInternalA->file_path)
            ->assertDontSee($this->attInternalA->file_name)
            ->assertDontSee('ticket-comment-body');
    }

    public function test_attachment_upload_validates_files_and_authorizes_upload_scope(): void
    {
        $this->actingAs($this->employeeA)->postJson('/tickets/'.$this->ticketA->id.'/attachments', [
            'file' => UploadedFile::fake()->create('evidence.pdf', 80, 'application/pdf'),
        ])->assertCreated()->assertJsonMissingPath('data.file_path');
        $this->assertDatabaseHas('ticket_attachments', ['ticket_id' => $this->ticketA->id, 'original_name' => 'evidence.pdf']);
        $this->actingAs($this->employeeA)->get('/tickets/'.$this->ticketA->id)
            ->assertOk()->assertSee('evidence.pdf')->assertSee($this->employeeA->name);

        $this->actingAs($this->employeeA)->postJson('/tickets/'.$this->ticketA->id.'/attachments', [
            'file' => UploadedFile::fake()->create('malware.exe', 80, 'application/x-msdownload'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->actingAs($this->employeeA)->postJson('/tickets/'.$this->ticketA->id.'/attachments', [
            'file' => UploadedFile::fake()->create('too-large.pdf', 10241, 'application/pdf'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->actingAs($this->employeeA)->postJson('/tickets/'.$this->ticketB->id.'/attachments', [
            'file' => UploadedFile::fake()->create('blocked.pdf', 10, 'application/pdf'),
        ])->assertForbidden();
        $this->actingAs($this->supportOne)->postJson('/tickets/'.$this->ticketB->id.'/attachments', [
            'file' => UploadedFile::fake()->create('blocked-support.pdf', 10, 'application/pdf'),
        ])->assertForbidden();
    }

    public function test_attachment_download_is_ticket_scoped_and_private(): void
    {
        $this->actingAs($this->employeeA)->get(route('tickets.attachments.show', [$this->ticketB, $this->attB]))->assertForbidden();
        $this->actingAs($this->supportTwo)->get(route('tickets.attachments.show', [$this->ticketA, $this->attB]))->assertNotFound();

        $response = $this->actingAs($this->supportTwo)->get(route('tickets.attachments.show', [$this->ticketA, $this->attA]));
        $response->assertOk()->assertDownload($this->attA->original_name);
        $this->assertSame('ticket file', $response->streamedContent());
        $this->assertStringNotContainsString($this->attA->file_path, (string) json_encode($response->headers->all()));
    }
}
