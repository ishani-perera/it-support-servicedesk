<?php

namespace Tests\Feature\Seeders;

use App\Enums\TicketPriorityLevel;
use App\Enums\TicketStatusSlug;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketAttachment;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class DemoDataSeedingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /* ------------------------------ master data ------------------------------ */

    public function test_master_data_is_present(): void
    {
        $this->assertSame(7, Department::count());
        $this->assertSame(9, TicketCategory::count());
        $this->assertSame(4, TicketPriority::count());
        $this->assertSame(6, TicketStatus::count());
    }

    /* --------------------------------- users --------------------------------- */

    public function test_expected_demo_users_and_roles_exist(): void
    {
        $this->assertSame(12, User::count());
        $this->assertSame(1, User::admins()->count());
        $this->assertSame(3, User::supportAgents()->count());
        $this->assertSame(8, User::employees()->count());

        $this->assertSame(UserRole::Admin, User::where('email', 'admin@example.com')->firstOrFail()->role);

        foreach (range(1, 3) as $i) {
            $this->assertSame(UserRole::Support, User::where('email', "support{$i}@example.com")->firstOrFail()->role);
        }
        foreach (range(1, 8) as $i) {
            $this->assertSame(UserRole::Employee, User::where('email', "employee{$i}@example.com")->firstOrFail()->role);
        }
    }

    public function test_all_demo_users_are_active_verified_and_have_a_department(): void
    {
        $this->assertSame(12, User::active()->count());
        $this->assertSame(0, User::whereNull('department_id')->count());
        $this->assertSame(0, User::whereNull('email_verified_at')->count());
        $this->assertSame(12, User::distinct()->count('employee_id'));
    }

    public function test_it_staff_belong_to_the_it_department_and_employees_do_not(): void
    {
        $it = Department::where('name', 'IT')->firstOrFail();

        $this->assertSame(4, User::staff()->where('department_id', $it->id)->count());
        $this->assertSame(0, User::employees()->where('department_id', $it->id)->count());
        $this->assertGreaterThanOrEqual(4, User::employees()->distinct()->count('department_id'));
    }

    public function test_demo_passwords_are_hashed_never_plain_text(): void
    {
        foreach (User::all() as $user) {
            $stored = $user->getRawOriginal('password');

            $this->assertNotSame(DemoUserSeeder::DEV_PASSWORD, $stored);
            $this->assertStringStartsWith('$2y$', $stored);
            $this->assertTrue(Hash::check(DemoUserSeeder::DEV_PASSWORD, $stored));
        }
    }

    public function test_demo_users_use_the_reserved_example_domain(): void
    {
        $this->assertSame(0, User::where('email', 'not like', '%@example.com')->count());
    }

    /* -------------------------------- tickets -------------------------------- */

    public function test_enough_realistic_tickets_exist(): void
    {
        $this->assertGreaterThanOrEqual(25, Ticket::count());

        $this->assertSame(0, Ticket::where('title', 'like', '%test%')->orWhere('title', 'like', '%lorem%')->count());
        $this->assertSame(
            Ticket::count(),
            Ticket::whereRaw('CHAR_LENGTH(description) >= 40')->count(),
            'Every ticket needs a meaningful description'
        );
        $this->assertSame(Ticket::count(), Ticket::distinct()->count('title'), 'Titles are unique');
    }

    public function test_tickets_cover_every_status_priority_and_category(): void
    {
        foreach (TicketStatusSlug::cases() as $slug) {
            $this->assertGreaterThan(0, Ticket::withStatus($slug)->count(), "No demo ticket with status {$slug->value}");
        }
        foreach (TicketPriorityLevel::cases() as $level) {
            $this->assertGreaterThan(
                0,
                Ticket::where('priority_id', TicketPriority::forLevel($level)->id)->count(),
                "No demo ticket with priority {$level->label()}"
            );
        }
        $this->assertSame(9, Ticket::distinct()->count('category_id'), 'All categories used');
    }

    public function test_tickets_span_multiple_departments_and_requesters(): void
    {
        $this->assertGreaterThanOrEqual(5, Ticket::distinct()->count('department_id'));
        $this->assertSame(8, Ticket::distinct()->count('user_id'), 'Every employee raised at least one ticket');
        $this->assertSame(0, Ticket::whereIn('user_id', User::staff()->select('id'))->count(), 'Only employees are requesters');
    }

    public function test_ticket_numbers_are_unique_and_correctly_formatted(): void
    {
        $numbers = Ticket::pluck('ticket_number');

        $this->assertSame($numbers->count(), $numbers->unique()->count());
        foreach ($numbers as $number) {
            $this->assertMatchesRegularExpression('/^INC-\d{4}-\d{6}$/', $number);
        }
    }

    public function test_ticket_department_matches_the_requesters_department(): void
    {
        $mismatches = Ticket::query()
            ->join('users', 'users.id', '=', 'tickets.user_id')
            ->whereColumn('tickets.department_id', '!=', 'users.department_id')
            ->count();

        $this->assertSame(0, $mismatches);
    }

    public function test_lifecycle_timestamps_are_consistent_with_status(): void
    {
        foreach (Ticket::with('status')->get() as $ticket) {
            $slug = $ticket->status->slug;
            $finished = in_array($slug, [TicketStatusSlug::Resolved->value, TicketStatusSlug::Closed->value], true);

            $this->assertSame($finished, $ticket->resolved_at !== null, "{$ticket->ticket_number}: resolved_at vs status {$slug}");
            $this->assertSame($finished, $ticket->resolution !== null, "{$ticket->ticket_number}: resolution vs status {$slug}");
            $this->assertSame($slug === TicketStatusSlug::Closed->value, $ticket->closed_at !== null, "{$ticket->ticket_number}: closed_at vs status {$slug}");

            if ($ticket->resolved_at) {
                $this->assertTrue($ticket->resolved_at->gte($ticket->created_at), "{$ticket->ticket_number}: resolved before created");
            }
            if ($ticket->closed_at) {
                $this->assertTrue($ticket->closed_at->gte($ticket->resolved_at), "{$ticket->ticket_number}: closed before resolved");
            }
        }
    }

    /* ----------------------------- assignments ----------------------------- */

    public function test_assignments_exist_and_match_the_ticket_status(): void
    {
        $this->assertGreaterThan(0, TicketAssignment::count());

        foreach (Ticket::with(['status', 'currentAssignment'])->get() as $ticket) {
            $assigned = $ticket->currentAssignment !== null;

            $this->assertSame(
                $ticket->status->slug !== TicketStatusSlug::Open->value,
                $assigned,
                "{$ticket->ticket_number}: only Open tickets are unassigned"
            );
        }

        $this->assertGreaterThan(0, Ticket::unassigned()->count());
        $this->assertSame(Ticket::open()->count(), Ticket::unassigned()->count());
    }

    public function test_every_ticket_has_at_most_one_open_assignment(): void
    {
        $tooMany = TicketAssignment::query()
            ->whereNull('unassigned_at')
            ->select('ticket_id')
            ->groupBy('ticket_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        $this->assertSame(0, $tooMany);
    }

    public function test_reassignment_history_is_preserved_and_chronological(): void
    {
        $reassigned = Ticket::query()->has('assignments', '>', 1)->with('assignments')->get();

        $this->assertGreaterThanOrEqual(3, $reassigned->count(), 'Several tickets should show reassignment history');

        foreach ($reassigned as $ticket) {
            $history = $ticket->assignments->sortBy('assigned_at')->values();

            foreach ($history as $i => $row) {
                $isLast = $i === $history->count() - 1;
                $this->assertSame($isLast, $row->isCurrent(), "{$ticket->ticket_number}: only the latest row is open");

                if (! $isLast) {
                    $this->assertEquals($history[$i + 1]->assigned_at, $row->unassigned_at, 'Rows chain without gaps');
                }
            }
            $this->assertGreaterThan(1, $history->pluck('assigned_to')->count());
        }
    }

    public function test_assignees_and_assigners_are_active_it_staff(): void
    {
        $this->assertSame(
            0,
            TicketAssignment::query()
                ->whereNotIn('assigned_to', User::staff()->active()->select('id'))
                ->orWhereNotIn('assigned_by', User::staff()->active()->select('id'))
                ->count()
        );
        $this->assertGreaterThanOrEqual(3, TicketAssignment::distinct()->count('assigned_to'), 'Work is spread across agents');
    }

    /* -------------------------------- comments -------------------------------- */

    public function test_comments_exist_with_realistic_bodies(): void
    {
        $this->assertGreaterThanOrEqual(40, TicketComment::count());
        $this->assertGreaterThanOrEqual(15, Ticket::has('comments')->count());
        $this->assertSame(0, TicketComment::whereRaw('CHAR_LENGTH(body) < 10')->count());
    }

    public function test_internal_notes_exist_and_are_always_written_by_it_staff(): void
    {
        $internal = TicketComment::internal();

        $this->assertGreaterThanOrEqual(8, $internal->count());
        $this->assertSame(0, TicketComment::internal()->whereNotIn('user_id', User::staff()->select('id'))->count());
        $this->assertGreaterThan(0, TicketComment::visibleToRequester()->count(), 'Public conversation exists too');
    }

    public function test_employees_never_receive_internal_notes_from_the_demo_data(): void
    {
        $requester = User::employees()->whereHas('tickets', fn ($q) => $q->has('comments'))->firstOrFail();

        $visible = TicketComment::visibleTo($requester)->get();

        $this->assertGreaterThan(0, $visible->count());
        $this->assertSame(0, $visible->where('is_internal', true)->count());
    }

    public function test_conversations_are_chronological_and_start_after_ticket_creation(): void
    {
        $early = TicketComment::query()
            ->join('tickets', 'tickets.id', '=', 'ticket_comments.ticket_id')
            ->whereColumn('ticket_comments.created_at', '<', 'tickets.created_at')
            ->count();

        $this->assertSame(0, $early);
    }

    public function test_it_staff_who_comment_were_assigned_to_that_ticket(): void
    {
        $staffComments = TicketComment::query()->whereIn('user_id', User::staff()->select('id'))->get(['ticket_id', 'user_id']);

        foreach ($staffComments as $comment) {
            $this->assertTrue(
                TicketAssignment::where('ticket_id', $comment->ticket_id)->where('assigned_to', $comment->user_id)->exists(),
                'A support agent commented on a ticket they were never assigned to'
            );
        }
    }

    /* ------------------------------ attachments ------------------------------ */

    public function test_no_fake_attachments_are_created(): void
    {
        $this->assertSame(0, TicketAttachment::count());
    }

    /* ------------------------------ idempotency ------------------------------ */

    public function test_reseeding_is_idempotent_and_does_not_touch_existing_data(): void
    {
        $snapshot = fn () => [
            'counts' => [
                User::count(), Ticket::count(), TicketComment::count(), TicketAssignment::count(),
                Department::count(), TicketCategory::count(), TicketPriority::count(), TicketStatus::count(),
            ],
            'users' => DB::table('users')->orderBy('id')->get(['id', 'email', 'password', 'role', 'updated_at'])->toJson(),
            'tickets' => DB::table('tickets')->orderBy('id')->get(['id', 'ticket_number', 'status_id', 'created_at', 'updated_at'])->toJson(),
            'assignments' => DB::table('ticket_assignments')->orderBy('id')->get(['id', 'assigned_to', 'assigned_at', 'unassigned_at'])->toJson(),
        ];

        $before = $snapshot();

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame($before, $snapshot());
    }

    public function test_reseeding_does_not_overwrite_a_changed_demo_password(): void
    {
        $user = User::where('email', 'employee1@example.com')->firstOrFail();
        $user->password = 'A-brand-new-password-1';
        $user->save();

        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(Hash::check('A-brand-new-password-1', $user->fresh()->password));
    }

    public function test_reseeding_restores_a_soft_deleted_demo_user_instead_of_failing(): void
    {
        User::where('email', 'employee2@example.com')->firstOrFail()->delete();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(12, User::count());
    }

    /* ------------------------------- production ------------------------------- */

    public function test_demo_user_seeder_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);
        // Run the seeder class directly: `db:seed` itself would stop at Laravel's own
        // "run in production?" confirmation before reaching this guard.
        $this->app->make(DemoUserSeeder::class)->run();
    }

    public function test_database_seeder_skips_all_demo_data_in_production(): void
    {
        $this->removeDemoRows();
        $this->app['env'] = 'production';

        $this->app->make(DatabaseSeeder::class)->run();

        $this->assertSame(0, User::count());
        $this->assertSame(0, Ticket::count());
        $this->assertSame(7, Department::count(), 'Master data is still seeded');
    }

    private function removeDemoRows(): void
    {
        // Remove the demo rows seeded in setUp() (children first: FK order) so this
        // test can prove a production-environment seed adds none of its own.
        DB::table('ticket_comments')->delete();
        DB::table('ticket_assignments')->delete();
        DB::table('tickets')->delete();
        DB::table('users')->delete();
    }
}
