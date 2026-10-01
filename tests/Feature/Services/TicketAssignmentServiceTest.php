<?php

namespace Tests\Feature\Services;

use App\Enums\UserRole;
use App\Exceptions\InvalidAssignmentException;
use App\Models\TicketAssignment;
use App\Models\User;
use App\Services\TicketAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTicketFixtures;
use Tests\Concerns\SeedsMasterData;
use Tests\TestCase;

class TicketAssignmentServiceTest extends TestCase
{
    use BuildsTicketFixtures;
    use RefreshDatabase;
    use SeedsMasterData;

    private TicketAssignmentService $service;

    private User $admin;

    private User $agentA;

    private User $agentB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMasterData();

        $this->service = app(TicketAssignmentService::class);
        $this->admin = $this->makeUser(UserRole::Admin);
        $this->agentA = $this->makeUser(UserRole::Support);
        $this->agentB = $this->makeUser(UserRole::Support);
    }

    public function test_assigning_creates_an_open_assignment(): void
    {
        $ticket = $this->makeTicket();

        $assignment = $this->service->assign($ticket, $this->agentA, $this->admin, 'Please take this.');

        $this->assertTrue($assignment->isCurrent());
        $this->assertSame($this->agentA->id, $assignment->assigned_to);
        $this->assertSame($this->admin->id, $assignment->assigned_by);
        $this->assertSame('Please take this.', $assignment->note);
        $this->assertTrue($ticket->fresh()->currentAssignment->is($assignment));
    }

    public function test_reassigning_closes_the_previous_row_and_keeps_history(): void
    {
        $ticket = $this->makeTicket();

        $first = $this->service->assign($ticket, $this->agentA, $this->admin, null, now()->subHours(4));
        $second = $this->service->assign($ticket, $this->agentB, $this->admin, 'Shift change', now()->subHour());

        $this->assertSame(2, $ticket->assignments()->count(), 'History must be preserved');
        $this->assertSame(1, $ticket->assignments()->current()->count(), 'Exactly one open assignment');

        $first = $first->fresh();
        $this->assertFalse($first->isCurrent());
        $this->assertEquals($second->assigned_at, $first->unassigned_at, 'Old row ends exactly when the new one starts');
        $this->assertTrue($ticket->fresh()->currentAssignment->is($second));
        $this->assertSame([$first->id], $ticket->assignments()->historical()->pluck('id')->all());
    }

    public function test_long_reassignment_chains_never_overwrite_history(): void
    {
        $ticket = $this->makeTicket();
        $agents = [$this->agentA, $this->agentB, $this->agentA, $this->agentB];

        foreach ($agents as $i => $agent) {
            $this->service->assign($ticket, $agent, $this->admin, null, now()->subHours(10 - $i));
        }

        $this->assertSame(4, $ticket->assignments()->count());
        $this->assertSame(3, $ticket->assignments()->historical()->count());
        $this->assertSame($this->agentB->id, $ticket->fresh()->currentAssignment->assigned_to);
    }

    public function test_assigning_the_current_assignee_again_is_a_no_op(): void
    {
        $ticket = $this->makeTicket();
        $first = $this->service->assign($ticket, $this->agentA, $this->admin);

        $again = $this->service->assign($ticket, $this->agentA, $this->admin);

        $this->assertTrue($again->is($first));
        $this->assertSame(1, $ticket->assignments()->count());
    }

    public function test_unassign_closes_the_open_assignment(): void
    {
        $ticket = $this->makeTicket();
        $assignment = $this->service->assign($ticket, $this->agentA, $this->admin, null, now()->subHour());

        $closed = $this->service->unassign($ticket);

        $this->assertTrue($closed->is($assignment));
        $this->assertFalse($assignment->fresh()->isCurrent());
        $this->assertNull($ticket->fresh()->currentAssignment);
        $this->assertSame(1, $ticket->assignments()->count(), 'Row is kept as history');
        $this->assertNull($this->service->unassign($ticket), 'Nothing left to close');
    }

    public function test_assignee_must_be_active_it_staff(): void
    {
        $ticket = $this->makeTicket();
        $employee = $this->makeUser(UserRole::Employee);
        $inactive = User::factory()->support()->inactive()->create();

        foreach ([$employee, $inactive] as $invalid) {
            try {
                $this->service->assign($ticket, $invalid, $this->admin);
                $this->fail('An invalid assignee was accepted');
            } catch (InvalidAssignmentException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame(0, TicketAssignment::count());
    }

    public function test_assigner_must_be_active_it_staff(): void
    {
        $this->expectException(InvalidAssignmentException::class);

        $this->service->assign($this->makeTicket(), $this->agentA, $this->makeUser(UserRole::Employee));
    }

    public function test_a_failed_reassignment_leaves_the_existing_assignment_untouched(): void
    {
        $ticket = $this->makeTicket();
        $first = $this->service->assign($ticket, $this->agentA, $this->admin, null, now()->subHours(2));

        try {
            // Ending before the previous assignment started is rejected inside the transaction.
            $this->service->assign($ticket, $this->agentB, $this->admin, null, now()->subHours(5));
            $this->fail('Time-travelling reassignment was accepted');
        } catch (InvalidAssignmentException) {
            $this->assertTrue(true);
        }

        $this->assertTrue($first->fresh()->isCurrent());
        $this->assertSame(1, $ticket->assignments()->count());
    }

    public function test_the_service_does_not_change_ticket_status(): void
    {
        $ticket = $this->makeTicket();
        $statusBefore = $ticket->status_id;

        $this->service->assign($ticket, $this->agentA, $this->admin);

        $this->assertSame($statusBefore, $ticket->fresh()->status_id, 'Workflow transitions belong to a later phase');
    }

    public function test_assignment_model_current_helpers(): void
    {
        $ticket = $this->makeTicket();
        $open = $this->makeAssignment($ticket, $this->agentA, $this->admin);
        $ended = $this->makeAssignment($this->makeTicket(), $this->agentA, $this->admin, ['unassigned_at' => now()->addMinute()]);

        $this->assertTrue($open->isCurrent());
        $this->assertFalse($ended->isCurrent());
        $this->assertSame([$open->id], TicketAssignment::current()->pluck('id')->all());
        $this->assertTrue($open->assignee->is($this->agentA));
        $this->assertTrue($open->assigner->is($this->admin));
        $this->assertTrue($open->ticket->is($ticket));
        $this->assertTrue($open->fresh()->is_current, 'DB-generated is_current is true while open');
        $this->assertNull($ended->fresh()->is_current, 'DB-generated is_current is NULL once closed');
    }
}
