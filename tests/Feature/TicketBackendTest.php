<?php

namespace Tests\Feature;

use App\Enums\TicketStatusSlug;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use App\Services\TicketAssignmentManager;
use App\Services\TicketWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsAuthScenario;
use Tests\Concerns\BuildsTicketFixtures;
use Tests\Concerns\SeedsMasterData;
use Tests\TestCase;

class TicketBackendTest extends TestCase
{
    use BuildsAuthScenario, BuildsTicketFixtures, RefreshDatabase, SeedsMasterData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildAuthScenario();
        Storage::fake('local');
    }

    public function test_employee_creates_ticket_with_server_owned_requester_number_and_open_status(): void
    {
        $payload = ['title' => 'VPN trouble', 'description' => 'Cannot connect',
            'category_id' => TicketCategory::first()->id, 'priority_id' => TicketPriority::first()->id,
            'user_id' => $this->employeeB->id, 'ticket_number' => 'TKT-2000-999999', 'status_id' => TicketStatus::forSlug(TicketStatusSlug::Closed)->id];

        $this->actingAs($this->employeeA)->postJson('/tickets', $payload)->assertCreated();
        $ticket = Ticket::latest('id')->firstOrFail();
        $this->assertSame($this->employeeA->id, $ticket->user_id);
        $this->assertSame('TKT-'.now()->format('Y').'-000001', $ticket->ticket_number);
        $this->assertSame(TicketStatusSlug::Open->value, $ticket->status->slug);
    }

    public function test_ticket_numbers_increment_and_are_unique(): void
    {
        $this->actingAs($this->employeeA);
        $payload = ['title' => 'A', 'description' => 'B', 'category_id' => TicketCategory::first()->id, 'priority_id' => TicketPriority::first()->id];
        $this->postJson('/tickets', $payload)->assertCreated();
        $this->postJson('/tickets', $payload)->assertCreated();
        $this->assertSame(2, Ticket::where('ticket_number', 'like', 'TKT-'.now()->format('Y').'-%')->count());
    }

    public function test_employee_cannot_update_or_view_another_requesters_ticket(): void
    {
        $this->actingAs($this->employeeA)->patchJson('/tickets/'.$this->ticketB->id, ['title' => 'hijack'])->assertForbidden();
        $this->actingAs($this->employeeA)->getJson('/tickets')->assertOk()->assertJsonMissing(['id' => $this->ticketB->id]);
    }

    public function test_ticket_filters_and_pagination_only_return_authorized_records(): void
    {
        $ticket = $this->makeTicket($this->employeeA, ['title' => 'unique-filter-needle']);
        $this->actingAs($this->employeeA)->getJson('/tickets?search=unique-filter-needle&per_page=1')
            ->assertOk()->assertJsonPath('per_page', 1)->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ticket->id);
    }

    public function test_invalid_workflow_transition_is_rejected(): void
    {
        $ticket = $this->makeTicket($this->employeeA);
        $this->actingAs($this->admin)->patchJson('/tickets/'.$ticket->id.'/status', ['status' => 'closed'])
            ->assertUnprocessable();
        $this->assertSame(TicketStatusSlug::Open->value, $ticket->fresh()->status->slug);
    }

    public function test_only_authorized_staff_can_move_workflow_and_timestamps_follow_state(): void
    {
        $ticket = $this->makeTicket($this->employeeA);
        $app = app(TicketAssignmentManager::class);
        $app->assign($ticket, $this->supportOne, $this->admin);
        $this->actingAs($this->supportOne)->patchJson('/tickets/'.$ticket->id.'/status', ['status' => 'in_progress'])->assertOk();
        $workflow = app(TicketWorkflowService::class);
        $workflow->transition($ticket, TicketStatusSlug::WaitingForUser, $this->supportOne);
        $workflow->transition($ticket, TicketStatusSlug::Resolved, $this->supportOne, 'Reconfigured the affected service and confirmed access.');
        $this->assertNotNull($ticket->fresh()->resolved_at);
        $workflow->transition($ticket, TicketStatusSlug::Closed, $this->supportOne);
        $this->assertNotNull($ticket->fresh()->closed_at);
        $workflow->transition($ticket, TicketStatusSlug::Resolved, $this->supportOne, 'Confirmed the reported issue is fixed.');
        $this->assertNull($ticket->fresh()->closed_at);
    }

    public function test_assignment_records_history_and_employees_cannot_assign(): void
    {
        $this->actingAs($this->employeeA)->postJson('/tickets/'.$this->ticketA->id.'/assignments', ['assigned_to' => $this->supportTwo->id])->assertForbidden();
        $manager = app(TicketAssignmentManager::class);
        $manager->assign($this->ticketA, $this->supportTwo, $this->admin);
        $this->assertSame(2, $this->ticketA->assignments()->count());
        $this->assertSame(1, $this->ticketA->assignments()->current()->count());
    }

    public function test_admin_can_assign_ticket_to_a_technician_through_existing_assignment_route(): void
    {
        $technician = User::factory()->technician()->inDepartment($this->supportOne->department_id)->create();

        $this->actingAs($this->admin)->postJson('/tickets/'.$this->ticketA->id.'/assignments', [
            'assigned_to' => $technician->id,
        ])->assertCreated()->assertJsonPath('data.assigned_to', $technician->id);

        $this->assertSame($technician->id, $this->ticketA->fresh()->currentAssignment->assigned_to);
    }

    public function test_internal_comments_are_hidden_from_employee_ticket_details(): void
    {
        $internal = $this->makeComment($this->ticketA, $this->supportOne, true);
        $internal->update(['body' => 'internal-secret-marker']);
        $public = $this->makeComment($this->ticketA, $this->supportOne, false);
        $public->update(['body' => 'public-comment-marker']);
        $this->actingAs($this->employeeA)->getJson('/tickets/'.$this->ticketA->id)->assertOk()
            ->assertJsonFragment(['body' => 'public-comment-marker'])->assertJsonMissing(['body' => 'internal-secret-marker'])
            ->assertJsonMissing(['is_internal' => true])
            ->assertJsonPath('data.current_assignment', null)->assertJsonCount(0, 'data.assignment_history');
        $this->actingAs($this->employeeA)->postJson('/tickets/'.$this->ticketA->id.'/comments', ['body' => 'Hello', 'is_internal' => true])->assertForbidden();
    }

    public function test_attachment_upload_rejects_executables_and_stores_safe_names(): void
    {
        $this->actingAs($this->employeeA)->postJson('/tickets/'.$this->ticketB->id.'/attachments', [])->assertForbidden();
        $this->actingAs($this->employeeA)->post('/tickets/'.$this->ticketA->id.'/attachments', ['file' => UploadedFile::fake()->create('bad.exe', 10, 'application/x-msdownload')])->assertSessionHasErrors('file');
        $this->actingAs($this->employeeA)->post('/tickets/'.$this->ticketA->id.'/attachments', ['file' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf')])->assertSessionHasErrors('file');
        $this->actingAs($this->employeeA)->post('/tickets/'.$this->ticketA->id.'/attachments', ['file' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf')])->assertCreated();
        $this->assertDatabaseMissing('ticket_attachments', ['file_name' => 'proof.pdf']);
        $this->assertDatabaseHas('ticket_attachments', ['ticket_id' => $this->ticketA->id]);
    }
}
