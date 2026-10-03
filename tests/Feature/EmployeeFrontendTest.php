<?php

namespace Tests\Feature;

use App\Enums\TicketStatusSlug;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsAuthScenario;
use Tests\Concerns\BuildsTicketFixtures;
use Tests\Concerns\SeedsMasterData;
use Tests\TestCase;

class EmployeeFrontendTest extends TestCase
{
    use BuildsAuthScenario, BuildsTicketFixtures, RefreshDatabase, SeedsMasterData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildAuthScenario();
        Storage::fake('local');
        Storage::disk('local')->put($this->attA->file_path, 'public attachment');
        Storage::disk('local')->put($this->attB->file_path, 'other requester attachment');
    }

    public function test_employee_dashboard_counts_and_recent_tickets_are_limited_to_requester(): void
    {
        $this->actingAs($this->employeeA)->get('/dashboard')
            ->assertOk()
            ->assertSee('My tickets')
            ->assertSee($this->ticketA->ticket_number)
            ->assertDontSee($this->ticketB->ticket_number);

        $this->actingAs($this->employeeA)->get('/home')->assertOk()->assertSee('How can we help?');
    }

    public function test_my_tickets_page_contains_only_owned_tickets(): void
    {
        $this->actingAs($this->employeeA)->get('/tickets')
            ->assertOk()
            ->assertSee($this->ticketA->ticket_number)
            ->assertSee($this->ticketA2->ticket_number)
            ->assertDontSee($this->ticketB->ticket_number);
    }

    public function test_search_filters_and_pagination_work_in_the_employee_list(): void
    {
        $category = TicketCategory::firstOrFail();
        $priority = TicketPriority::firstOrFail();
        $first = $this->makeTicket($this->employeeA, ['title' => 'front-end-match alpha', 'category_id' => $category->id, 'priority_id' => $priority->id]);
        $second = $this->makeTicket($this->employeeA, ['title' => 'front-end-match beta', 'category_id' => $category->id, 'priority_id' => $priority->id]);

        $this->actingAs($this->employeeA)->get('/tickets?search=front-end-match&priority_id='.$priority->id.'&category_id='.$category->id.'&sort=oldest&per_page=1')
            ->assertOk()
            ->assertSee($first->ticket_number)
            ->assertDontSee($second->ticket_number)
            ->assertDontSee($this->ticketB->ticket_number)
            ->assertSee('Next');
    }

    public function test_create_page_and_validation_errors_are_rendered(): void
    {
        $this->actingAs($this->employeeA)->get('/tickets/create')->assertOk()->assertSee('What do you need help with?');

        $this->from('/tickets/create')
            ->withHeader('Accept', 'text/html')
            ->actingAs($this->employeeA)
            ->post('/tickets', ['title' => '', 'description' => '', 'category_id' => '', 'priority_id' => ''])
            ->assertRedirect('/tickets/create')
            ->assertSessionHasErrors(['title', 'description', 'category_id', 'priority_id']);

        $this->get('/tickets/create')->assertOk()->assertSee('The title field is required.');
    }

    public function test_ticket_creation_uses_the_authenticated_requester_and_uploads_optional_file(): void
    {
        $this->actingAs($this->employeeA)
            ->withHeader('Accept', 'text/html')
            ->post('/tickets', [
                'title' => 'Employee frontend request',
                'description' => 'Created from the employee form.',
                'category_id' => TicketCategory::firstOrFail()->id,
                'priority_id' => TicketPriority::firstOrFail()->id,
                '_html_form' => '1',
                'user_id' => $this->employeeB->id,
                'ticket_number' => 'TKT-1900-000001',
                'status_id' => TicketStatus::forSlug(TicketStatusSlug::Closed)->id,
                'file' => UploadedFile::fake()->create('screen.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $ticket = Ticket::where('title', 'Employee frontend request')->firstOrFail();
        $this->assertSame($this->employeeA->id, $ticket->user_id);
        $this->assertSame(TicketStatusSlug::Open->value, $ticket->status->slug);
        $this->assertDatabaseHas('ticket_attachments', ['ticket_id' => $ticket->id, 'original_name' => 'screen.pdf']);
        $attachment = $ticket->attachments()->firstOrFail();
        $this->assertNotSame('screen.pdf', $attachment->file_name);
        Storage::disk('local')->assertExists($attachment->file_path);
    }

    public function test_employee_ticket_details_hide_internal_comments_and_other_requesters_tickets(): void
    {
        $this->attInternalA->update(['original_name' => 'internal-only-file.pdf']);
        $this->actingAs($this->employeeA)->get('/tickets/'.$this->ticketA->id)
            ->assertOk()
            ->assertSee($this->publicA->body)
            ->assertDontSee($this->internalA->body)
            ->assertSee('Attachments')
            ->assertSee($this->attA->original_name)
            ->assertDontSee($this->attInternalA->original_name);

        $this->actingAs($this->employeeA)->get('/tickets/'.$this->ticketB->id)->assertForbidden();
    }

    public function test_employee_can_reply_and_cannot_reply_to_another_requesters_ticket(): void
    {
        $this->actingAs($this->employeeA)->withHeader('Accept', 'text/html')
            ->post('/tickets/'.$this->ticketA->id.'/comments', ['body' => 'Frontend reply marker', '_html_form' => '1'])
            ->assertRedirect(route('tickets.show', $this->ticketA));

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $this->ticketA->id,
            'user_id' => $this->employeeA->id,
            'body' => 'Frontend reply marker',
            'is_internal' => false,
        ]);

        $this->actingAs($this->employeeA)->postJson('/tickets/'.$this->ticketB->id.'/comments', ['body' => 'Not mine'])->assertForbidden();
    }

    public function test_employee_can_upload_and_only_download_authorized_attachments(): void
    {
        $this->actingAs($this->employeeA)->withHeader('Accept', 'text/html')
            ->post('/tickets/'.$this->ticketA->id.'/attachments', ['file' => UploadedFile::fake()->create('new-proof.pdf', 100, 'application/pdf'), '_html_form' => '1'])
            ->assertRedirect(route('tickets.show', $this->ticketA));

        $newAttachment = $this->ticketA->attachments()->where('original_name', 'new-proof.pdf')->firstOrFail();
        $this->actingAs($this->employeeA)->get(route('tickets.attachments.show', [$this->ticketA, $this->attA]))->assertOk();
        $this->actingAs($this->employeeA)->get(route('tickets.attachments.show', [$this->ticketB, $this->attB]))->assertForbidden();
        $this->assertDatabaseHas('ticket_attachments', ['id' => $newAttachment->id, 'ticket_id' => $this->ticketA->id]);
    }
}
