<?php

namespace Tests\Feature\Authorization;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\TicketAttachment;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketWorkReport;
use App\Models\User;
use App\Policies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\BuildsAuthScenario;
use Tests\Concerns\BuildsTicketFixtures;
use Tests\Concerns\SeedsMasterData;
use Tests\TestCase;

/**
 * Comment / attachment / assignment / user / master-data policies.
 * (HTTP-level IDOR checks live in IdorTest; these pin the rules themselves.)
 */
class RelatedPoliciesTest extends TestCase
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

    private function can(User $user, string $ability, mixed $arguments): bool
    {
        return Gate::forUser($user)->allows($ability, $arguments);
    }

    /* ----- registration ----- */

    public function test_every_model_has_its_policy_registered(): void
    {
        $expected = [
            Ticket::class => Policies\TicketPolicy::class,
            TicketComment::class => Policies\TicketCommentPolicy::class,
            TicketAttachment::class => Policies\TicketAttachmentPolicy::class,
            TicketAssignment::class => Policies\TicketAssignmentPolicy::class,
            TicketWorkReport::class => Policies\TicketWorkReportPolicy::class,
            User::class => Policies\UserPolicy::class,
            Department::class => Policies\DepartmentPolicy::class,
            TicketCategory::class => Policies\TicketCategoryPolicy::class,
            TicketPriority::class => Policies\TicketPriorityPolicy::class,
            TicketStatus::class => Policies\TicketStatusPolicy::class,
        ];

        foreach ($expected as $model => $policy) {
            $this->assertInstanceOf($policy, Gate::getPolicyFor($model), "{$model} has no/incorrect policy");
        }

        // Nothing slipped through: every Eloquent model in app/Models is covered above.
        foreach (glob(app_path('Models/*.php')) as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');
            $this->assertArrayHasKey($class, $expected, "{$class} has no policy test coverage");
        }
    }

    /* ----- comments ----- */

    public function test_comment_view_rules(): void
    {
        // [user, comment, expected]
        $cases = [
            ['employeeA', 'publicA', true],      // own ticket, public
            ['employeeA', 'internalA', false],   // own ticket, INTERNAL note
            ['employeeA', 'publicB', false],     // someone else's ticket
            ['employeeA', 'internalB', false],
            ['employeeB', 'publicA', false],
            ['supportOne', 'publicA', true],
            ['supportOne', 'internalA', true],   // assigned: sees internal notes
            ['supportOne', 'publicB', true],     // support agents share ticket read access
            ['supportOne', 'internalB', true],
            ['supportTwo', 'internalA', true],
            ['admin', 'publicA', true],
            ['admin', 'internalA', true],
            ['admin', 'internalB', true],
        ];

        foreach ($cases as [$who, $comment, $expected]) {
            $this->assertSame($expected, $this->can($this->{$who}, 'view', $this->{$comment}), "{$who} -> {$comment}");
        }
    }

    public function test_comment_creation_rules(): void
    {
        $create = fn (User $u, Ticket $t, bool $internal) => $this->can($u, 'create', [TicketComment::class, $t, $internal]);

        // public comment
        $this->assertTrue($create($this->employeeA, $this->ticketA, false));
        $this->assertFalse($create($this->employeeA, $this->ticketB, false), 'cannot comment on another employee\'s ticket');
        $this->assertTrue($create($this->supportOne, $this->ticketA, false));
        $this->assertFalse($create($this->supportOne, $this->ticketB, false));
        $this->assertTrue($create($this->admin, $this->ticketB, false));

        // internal notes
        $this->assertFalse($create($this->employeeA, $this->ticketA, true), 'employees can never write internal notes');
        $this->assertTrue($create($this->supportOne, $this->ticketA, true));
        $this->assertTrue($create($this->supportOne, $this->ticketQueueB, true), 'queue tickets may receive staff notes');
        $this->assertFalse($create($this->supportOne, $this->ticketB, true));
        $this->assertTrue($create($this->admin, $this->ticketB, true));
    }

    public function test_comments_cannot_be_edited_or_deleted_in_this_phase(): void
    {
        foreach ([$this->employeeA, $this->supportOne, $this->admin] as $user) {
            $this->assertFalse($this->can($user, 'update', $this->publicA));
            $this->assertFalse($this->can($user, 'delete', $this->publicA));
        }
    }

    public function test_the_comment_policy_uses_the_real_parent_ticket(): void
    {
        // A comment that belongs to ticket B, "presented" next to ticket A in memory:
        $comment = $this->publicB->setRelation('ticket', $this->ticketB);

        $this->assertFalse($this->can($this->employeeA, 'view', $comment));

        $fresh = TicketComment::find($this->publicB->id); // relation NOT loaded -> queried
        $this->assertFalse($this->can($this->employeeA, 'view', $fresh));
        $this->assertTrue($this->can($this->employeeB, 'view', $fresh));
    }

    /* ----- attachments ----- */

    public function test_attachment_view_and_download_rules(): void
    {
        $cases = [
            ['employeeA', 'attA', true],
            ['employeeA', 'attInternalA', false],  // attached to an internal note
            ['employeeA', 'attB', false],
            ['employeeB', 'attA', false],
            ['supportOne', 'attA', true],
            ['supportOne', 'attInternalA', true],
            ['supportOne', 'attB', true],
            ['supportTwo', 'attA', true],
            ['supportTwo', 'attB', true],
            ['admin', 'attA', true],
            ['admin', 'attInternalA', true],
            ['admin', 'attB', true],
        ];

        foreach ($cases as [$who, $attachment, $expected]) {
            $this->assertSame($expected, $this->can($this->{$who}, 'view', $this->{$attachment}), "view {$who} -> {$attachment}");
            $this->assertSame($expected, $this->can($this->{$who}, 'download', $this->{$attachment}), "download {$who} -> {$attachment}");
        }
    }

    public function test_attachment_upload_rules(): void
    {
        $upload = fn (User $u, Ticket $t) => $this->can($u, 'create', [TicketAttachment::class, $t]);

        $this->assertTrue($upload($this->employeeA, $this->ticketA));
        $this->assertFalse($upload($this->employeeA, $this->ticketB));
        $this->assertTrue($upload($this->supportOne, $this->ticketA));
        $this->assertFalse($upload($this->supportOne, $this->ticketB), 'view access does not grant upload permission');
        $this->assertTrue($upload($this->admin, $this->ticketB));
    }

    public function test_attachments_cannot_be_edited_or_deleted_in_this_phase(): void
    {
        foreach ([$this->employeeA, $this->supportOne, $this->admin] as $user) {
            $this->assertFalse($this->can($user, 'update', $this->attA));
            $this->assertFalse($this->can($user, 'delete', $this->attA));
        }
    }

    /* ----- assignments ----- */

    public function test_assignment_history_is_internal_and_immutable(): void
    {
        $row = $this->ticketA->assignments()->first();  // supportOne holds ticketA
        $rowB = $this->ticketB->assignments()->first(); // supportTwo holds ticketB

        $this->assertFalse($this->can($this->employeeA, 'view', $row), 'requesters do not see assignment history');
        $this->assertFalse($this->can($this->employeeB, 'view', $row));
        $this->assertTrue($this->can($this->supportOne, 'view', $row));
        $this->assertTrue($this->can($this->supportTwo, 'view', $row), 'support can read history for visible tickets');
        $this->assertTrue($this->can($this->supportTwo, 'view', $rowB));
        $this->assertTrue($this->can($this->admin, 'view', $row));

        foreach ([$this->employeeA, $this->supportOne, $this->admin] as $user) {
            $this->assertFalse($this->can($user, 'update', $row), 'history is append-only');
            $this->assertFalse($this->can($user, 'delete', $row));
        }
    }

    public function test_assigning_follows_the_ticket_assign_ability(): void
    {
        $assign = fn (User $u, Ticket $t) => $this->can($u, 'create', [TicketAssignment::class, $t]);

        $this->assertFalse($assign($this->employeeA, $this->ticketA));
        $this->assertTrue($assign($this->supportOne, $this->ticketA));
        $this->assertTrue($assign($this->supportOne, $this->ticketQueueB));
        $this->assertFalse($assign($this->supportOne, $this->ticketB));
        $this->assertTrue($assign($this->admin, $this->ticketB));
    }

    public function test_work_report_access_is_limited_to_it_support_and_its_technician(): void
    {
        $technician = User::factory()->technician()->inDepartment($this->supportOne->department_id)->create();
        $ticket = $this->makeTicket($this->employeeA);
        $assignment = $this->makeAssignment($ticket, $technician, $this->admin);
        $report = $assignment->workReports()->create();

        foreach ([$this->employeeA, $this->supportOne, $this->admin, $technician] as $user) {
            $this->assertSame($user->is($this->employeeA) ? false : true, $this->can($user, 'view', $report));
            $this->assertFalse($this->can($user, 'update', $report));
            $this->assertFalse($this->can($user, 'delete', $report));
        }
    }

    /* ----- users ----- */

    public function test_user_policy_rules(): void
    {
        $this->assertTrue($this->can($this->admin, 'viewAny', User::class));
        $this->assertFalse($this->can($this->supportOne, 'viewAny', User::class));
        $this->assertFalse($this->can($this->employeeA, 'viewAny', User::class));

        // view / self-service update
        $this->assertTrue($this->can($this->employeeA, 'view', $this->employeeA));
        $this->assertTrue($this->can($this->employeeA, 'update', $this->employeeA));
        $this->assertFalse($this->can($this->employeeA, 'view', $this->employeeB));
        $this->assertFalse($this->can($this->employeeA, 'update', $this->employeeB));
        $this->assertFalse($this->can($this->supportOne, 'update', $this->employeeA));
        $this->assertTrue($this->can($this->admin, 'update', $this->employeeA));

        // privileged fields: admin only, never on yourself
        foreach (['updateRole', 'updateStatus', 'delete'] as $ability) {
            $this->assertFalse($this->can($this->employeeA, $ability, $this->employeeA), "employee self {$ability}");
            $this->assertFalse($this->can($this->employeeA, $ability, $this->employeeB), "employee other {$ability}");
            $this->assertFalse($this->can($this->supportOne, $ability, $this->supportOne), "support self {$ability}");
            $this->assertFalse($this->can($this->supportOne, $ability, $this->employeeA), "support other {$ability}");
            $this->assertTrue($this->can($this->admin, $ability, $this->employeeA), "admin other {$ability}");
            $this->assertFalse($this->can($this->admin, $ability, $this->admin), "admin self {$ability} (lock-out protection)");
        }

        // department: admin only
        $this->assertFalse($this->can($this->employeeA, 'updateDepartment', $this->employeeA));
        $this->assertFalse($this->can($this->supportOne, 'updateDepartment', $this->employeeA));
        $this->assertTrue($this->can($this->admin, 'updateDepartment', $this->employeeA));
    }

    public function test_deactivated_users_have_no_user_management_rights(): void
    {
        $inactiveAdmin = User::factory()->admin()->inactive()->create();

        foreach (['viewAny', 'view', 'update', 'updateRole', 'updateStatus', 'updateDepartment', 'delete'] as $ability) {
            $arguments = $ability === 'viewAny' ? User::class : $this->employeeA;
            $this->assertFalse($this->can($inactiveAdmin, $ability, $arguments), $ability);
        }
    }

    /* ----- reference data ----- */

    public function test_master_data_is_readable_by_all_but_writable_by_admin_only(): void
    {
        $models = [
            Department::first(),
            TicketCategory::first(),
            TicketPriority::first(),
            TicketStatus::first(),
        ];

        foreach ($models as $model) {
            foreach ([$this->employeeA, $this->supportOne, $this->admin] as $user) {
                $this->assertTrue($this->can($user, 'viewAny', $model::class));
                $this->assertTrue($this->can($user, 'view', $model));
            }

            foreach (['update', 'delete', 'restore'] as $ability) {
                $this->assertFalse($this->can($this->employeeA, $ability, $model), $model::class." employee {$ability}");
                $this->assertFalse($this->can($this->supportOne, $ability, $model), $model::class." support {$ability}");
                $this->assertTrue($this->can($this->admin, $ability, $model), $model::class." admin {$ability}");
            }

            $this->assertFalse($this->can($this->employeeA, 'create', $model::class));
            $this->assertFalse($this->can($this->supportOne, 'create', $model::class));
            $this->assertTrue($this->can($this->admin, 'create', $model::class));
        }
    }
}
