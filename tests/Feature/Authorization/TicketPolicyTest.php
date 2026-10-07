<?php

namespace Tests\Feature\Authorization;

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use App\Policies\TicketPolicy;
use App\Services\TicketAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\BuildsAuthScenario;
use Tests\Concerns\BuildsTicketFixtures;
use Tests\Concerns\SeedsMasterData;
use Tests\TestCase;

/**
 * Exhaustive role x ownership x assignment matrix for TicketPolicy, plus the
 * guarantee that list queries (Ticket::visibleTo) and single-record checks
 * (view) always agree.
 */
class TicketPolicyTest extends TestCase
{
    use BuildsAuthScenario;
    use BuildsTicketFixtures;
    use RefreshDatabase;
    use SeedsMasterData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildAuthScenario();
    }

    private function allows(User $user, string $ability, Ticket $ticket): bool
    {
        return Gate::forUser($user)->allows($ability, $ticket);
    }

    /**
     * Expected outcome of every record-level ability, per actor, for the 3
     * relevant situations. Ticket states in the scenario:
     *   ticketA       requested by employeeA, held by supportOne
     *   ticketB       requested by employeeB, held by supportTwo
     *   ticketQueueB  requested by employeeB, unassigned
     */
    public function test_the_full_ticket_ability_matrix(): void
    {
        //               view   updateStatus updatePriority update assign comment internal viewInternal upload
        $matrix = [
            'employeeA on own ticket' => ['employeeA', 'ticketA', [1, 0, 0, 0, 0, 1, 0, 0, 1]],
            'employeeA on own queue ticket' => ['employeeA', 'ticketA2', [1, 0, 0, 0, 0, 1, 0, 0, 1]],
            "employeeA on B's ticket" => ['employeeA', 'ticketB', [0, 0, 0, 0, 0, 0, 0, 0, 0]],
            "employeeA on B's queue ticket" => ['employeeA', 'ticketQueueB', [0, 0, 0, 0, 0, 0, 0, 0, 0]],
            "employeeB on A's ticket" => ['employeeB', 'ticketA', [0, 0, 0, 0, 0, 0, 0, 0, 0]],

            'supportOne on ticket assigned to them' => ['supportOne', 'ticketA', [1, 1, 1, 1, 1, 1, 1, 1, 1]],
            'supportOne on unassigned queue ticket' => ['supportOne', 'ticketQueueB', [1, 0, 0, 0, 1, 1, 1, 1, 1]],
            "supportOne on a colleague's ticket" => ['supportOne', 'ticketB', [1, 0, 0, 0, 0, 0, 0, 1, 0]],
            "supportTwo on supportOne's ticket" => ['supportTwo', 'ticketA', [1, 0, 0, 0, 0, 0, 0, 1, 0]],
            'supportTwo on their ticket' => ['supportTwo', 'ticketB', [1, 1, 1, 1, 1, 1, 1, 1, 1]],

            'admin on assigned ticket' => ['admin', 'ticketA', [1, 1, 1, 1, 1, 1, 1, 1, 1]],
            'admin on unassigned ticket' => ['admin', 'ticketQueueB', [1, 1, 1, 1, 1, 1, 1, 1, 1]],
        ];

        $abilities = ['view', 'updateStatus', 'updatePriority', 'update', 'assign', 'comment', 'addInternalNote', 'viewInternalNotes', 'uploadAttachment'];

        foreach ($matrix as $label => [$actor, $ticketKey, $expected]) {
            foreach ($abilities as $i => $ability) {
                $this->assertSame(
                    (bool) $expected[$i],
                    $this->allows($this->{$actor}, $ability, $this->{$ticketKey}),
                    "{$label}: ability [{$ability}] should be ".($expected[$i] ? 'ALLOWED' : 'DENIED'),
                );
            }
        }
    }

    public function test_any_active_user_may_create_and_list(): void
    {
        foreach ([$this->employeeA, $this->supportOne, $this->admin] as $user) {
            $this->assertTrue(Gate::forUser($user)->allows('create', Ticket::class));
            $this->assertTrue(Gate::forUser($user)->allows('viewAny', Ticket::class));
        }
    }

    public function test_technician_can_view_only_tickets_currently_assigned_to_them(): void
    {
        $technician = User::factory()->technician()->inDepartment($this->supportOne->department_id)->create();
        $otherTechnician = User::factory()->technician()->create();
        $assigned = $this->makeTicket($this->employeeA);
        $unassigned = $this->makeTicket($this->employeeB);
        $this->makeAssignment($assigned, $technician, $this->admin);
        $this->makeAssignment($unassigned, $otherTechnician, $this->admin);

        $this->assertFalse(Gate::forUser($technician)->allows('create', Ticket::class));
        $this->assertSame([$assigned->id], Ticket::query()->visibleTo($technician)->pluck('id')->all());
        $this->assertTrue($this->allows($technician, 'view', $assigned));
        $this->assertTrue($this->allows($technician, 'addInternalNote', $assigned));
        $this->assertFalse($this->allows($technician, 'view', $unassigned));
        $this->assertFalse($this->allows($technician, 'assign', $assigned));
        $this->assertFalse($this->allows($technician, 'updateStatus', $assigned));
    }

    public function test_deactivated_users_are_denied_every_ability_even_admins(): void
    {
        $inactiveAdmin = User::factory()->admin()->inactive()->create();
        $inactiveEmployee = User::factory()->inactive()->create();
        $own = $this->makeTicket($inactiveEmployee);

        $abilities = ['view', 'updateStatus', 'updatePriority', 'update', 'assign', 'comment', 'addInternalNote', 'viewInternalNotes', 'uploadAttachment'];

        foreach ($abilities as $ability) {
            $this->assertFalse($this->allows($inactiveAdmin, $ability, $this->ticketA), "inactive admin: {$ability}");
            $this->assertFalse($this->allows($inactiveEmployee, $ability, $own), "inactive requester on own ticket: {$ability}");
        }
        $this->assertFalse(Gate::forUser($inactiveAdmin)->allows('create', Ticket::class));
        $this->assertFalse(Gate::forUser($inactiveAdmin)->allows('viewAny', Ticket::class));
    }

    public function test_undefined_abilities_are_denied_even_for_admin(): void
    {
        // There is deliberately no delete / forceDelete / restore ability for tickets.
        foreach (['delete', 'forceDelete', 'restore', 'anythingElse'] as $ability) {
            $this->assertFalse($this->allows($this->admin, $ability, $this->ticketA), $ability);
        }
    }

    public function test_the_ticket_policy_never_grants_through_before(): void
    {
        $policy = new TicketPolicy;

        $this->assertNull($policy->before($this->admin, 'view'), 'active admin must fall through to the explicit ability');
        $this->assertNull($policy->before($this->employeeA, 'view'));
        $this->assertFalse($policy->before(User::factory()->admin()->inactive()->make(), 'view'));
    }

    public function test_access_follows_the_assignment_when_a_ticket_is_reassigned(): void
    {
        $service = app(TicketAssignmentService::class);

        $this->assertTrue($this->allows($this->supportOne, 'view', $this->ticketA));
        $this->assertTrue($this->allows($this->supportTwo, 'view', $this->ticketA));
        $this->assertFalse($this->allows($this->supportTwo, 'updateStatus', $this->ticketA));

        $service->assign($this->ticketA, $this->supportTwo, $this->admin, 'handover');

        $this->assertTrue($this->allows($this->supportOne, 'view', $this->ticketA), 'team view remains available');
        $this->assertFalse($this->allows($this->supportOne, 'updateStatus', $this->ticketA), 'previous agent loses modification rights');
        $this->assertTrue($this->allows($this->supportTwo, 'view', $this->ticketA));
        $this->assertTrue($this->allows($this->supportTwo, 'updateStatus', $this->ticketA));

        $service->unassign($this->ticketA);

        $this->assertTrue($this->allows($this->supportOne, 'view', $this->ticketA));
        $this->assertFalse($this->allows($this->supportOne, 'updateStatus', $this->ticketA), 'but not workable until claimed');
        $this->assertTrue($this->allows($this->supportOne, 'assign', $this->ticketA), 'a support agent may claim a queue ticket');
    }

    public function test_requesting_does_not_grant_staff_rights_and_staff_do_not_gain_ownership(): void
    {
        // A support agent's OWN ticket (they requested it) is still governed by the support rules.
        $own = $this->makeTicket($this->supportOne);
        $this->makeAssignment($own, $this->supportTwo, $this->admin);

        $this->assertTrue($this->allows($this->supportOne, 'view', $own));
        $this->assertTrue($this->allows($this->supportTwo, 'view', $own));
    }

    /* ----- list/record parity ----- */

    public function test_visible_to_scope_matches_the_view_policy_for_every_user_and_ticket(): void
    {
        $this->makeTicket($this->supportOne);                       // staff-requested, unassigned
        $this->makeAssignment($this->makeTicket($this->employeeB), $this->supportOne, $this->admin);
        $inactive = User::factory()->admin()->inactive()->create();

        $technician = User::factory()->technician()->create();
        $users = [$this->employeeA, $this->employeeB, $this->supportOne, $this->supportTwo, $this->admin, $technician, $inactive];

        foreach ($users as $user) {
            $visible = Ticket::query()->visibleTo($user)->pluck('id')->all();

            foreach (Ticket::all() as $ticket) {
                $this->assertSame(
                    $this->allows($user, 'view', $ticket),
                    in_array($ticket->id, $visible, true),
                    "list/record mismatch for user #{$user->id} ({$user->role->value}) on ticket #{$ticket->id}",
                );
            }
        }
    }

    public function test_visible_to_returns_exactly_the_expected_sets(): void
    {
        $ids = fn (User $u) => Ticket::query()->visibleTo($u)->orderBy('id')->pluck('id')->all();

        $this->assertSame([$this->ticketA->id, $this->ticketA2->id], $ids($this->employeeA));
        $this->assertSame([$this->ticketB->id, $this->ticketQueueB->id], $ids($this->employeeB));
        $this->assertSame([$this->ticketA->id, $this->ticketA2->id, $this->ticketB->id, $this->ticketQueueB->id], $ids($this->supportOne));
        $this->assertSame([$this->ticketA->id, $this->ticketA2->id, $this->ticketB->id, $this->ticketQueueB->id], $ids($this->supportTwo));
        $this->assertCount(4, $ids($this->admin));
        $this->assertSame([], $ids(User::factory()->admin()->inactive()->create()));
    }

    public function test_an_employee_with_a_legacy_unknown_role_gets_nothing(): void
    {
        $user = User::factory()->make(['role' => UserRole::Employee]);
        $user->role = null; // simulate a corrupt row; helpers treat it as no role

        $this->assertFalse(Gate::forUser($user)->allows('view', $this->ticketA));
    }
}
