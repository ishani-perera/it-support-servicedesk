<?php

namespace Tests\Feature\Models;

use App\Enums\TicketPriorityLevel;
use App\Enums\TicketStatusSlug;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Services\TicketAssignmentService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsTicketFixtures;
use Tests\Concerns\SeedsMasterData;
use Tests\TestCase;

class TicketModelTest extends TestCase
{
    use BuildsTicketFixtures;
    use RefreshDatabase;
    use SeedsMasterData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMasterData();
    }

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);
        parent::tearDown();
    }

    public function test_ticket_has_all_expected_relationships(): void
    {
        $ticket = $this->makeTicket();
        $staff = $this->makeUser(UserRole::Support);
        $this->makeComment($ticket, $staff);
        $this->makeAssignment($ticket, $staff, $this->makeUser(UserRole::Admin));

        $ticket = $ticket->fresh();

        $this->assertNotNull($ticket->user);
        $this->assertNotNull($ticket->department);
        $this->assertNotNull($ticket->category);
        $this->assertNotNull($ticket->priority);
        $this->assertNotNull($ticket->status);
        $this->assertCount(1, $ticket->comments);
        $this->assertCount(1, $ticket->assignments);
        $this->assertTrue($ticket->currentAssignment->assignee->is($staff));
    }

    public function test_requester_department_is_copied_to_the_ticket(): void
    {
        $sales = Department::where('name', 'Sales')->firstOrFail();
        $requester = $this->makeUser(UserRole::Employee, $sales);

        $ticket = Ticket::factory()->create(['user_id' => $requester->id]);

        $this->assertSame($sales->id, $ticket->department_id);
    }

    public function test_priority_relationship_and_level_ordering(): void
    {
        $critical = $this->makeTicket(null, ['priority_id' => TicketPriority::forLevel(TicketPriorityLevel::Critical)->id]);

        $this->assertSame(TicketPriorityLevel::Critical->value, $critical->priority->level);
        $this->assertSame('Critical', $critical->priority->name);
    }

    public function test_lifecycle_timestamps_are_cast_to_carbon(): void
    {
        $ticket = Ticket::factory()->closed()->create()->fresh();

        $this->assertInstanceOf(CarbonInterface::class, $ticket->resolved_at);
        $this->assertInstanceOf(CarbonInterface::class, $ticket->closed_at);
        $this->assertInstanceOf(CarbonInterface::class, $ticket->created_at);
        $this->assertInstanceOf(CarbonInterface::class, $ticket->updated_at);
    }

    public function test_system_managed_fields_are_not_fillable(): void
    {
        $fillable = (new Ticket)->getFillable();

        foreach (['ticket_number', 'resolved_at', 'closed_at'] as $protected) {
            $this->assertNotContains($protected, $fillable);
        }
    }

    public function test_every_status_scope_returns_only_its_status(): void
    {
        $scopes = [
            'open' => TicketStatusSlug::Open,
            'assigned' => TicketStatusSlug::Assigned,
            'inProgress' => TicketStatusSlug::InProgress,
            'waitingForUser' => TicketStatusSlug::WaitingForUser,
            'resolved' => TicketStatusSlug::Resolved,
            'closed' => TicketStatusSlug::Closed,
        ];

        $byStatus = [];
        foreach ($scopes as $slug) {
            $byStatus[$slug->value] = Ticket::factory()->withStatus($slug)->create()->id;
        }

        foreach ($scopes as $scope => $slug) {
            $this->assertSame(
                [$byStatus[$slug->value]],
                Ticket::query()->{$scope}()->pluck('id')->all(),
                "Scope {$scope}() returned the wrong tickets"
            );
        }
    }

    public function test_with_status_accepts_several_statuses(): void
    {
        $open = Ticket::factory()->create();
        $resolved = Ticket::factory()->withStatus(TicketStatusSlug::Resolved)->create();
        Ticket::factory()->withStatus(TicketStatusSlug::Closed)->create();

        $this->assertEqualsCanonicalizing(
            [$open->id, $resolved->id],
            Ticket::withStatus(TicketStatusSlug::Open, TicketStatusSlug::Resolved)->pluck('id')->all()
        );
    }

    public function test_has_status_helper(): void
    {
        $ticket = Ticket::factory()->withStatus(TicketStatusSlug::InProgress)->create();

        $this->assertTrue($ticket->hasStatus(TicketStatusSlug::InProgress));
        $this->assertFalse($ticket->hasStatus(TicketStatusSlug::Open));
    }

    public function test_unassigned_and_assigned_to_scopes_use_the_current_assignment_only(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $alice = $this->makeUser(UserRole::Support);
        $bob = $this->makeUser(UserRole::Support);

        $nobody = $this->makeTicket();
        $withAlice = $this->makeTicket();
        $movedToBob = $this->makeTicket();

        $service = app(TicketAssignmentService::class);
        $service->assign($withAlice, $alice, $admin);
        $service->assign($movedToBob, $alice, $admin, null, now()->subHour());
        $service->assign($movedToBob, $bob, $admin);

        $this->assertSame([$nobody->id], Ticket::unassigned()->pluck('id')->all());
        $this->assertSame([$withAlice->id], Ticket::assignedTo($alice)->pluck('id')->all(), 'Alice\'s CLOSED assignment must not count');
        $this->assertSame([$movedToBob->id], Ticket::assignedTo($bob->id)->pluck('id')->all());

        // A ticket whose assignment was closed returns to the unassigned queue.
        $service->unassign($withAlice);
        $this->assertEqualsCanonicalizing([$nobody->id, $withAlice->id], Ticket::unassigned()->pluck('id')->all());
    }

    public function test_created_by_scope(): void
    {
        $alice = $this->makeUser();
        $bob = $this->makeUser();
        $aliceTicket = $this->makeTicket($alice);
        $this->makeTicket($bob);

        $this->assertSame([$aliceTicket->id], Ticket::createdBy($alice)->pluck('id')->all());
        $this->assertSame([$aliceTicket->id], Ticket::createdBy($alice->id)->pluck('id')->all());
    }

    public function test_current_assignment_is_the_open_one_and_null_when_unassigned(): void
    {
        $ticket = $this->makeTicket();
        $admin = $this->makeUser(UserRole::Admin);
        $first = $this->makeUser(UserRole::Support);
        $second = $this->makeUser(UserRole::Support);

        $this->assertNull($ticket->currentAssignment);

        $this->makeAssignment($ticket, $first, $admin, ['assigned_at' => now()->subHours(3), 'unassigned_at' => now()->subHours(2)]);
        $this->makeAssignment($ticket, $second, $admin);

        $this->assertTrue($ticket->fresh()->currentAssignment->assignee->is($second));
        $this->assertCount(2, $ticket->assignments);
    }

    public function test_list_relations_load_in_a_constant_number_of_queries_without_lazy_loading(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $staff = $this->makeUser(UserRole::Support);
        foreach (range(1, 12) as $i) {
            $ticket = $this->makeTicket();
            if ($i % 2 === 0) {
                $this->makeAssignment($ticket, $staff, $admin);
            }
        }

        Model::preventLazyLoading(true); // any N+1 access below would throw

        $queries = function (int $limit): int {
            DB::flushQueryLog();
            DB::enableQueryLog();

            $tickets = Ticket::withListRelations()->orderBy('id')->limit($limit)->get();
            foreach ($tickets as $t) {
                [$t->user->name, $t->department->name, $t->category->name, $t->priority->name, $t->status->name];
                $t->currentAssignment?->assignee->name;
            }

            return count(DB::getQueryLog());
        };

        $small = $queries(3);
        $large = $queries(12);

        $this->assertSame($small, $large, 'Query count must not grow with the number of tickets');
        $this->assertLessThanOrEqual(8, $large);
    }

    public function test_with_count_avoids_loading_comments_for_a_list(): void
    {
        $ticket = $this->makeTicket();
        $this->makeComment($ticket, $this->makeUser());
        $this->makeComment($ticket, $this->makeUser(UserRole::Support), internal: true);

        $row = Ticket::withCount('comments')->findOrFail($ticket->id);

        $this->assertSame(2, $row->comments_count);
    }
}
