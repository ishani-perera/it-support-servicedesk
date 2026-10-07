<?php

namespace Tests\Feature;

use App\Enums\TicketStatusSlug;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketWorkReport;
use App\Models\User;
use App\Services\TechnicianWorkflowService;
use App\Services\TicketAssignmentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTicketFixtures;
use Tests\Concerns\SeedsMasterData;
use Tests\TestCase;

class TechnicianFrontendTest extends TestCase
{
    use BuildsTicketFixtures;
    use RefreshDatabase;
    use SeedsMasterData;

    private User $employee;

    private User $support;

    private User $technician;

    private User $otherTechnician;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMasterData();
        $itDepartment = Department::where('name', 'IT')->firstOrFail();
        $this->employee = User::factory()->inDepartment(Department::where('name', 'Finance')->firstOrFail())->create();
        $this->support = User::factory()->support()->inDepartment($itDepartment)->create();
        $this->technician = User::factory()->technician()->inDepartment($itDepartment)->create();
        $this->otherTechnician = User::factory()->technician()->inDepartment($itDepartment)->create();
        $this->ticket = $this->makeTicket($this->employee, ['title' => 'Printer hardware failure']);
    }

    public function test_dashboard_and_ticket_list_only_show_currently_assigned_work_and_role_navigation(): void
    {
        $this->assign($this->ticket, $this->technician);
        $otherTicket = $this->makeTicket($this->employee, ['title' => 'Other technician private request']);
        $this->assign($otherTicket, $this->otherTechnician);

        $this->actingAs($this->technician)->get('/home')->assertRedirect(route('technician.dashboard'));
        $this->actingAs($this->technician)->get('/technician/dashboard')->assertOk()
            ->assertSee('Your work, all in one place.')
            ->assertSee($this->ticket->ticket_number)
            ->assertDontSee($otherTicket->ticket_number)
            ->assertSee('Technician dashboard')
            ->assertSee('Assigned tickets')
            ->assertDontSee('Support dashboard')
            ->assertDontSee('Administration');
        $this->actingAs($this->technician)->get('/technician/tickets')->assertOk()
            ->assertSee($this->ticket->ticket_number)->assertDontSee($otherTicket->ticket_number);
        $this->actingAs($this->technician)->get('/tickets')->assertOk()
            ->assertSee($this->ticket->ticket_number)->assertDontSee($otherTicket->ticket_number);
        $this->actingAs($this->otherTechnician)->get('/tickets/'.$this->ticket->id)->assertForbidden();
        $this->actingAs($this->employee)->get('/technician/dashboard')->assertForbidden();
    }

    public function test_technician_ticket_detail_has_work_controls_and_uses_existing_start_and_information_endpoints(): void
    {
        $assignment = $this->assign($this->ticket, $this->technician);
        $this->makeComment($this->ticket, $this->employee, false);
        $internalNote = $this->makeComment($this->ticket, $this->support, true);
        $internalNote->update(['body' => 'Private technician handoff note']);

        $this->actingAs($this->technician)->get('/tickets/'.$this->ticket->id)->assertOk()
            ->assertSee('Start Work')
            ->assertSee('Technician Work Report')
            ->assertSee('Employee / requester')
            ->assertSee('Assignment information')
            ->assertSee('Public reply')
            ->assertSee('Internal note')
            ->assertSee('Private technician handoff note')
            ->assertDontSee('Approve and close ticket')
            ->assertDontSee('Send back to Technician')
            ->assertSee(route('tickets.work.start', $this->ticket), false);

        $this->actingAs($this->technician)->postJson('/tickets/'.$this->ticket->id.'/work/start')->assertCreated();
        $this->assertSame(TicketStatusSlug::InProgress->value, $this->ticket->fresh()->status->slug);
        $report = TicketWorkReport::query()->where('ticket_assignment_id', $assignment->id)->firstOrFail();
        $this->actingAs($this->technician)->get('/tickets/'.$this->ticket->id)->assertOk()
            ->assertSee('Work In Progress')->assertSee('Request Information from Employee')
            ->assertSee('Complete Technical Work')->assertSee($report->work_started_at->format('M j, Y g:i A'));

        $this->actingAs($this->technician)->postJson('/tickets/'.$this->ticket->id.'/work/request-information', [
            'body' => 'Please share the printer error code shown on the display.',
        ])->assertCreated();
        $this->assertSame(TicketStatusSlug::WaitingForUser->value, $this->ticket->fresh()->status->slug);
        $this->actingAs($this->technician)->get('/tickets/'.$this->ticket->id)->assertOk()
            ->assertSee('Waiting for Employee Information')
            ->assertSee('Please share the printer error code shown on the display.')
            ->assertDontSee('Complete Technical Work');

        $this->actingAs($this->employee)->postJson('/tickets/'.$this->ticket->id.'/comments', [
            'body' => 'The display shows error code E-14.',
        ])->assertCreated();
        $this->assertSame(TicketStatusSlug::InProgress->value, $this->ticket->fresh()->status->slug);
        $this->actingAs($this->technician)->get('/tickets/'.$this->ticket->id)->assertOk()
            ->assertSee('The display shows error code E-14.')
            ->assertSee('Complete Technical Work');

        $this->actingAs($this->employee)->get('/tickets/'.$this->ticket->id)->assertOk()
            ->assertSee('Please share the printer error code shown on the display.')
            ->assertDontSee('Private technician handoff note');
    }

    public function test_complete_work_is_read_only_for_technician_during_it_support_review_and_closed_state(): void
    {
        $this->assign($this->ticket, $this->technician);
        $workflow = app(TechnicianWorkflowService::class);
        $workflow->startWork($this->ticket, $this->technician);
        $workflow->completeWork($this->ticket, $this->technician, [
            'work_summary' => 'Replaced the faulty printer cable.',
            'root_cause' => 'The cable connector was damaged.',
            'technician_notes' => 'Print test passed.',
        ]);

        $this->actingAs($this->technician)->get('/tickets/'.$this->ticket->id)->assertOk()
            ->assertSee('Waiting for IT Support Review')
            ->assertSee('Replaced the faulty printer cable.')
            ->assertSee('The cable connector was damaged.')
            ->assertSee('Print test passed.')
            ->assertDontSee('Complete Technical Work')
            ->assertDontSee('Approve and close ticket')
            ->assertDontSee('Send back to Technician');
        $this->actingAs($this->technician)->postJson('/tickets/'.$this->ticket->id.'/work/approve')->assertForbidden();
        $this->actingAs($this->technician)->postJson('/tickets/'.$this->ticket->id.'/work/send-back', ['review_note' => 'revise'])->assertForbidden();
        $this->actingAs($this->technician)->patchJson('/tickets/'.$this->ticket->id.'/status', ['status' => TicketStatusSlug::Closed->value])->assertForbidden();

        $this->actingAs($this->support)->postJson('/tickets/'.$this->ticket->id.'/work/approve')->assertOk();
        $this->actingAs($this->technician)->get('/tickets/'.$this->ticket->id)->assertOk()
            ->assertSee('Ticket closed')->assertDontSee('Start Work')->assertDontSee('Complete Technical Work');
    }

    public function test_sent_back_work_shows_review_reason_and_allows_a_new_work_report(): void
    {
        $this->assign($this->ticket, $this->technician);
        $workflow = app(TechnicianWorkflowService::class);
        $workflow->startWork($this->ticket, $this->technician);
        $workflow->completeWork($this->ticket, $this->technician, [
            'work_summary' => 'Replaced printer cable.',
            'root_cause' => 'Cable failure.',
        ]);
        $workflow->sendBackWork($this->ticket, $this->support, 'Please test with a second workstation before resubmitting.');

        $this->actingAs($this->technician)->get('/tickets/'.$this->ticket->id)->assertOk()
            ->assertSee('Work Sent Back by IT Support')
            ->assertSee('Please test with a second workstation before resubmitting.')
            ->assertSee('Continue Work')
            ->assertDontSee('Approve and close ticket');
        $this->actingAs($this->technician)->get('/technician/tickets?status=sent_back')->assertOk()
            ->assertSee($this->ticket->ticket_number);
        $this->actingAs($this->technician)->postJson('/tickets/'.$this->ticket->id.'/work/start')->assertCreated();
        $this->assertSame(2, $this->ticket->workReports()->count());
        $this->assertSame(TicketStatusSlug::InProgress->value, $this->ticket->fresh()->status->slug);
    }

    private function assign(Ticket $ticket, User $technician): TicketAssignment
    {
        return app(TicketAssignmentManager::class)->assign($ticket, $technician, $this->support);
    }
}
