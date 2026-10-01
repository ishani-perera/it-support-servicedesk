<?php

namespace Tests\Feature\Database;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\TicketAssignment;
use App\Models\TicketAttachment;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsTicketFixtures;
use Tests\Concerns\SeedsMasterData;
use Tests\TestCase;

/**
 * Proves the database itself enforces integrity (not just the PHP layer).
 */
class ConstraintsTest extends TestCase
{
    use BuildsTicketFixtures;
    use RefreshDatabase;
    use SeedsMasterData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMasterData();
    }

    /* ---------------------------- unique constraints ---------------------------- */

    public function test_department_name_must_be_unique(): void
    {
        $this->expectException(QueryException::class);
        Department::create(['name' => 'IT']);
    }

    public function test_category_name_must_be_unique(): void
    {
        $this->expectException(QueryException::class);
        TicketCategory::create(['name' => 'Hardware']);
    }

    public function test_priority_name_and_level_must_be_unique(): void
    {
        $base = ['color' => '#000000', 'sla_response_minutes' => 1, 'sla_resolution_minutes' => 2];

        try {
            TicketPriority::create($base + ['name' => 'Low', 'level' => 99]);
            $this->fail('Duplicate priority name was accepted');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->expectException(QueryException::class);
        TicketPriority::create($base + ['name' => 'Brand New', 'level' => 1]);
    }

    public function test_status_slug_must_be_unique(): void
    {
        $this->expectException(QueryException::class);
        TicketStatus::create(['name' => 'Different Name', 'slug' => 'open', 'color' => '#000000']);
    }

    public function test_ticket_number_must_be_unique(): void
    {
        $this->makeTicket(overrides: ['ticket_number' => 'INC-2026-000001']);

        $this->expectException(QueryException::class);
        $this->makeTicket(overrides: ['ticket_number' => 'INC-2026-000001']);
    }

    public function test_user_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'a@example.com']);

        $this->expectException(QueryException::class);
        User::factory()->create(['email' => 'a@example.com']);
    }

    public function test_employee_id_is_unique_but_may_be_null_for_many_users(): void
    {
        User::factory()->count(2)->create(['employee_id' => null]); // allowed
        User::factory()->create(['employee_id' => 'EMP-001']);

        $this->expectException(QueryException::class);
        User::factory()->create(['employee_id' => 'EMP-001']);
    }

    public function test_database_rejects_an_invalid_user_role(): void
    {
        // Bypass Eloquent (whose enum cast would throw first) to prove the DB itself enforces it.
        $this->expectException(QueryException::class);
        DB::table('users')->insert([
            'name' => 'Raw', 'email' => 'bad-role@example.com', 'password' => 'x', 'role' => 'superuser',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_eloquent_rejects_an_invalid_user_role(): void
    {
        $this->expectException(\ValueError::class);
        User::factory()->create(['role' => 'superuser']);
    }

    public function test_new_users_default_to_employee_role_and_active(): void
    {
        $id = DB::table('users')->insertGetId([
            'name' => 'Raw', 'email' => 'raw@example.com', 'password' => 'x',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $user = User::findOrFail($id);
        $this->assertSame(UserRole::Employee, $user->role);
        $this->assertTrue($user->is_active);
    }

    /* ------------------------------ foreign keys ------------------------------- */

    public function test_ticket_requires_valid_foreign_keys(): void
    {
        $this->expectException(QueryException::class);
        $this->makeTicket(overrides: ['category_id' => 999999]);
    }

    public function test_user_cannot_reference_missing_department(): void
    {
        $this->expectException(QueryException::class);
        User::factory()->create(['department_id' => 999999]);
    }

    public function test_deleting_a_department_with_users_is_restricted(): void
    {
        $department = Department::where('name', 'IT')->firstOrFail();
        $user = $this->makeUser(UserRole::Support, $department);

        try {
            $department->forceDelete();
            $this->fail('Department with users was hard-deleted');
        } catch (QueryException) {
            $this->assertNotNull($user->fresh()->department_id);
        }
    }

    public function test_deleting_referenced_category_priority_and_status_is_restricted(): void
    {
        $ticket = $this->makeTicket();

        foreach ([$ticket->category, $ticket->priority] as $model) {
            try {
                $model->forceDelete();
                $this->fail(class_basename($model).' referenced by a ticket was hard-deleted');
            } catch (QueryException) {
                $this->assertTrue(true);
            }
        }

        try {
            $ticket->status->delete();
            $this->fail('Status referenced by a ticket was deleted');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseHas('tickets', ['id' => $ticket->id]);
    }

    public function test_hard_deleting_a_user_with_tickets_is_restricted(): void
    {
        $ticket = $this->makeTicket();

        $this->expectException(QueryException::class);
        $ticket->user->forceDelete();
    }

    public function test_deleting_a_ticket_cascades_to_comments_attachments_and_assignments(): void
    {
        $ticket = $this->makeTicket();
        $staff = $this->makeUser(UserRole::Support);
        $admin = $this->makeUser(UserRole::Admin);

        $comment = $this->makeComment($ticket, $staff);
        $this->makeAttachment($ticket, $staff);
        $this->makeAttachment($ticket, $staff, $comment);
        $this->makeAssignment($ticket, $staff, $admin);

        $ticket->delete();

        $this->assertSame(0, TicketComment::count());
        $this->assertSame(0, TicketAttachment::count());
        $this->assertSame(0, TicketAssignment::count());
        $this->assertSame(3, User::count(), 'Requester, staff and admin must survive ticket deletion');
    }

    public function test_deleting_a_comment_removes_its_attachments_but_not_ticket_level_ones(): void
    {
        $ticket = $this->makeTicket();
        $staff = $this->makeUser(UserRole::Support);
        $comment = $this->makeComment($ticket, $staff, internal: true);

        $this->makeAttachment($ticket, $staff, $comment);
        $ticketLevel = $this->makeAttachment($ticket, $staff);

        $comment->delete();

        $this->assertSame([$ticketLevel->id], TicketAttachment::pluck('id')->all());
    }

    public function test_attachment_comment_must_belong_to_the_same_ticket(): void
    {
        $ticketA = $this->makeTicket();
        $ticketB = $this->makeTicket();
        $commentOnB = $this->makeComment($ticketB, $ticketB->user);

        $this->expectException(QueryException::class);
        $this->makeAttachment($ticketA, $ticketA->user, $commentOnB);
    }

    public function test_attachment_file_path_must_be_unique(): void
    {
        $ticket = $this->makeTicket();
        $this->makeAttachment($ticket, $ticket->user, null, ['file_path' => 'tickets/1/same.pdf']);

        $this->expectException(QueryException::class);
        $this->makeAttachment($ticket, $ticket->user, null, ['file_path' => 'tickets/1/same.pdf']);
    }

    /* ------------------------- assignment history rules ------------------------ */

    public function test_a_ticket_can_have_only_one_open_assignment(): void
    {
        $ticket = $this->makeTicket();
        $admin = $this->makeUser(UserRole::Admin);
        $a = $this->makeUser(UserRole::Support);
        $b = $this->makeUser(UserRole::Support);

        $this->makeAssignment($ticket, $a, $admin);

        $this->expectException(QueryException::class);
        $this->makeAssignment($ticket, $b, $admin);
    }

    public function test_reassigning_after_closing_the_previous_assignment_keeps_history(): void
    {
        $ticket = $this->makeTicket();
        $admin = $this->makeUser(UserRole::Admin);
        $a = $this->makeUser(UserRole::Support);
        $b = $this->makeUser(UserRole::Support);
        $c = $this->makeUser(UserRole::Support);

        $first = $this->makeAssignment($ticket, $a, $admin);
        $first->update(['unassigned_at' => now()]);
        $second = $this->makeAssignment($ticket, $b, $admin);
        $second->update(['unassigned_at' => now()]);
        $open = $this->makeAssignment($ticket, $c, $admin);

        $this->assertSame(3, $ticket->assignments()->count());
        $this->assertSame(1, $ticket->assignments()->whereNull('unassigned_at')->count());
        $this->assertTrue($open->fresh()->is_current);
        $this->assertNull($first->fresh()->is_current, 'Closed assignments carry no current marker');
    }

    public function test_different_tickets_may_each_have_an_open_assignment_to_the_same_user(): void
    {
        $admin = $this->makeUser(UserRole::Admin);
        $staff = $this->makeUser(UserRole::Support);

        $this->makeAssignment($this->makeTicket(), $staff, $admin);
        $this->makeAssignment($this->makeTicket(), $staff, $admin);

        $this->assertCount(2, $staff->assignedTickets);
    }

    /* ------------------------------- soft deletes ------------------------------ */

    public function test_soft_deleted_master_data_and_users_are_hidden_but_recoverable(): void
    {
        $category = TicketCategory::where('name', 'Printer')->firstOrFail();
        $category->delete();

        $this->assertNull(TicketCategory::find($category->id));
        $this->assertNotNull(TicketCategory::withTrashed()->find($category->id));

        $user = $this->makeUser();
        $user->delete();
        $this->assertNull(User::find($user->id));
        $user->restore();
        $this->assertNotNull(User::find($user->id));
    }

    public function test_reseeding_does_not_break_when_master_data_was_soft_deleted(): void
    {
        TicketCategory::where('name', 'Printer')->firstOrFail()->delete();
        Department::where('name', 'Sales')->firstOrFail()->delete();

        $this->seedMasterData(); // must not hit a unique violation

        $this->assertSame(9, TicketCategory::withTrashed()->count());
        $this->assertSame(7, Department::withTrashed()->count());
    }
}
