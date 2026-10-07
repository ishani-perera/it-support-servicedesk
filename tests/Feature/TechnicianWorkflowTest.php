<?php

namespace Tests\Feature;

use App\Enums\TechnicianWorkReviewStatus;
use App\Enums\TicketStatusSlug;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketWorkReport;
use App\Models\User;
use App\Services\TicketAssignmentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTicketFixtures;
use Tests\Concerns\SeedsMasterData;
use Tests\TestCase;

class TechnicianWorkflowTest extends TestCase
{
    use BuildsTicketFixtures;
    use RefreshDatabase;
    use SeedsMasterData;

    private User $employee;

    private User $support;

    private User $admin;

    private User $technician;

    private User $otherTechnician;

    private User $supportObserver;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMasterData();
        $itDepartment = Department::where('name', 'IT')->firstOrFail();
        $this->employee = User::factory()->inDepartment(Department::where('name', 'Finance')->firstOrFail())->create();
        $this->support = User::factory()->support()->inDepartment($itDepartment)->create();
        $this->supportObserver = User::factory()->support()->inDepartment($itDepartment)->create();
        $this->admin = User::factory()->admin()->create();
        $this->technician = User::factory()->technician()->inDepartment($itDepartment)->create();
        $this->otherTechnician = User::factory()->technician()->inDepartment($itDepartment)->create();
        $this->ticket = $this->makeTicket($this->employee);
    }

    public function test_technician_ticket_lifecycle_and_it_support_approval(): void
    {
        $this->actingAs($this->employee)->postJson('/api/tickets/'.$this->ticket->id.'/work/start')->assertForbidden();

        $this->actingAs($this->support)->postJson('/api/tickets/'.$this->ticket->id.'/technician-assignment', [
            'assigned_to' => $this->technician->id,
            'note' => 'Hardware investigation',
        ])->assertCreated()->assertJsonPath('data.assigned_to', $this->technician->id);
        $this->assertSame(TicketStatusSlug::Assigned->value, $this->ticket->fresh()->status->slug);
        $this->assertNotificationCount($this->technician, 'Ticket assigned to you', 1);
        $this->assertNotificationCount($this->support, 'Technician assigned', 0);
        $this->assertNotificationCount($this->supportObserver, 'Technician assigned', 1);
        $assignmentNotice = $this->supportObserver->notifications()->firstOrFail()->data;
        $this->assertSame($this->ticket->title, $assignmentNotice['ticket_title']);
        $this->assertSame($this->support->name, $assignmentNotice['context']['assigned_by']);

        $this->actingAs($this->technician)->getJson('/api/tickets')->assertOk()->assertJsonFragment(['id' => $this->ticket->id]);
        $this->actingAs($this->technician)->getJson('/api/tickets/'.$this->ticket->id)->assertOk();
        $this->actingAs($this->otherTechnician)->getJson('/api/tickets/'.$this->ticket->id)->assertForbidden();

        $this->actingAs($this->technician)->patchJson('/api/tickets/'.$this->ticket->id.'/status', [
            'status' => TicketStatusSlug::InProgress->value,
        ])->assertForbidden();
        $this->actingAs($this->admin)->patchJson('/api/tickets/'.$this->ticket->id.'/status', [
            'status' => TicketStatusSlug::ItSupportReview->value,
        ])->assertUnprocessable();

        $this->actingAs($this->technician)->postJson('/api/tickets/'.$this->ticket->id.'/work/start')->assertCreated();
        $this->assertNotificationCount($this->supportObserver, 'Technician started work', 1);
        $report = TicketWorkReport::firstOrFail();
        $this->assertSame($this->technician->id, $report->started_by);
        $this->assertNotNull($report->work_started_at);
        $this->assertSame(TicketStatusSlug::InProgress->value, $this->ticket->fresh()->status->slug);
        $this->actingAs($this->technician)->postJson('/api/tickets/'.$this->ticket->id.'/work/start')->assertUnprocessable();

        $this->actingAs($this->technician)->postJson('/api/tickets/'.$this->ticket->id.'/comments', [
            'body' => 'Internal progress note',
            'is_internal' => true,
        ])->assertCreated();
        $this->assertNotificationCount($this->support, 'Technician added an internal note', 1);
        $this->assertNotificationCount($this->employee, 'Technician added an internal note', 0);
        $this->assertSame('Internal progress note', $this->actingAs($this->support)->getJson('/api/tickets/'.$this->ticket->id.'/comments')
            ->assertOk()->json('data.0.body'));
        $this->actingAs($this->employee)->getJson('/api/tickets/'.$this->ticket->id.'/comments')
            ->assertOk()->assertJsonMissing(['body' => 'Internal progress note']);
        $this->actingAs($this->employee)->getJson('/api/tickets/'.$this->ticket->id.'/comments/'.TicketComment::query()->where('body', 'Internal progress note')->value('id'))
            ->assertForbidden();
        $this->actingAs($this->otherTechnician)->getJson('/api/tickets/'.$this->ticket->id.'/comments')
            ->assertForbidden();

        $this->actingAs($this->support)->postJson('/api/tickets/'.$this->ticket->id.'/comments', [
            'body' => 'Please include the port check in your work report.',
            'is_internal' => true,
        ])->assertCreated();
        $this->assertNotificationCount($this->technician, 'IT Support added an internal note', 1);
        $this->assertSame('Please include the port check in your work report.', $this->actingAs($this->technician)
            ->getJson('/api/tickets/'.$this->ticket->id.'/comments')->assertOk()->json('data.1.body'));
        $this->assertNotificationCount($this->employee, 'IT Support added an internal note', 0);

        $genericStatusNotificationsBeforeRequest = $this->employee->notifications()->where('data->title', 'Ticket status updated')->count();
        $this->actingAs($this->technician)->postJson('/api/tickets/'.$this->ticket->id.'/work/request-information', [
            'body' => 'Please confirm whether the router power light is on.',
        ])->assertCreated();
        $this->assertSame(TicketStatusSlug::WaitingForUser->value, $this->ticket->fresh()->status->slug);
        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $this->ticket->id,
            'user_id' => $this->technician->id,
            'is_internal' => false,
            'body' => 'Please confirm whether the router power light is on.',
        ]);
        $this->assertContains('Action required: more information needed', $this->notificationTitles($this->employee));
        $this->assertNotificationCount($this->employee, 'Action required: more information needed', 1);
        $this->assertSame(
            $genericStatusNotificationsBeforeRequest,
            $this->employee->notifications()->where('data->title', 'Ticket status updated')->count(),
        );
        $this->assertSame(1, $this->employee->notifications()->where('data->title', 'Action required: more information needed')->count());

        $techNotificationsBeforeReply = count($this->notificationTitles($this->technician));
        $this->actingAs($this->employee)->postJson('/api/tickets/'.$this->ticket->id.'/comments', [
            'body' => 'The power light is on and stable.',
        ])->assertCreated();
        $this->assertSame(TicketStatusSlug::InProgress->value, $this->ticket->fresh()->status->slug);
        $this->assertGreaterThan($techNotificationsBeforeReply, count($this->notificationTitles($this->technician)));
        $this->assertNotificationCount($this->technician, 'New public reply', 1);

        $this->actingAs($this->technician)->postJson('/api/tickets/'.$this->ticket->id.'/work/complete', [
            'work_summary' => 'Replaced and configured the router.',
            'root_cause' => 'The original router power supply had failed.',
            'technician_notes' => 'Connectivity was verified from the employee workstation.',
            'reviewed_by' => $this->technician->id,
            'review_status' => TechnicianWorkReviewStatus::Approved->value,
            'review_note' => 'forged review value',
        ])->assertOk();

        $report->refresh();
        $this->assertSame('Replaced and configured the router.', $report->work_summary);
        $this->assertSame('The original router power supply had failed.', $report->root_cause);
        $this->assertNotNull($report->work_completed_at);
        $this->assertSame(TechnicianWorkReviewStatus::Pending, $report->review_status);
        $this->assertNull($report->reviewed_by);
        $this->assertNull($report->review_note);
        $this->assertSame(TicketStatusSlug::ItSupportReview->value, $this->ticket->fresh()->status->slug);
        $this->assertContains('Technician work ready for review', $this->notificationTitles($this->support));
        $this->assertNotificationCount($this->support, 'Technician work ready for review', 1);
        $this->assertNotificationCount($this->admin, 'Technician work ready for review', 0);
        $this->assertNotificationCount($this->employee, 'Technician work ready for review', 0);

        $this->actAsFresh($this->support)->get('/tickets/'.$this->ticket->id)->assertOk()
            ->assertSee('Technician work')
            ->assertSee('IT Support review')
            ->assertSee('Replaced and configured the router.')
            ->assertSee('The original router power supply had failed.')
            ->assertSee('Connectivity was verified from the employee workstation.')
            ->assertSee('Approve and close ticket')
            ->assertSee('Send back to Technician')
            ->assertSee('Revision reason')
            ->assertSee('data-trim-required', false)
            ->assertSee('data-async-workflow-form', false);
        $this->actAsFresh($this->employee)->get('/tickets/'.$this->ticket->id)->assertOk()
            ->assertDontSee('Technician work')
            ->assertDontSee('Replaced and configured the router.')
            ->assertDontSee('The original router power supply had failed.')
            ->assertDontSee('Connectivity was verified from the employee workstation.')
            ->assertDontSee('Internal progress note');

        $this->actingAs($this->technician)->postJson('/api/tickets/'.$this->ticket->id.'/work/approve')->assertForbidden();
        $this->actingAs($this->technician)->patchJson('/api/tickets/'.$this->ticket->id.'/status', [
            'status' => TicketStatusSlug::Closed->value,
        ])->assertForbidden();
        $this->actingAs($this->technician)->postJson('/api/tickets/'.$this->ticket->id.'/technician-assignment', [
            'assigned_to' => $this->otherTechnician->id,
        ])->assertForbidden();

        $this->actingAs($this->support)->getJson('/api/tickets/'.$this->ticket->id.'/work-reports')
            ->assertOk()->assertJsonPath('data.0.work_summary', 'Replaced and configured the router.');
        $this->actingAs($this->employee)->getJson('/api/tickets/'.$this->ticket->id.'/work-reports')
            ->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs($this->admin)->patchJson('/api/tickets/'.$this->ticket->id.'/status', [
            'status' => TicketStatusSlug::Closed->value,
        ])->assertForbidden();

        $this->actingAs($this->support)->postJson('/api/tickets/'.$this->ticket->id.'/work/approve', [
            'review_note' => 'Validated against the reported symptom.',
        ])->assertOk();
        $report->refresh();
        $this->assertSame(TechnicianWorkReviewStatus::Approved, $report->review_status);
        $this->assertSame($this->support->id, $report->reviewed_by);
        $this->assertNotNull($report->reviewed_at);
        $this->assertSame('Validated against the reported symptom.', $report->review_note);
        $this->assertSame(TicketStatusSlug::Closed->value, $this->ticket->fresh()->status->slug);
        $this->assertNotNull($this->ticket->fresh()->closed_at);
        $this->assertNotificationCount($this->employee, 'Ticket closed', 1);
        $this->assertNotificationCount($this->technician, 'Ticket closed', 1);
        $this->assertNotificationCount($this->employee, 'Technician work ready for review', 0);
    }

    public function test_support_can_send_work_back_with_a_reason_and_technician_can_continue(): void
    {
        $this->assignTechnician();
        $this->actingAs($this->technician)->postJson('/api/tickets/'.$this->ticket->id.'/work/start')->assertCreated();
        $this->actingAs($this->technician)->postJson('/api/tickets/'.$this->ticket->id.'/work/complete', [
            'work_summary' => 'Replaced the network cable.',
            'root_cause' => 'Cable damage.',
        ])->assertOk();
        $firstReport = TicketWorkReport::firstOrFail();

        $this->actingAs($this->support)->postJson('/api/tickets/'.$this->ticket->id.'/work/send-back', [
            'review_note' => '',
        ])->assertUnprocessable();
        $this->assertSame(TicketStatusSlug::ItSupportReview->value, $this->ticket->fresh()->status->slug);

        $this->actingAs($this->support)->postJson('/api/tickets/'.$this->ticket->id.'/work/send-back', [
            'review_note' => 'Please verify the replacement cable at both ends.',
        ])->assertOk();
        $firstReport->refresh();
        $this->assertSame(TechnicianWorkReviewStatus::ChangesRequested, $firstReport->review_status);
        $this->assertSame('Please verify the replacement cable at both ends.', $firstReport->review_note);
        $this->assertSame($this->support->id, $firstReport->reviewed_by);
        $this->assertSame(TicketStatusSlug::InProgress->value, $this->ticket->fresh()->status->slug);
        $this->assertContains('Work needs changes', $this->notificationTitles($this->technician));
        $sendBackNotice = $this->technician->notifications()->where('data->title', 'Work needs changes')->firstOrFail()->data;
        $this->assertStringContainsString($firstReport->review_note, $sendBackNotice['message']);
        $this->assertSame($firstReport->review_note, $sendBackNotice['context']['review_reason']);

        $this->actingAs($this->technician)->postJson('/api/tickets/'.$this->ticket->id.'/work/start')->assertCreated();
        $secondReport = TicketWorkReport::where('ticket_assignment_id', $firstReport->ticket_assignment_id)->latest('id')->firstOrFail();
        $this->assertNotSame($firstReport->id, $secondReport->id);
        $this->assertNotNull($secondReport->work_started_at);
        $this->assertNull($secondReport->work_completed_at);

        $this->actingAs($this->technician)->postJson('/api/tickets/'.$this->ticket->id.'/work/complete', [
            'work_summary' => 'Inspected both ends and replaced the damaged connector.',
            'root_cause' => 'The connector latch was broken.',
        ])->assertOk();
        $this->assertSame(TicketStatusSlug::ItSupportReview->value, $this->ticket->fresh()->status->slug);
        $this->assertSame(2, TicketWorkReport::where('ticket_assignment_id', $secondReport->ticket_assignment_id)->count());
    }

    public function test_support_can_reassign_its_technician_handoff_and_history_is_preserved(): void
    {
        $this->assignTechnician();
        $this->actingAs($this->technician)->postJson('/api/tickets/'.$this->ticket->id.'/work/start')->assertCreated();

        $this->actingAs($this->support)->postJson('/api/tickets/'.$this->ticket->id.'/technician-assignment', [
            'assigned_to' => $this->otherTechnician->id,
            'note' => 'Continuing investigation with the hardware technician.',
        ])->assertCreated();

        $this->assertSame(2, $this->ticket->assignments()->count());
        $this->assertDatabaseHas('ticket_assignments', [
            'ticket_id' => $this->ticket->id,
            'assigned_to' => $this->technician->id,
        ]);
        $this->assertSame(TicketStatusSlug::InProgress->value, $this->ticket->fresh()->status->slug);

        $this->actingAs($this->otherTechnician)->postJson('/api/tickets/'.$this->ticket->id.'/work/start')->assertCreated();
        $newReport = TicketWorkReport::whereHas('assignment', fn ($query) => $query->where('assigned_to', $this->otherTechnician->id))->firstOrFail();
        $this->assertSame($this->otherTechnician->id, $newReport->started_by);
    }

    public function test_technician_assignment_rejects_non_technician_inactive_targets_and_unauthorized_support(): void
    {
        $this->actingAs($this->support)->postJson('/api/tickets/'.$this->ticket->id.'/technician-assignment', [
            'assigned_to' => $this->employee->id,
        ])->assertUnprocessable()->assertJsonPath('error.details.assigned_to.0', 'The selected assigned to is invalid.');

        $inactive = User::factory()->technician()->inactive()->create();
        $this->actingAs($this->support)->postJson('/api/tickets/'.$this->ticket->id.'/technician-assignment', [
            'assigned_to' => $inactive->id,
        ])->assertUnprocessable()->assertJsonPath('error.details.assigned_to.0', 'The selected assigned to is invalid.');
        $anotherTicket = $this->makeTicket($this->employee);
        $this->makeAssignment($anotherTicket, $this->otherTechnician, $this->admin);
        $this->actingAs($this->support)->postJson('/api/tickets/'.$anotherTicket->id.'/technician-assignment', [
            'assigned_to' => $this->otherTechnician->id,
        ])->assertForbidden();
    }

    public function test_reassignment_notifies_the_previous_support_assignee_once(): void
    {
        app(TicketAssignmentManager::class)->assign($this->ticket, $this->supportObserver, $this->support);

        $this->actingAs($this->admin)->postJson('/api/tickets/'.$this->ticket->id.'/technician-assignment', [
            'assigned_to' => $this->technician->id,
        ])->assertCreated();

        $this->assertNotificationCount($this->supportObserver, 'Ticket reassigned', 1);
        $this->assertNotificationCount($this->supportObserver, 'Technician assigned', 0);
        $this->assertNotificationCount($this->supportObserver, 'Ticket assigned to you', 1);
        $this->assertNotificationCount($this->technician, 'Ticket assigned to you', 1);
    }

    public function test_employee_cannot_trigger_any_technician_work_action_and_unrelated_users_get_no_notifications(): void
    {
        $inactiveSupport = User::factory()->support()->inactive()->create();
        $this->assignTechnician();
        $otherEmployee = User::factory()->create();
        $employeeNotificationsBefore = $this->employee->notifications()->count();

        foreach ([
            ['/work/start', []],
            ['/work/request-information', ['body' => 'Please send the serial number.']],
            ['/work/complete', ['work_summary' => 'Done', 'root_cause' => 'Cause']],
            ['/work/approve', []],
            ['/work/send-back', ['review_note' => 'Revise this']],
        ] as [$path, $payload]) {
            $this->actingAs($this->employee)->postJson('/api/tickets/'.$this->ticket->id.$path, $payload)->assertForbidden();
        }

        $this->assertSame(0, $otherEmployee->notifications()->count());
        $this->assertSame(0, $this->otherTechnician->notifications()->count());
        $this->assertSame($employeeNotificationsBefore, $this->employee->notifications()->count());
        $this->assertSame(0, $inactiveSupport->notifications()->count());
    }

    private function assignTechnician(): void
    {
        $this->actingAs($this->support)->postJson('/api/tickets/'.$this->ticket->id.'/technician-assignment', [
            'assigned_to' => $this->technician->id,
        ])->assertCreated();
    }

    /** @return list<string> */
    private function notificationTitles(User $user): array
    {
        return $user->notifications()->get()->map(fn ($notification) => $notification->data['title'])->all();
    }

    private function assertNotificationCount(User $user, string $title, int $expected): void
    {
        $this->assertSame($expected, $user->notifications()->where('data->title', $title)->count(), $title.' notification count for '.$user->name);
    }

    private function actAsFresh(User $user): static
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user, 'web');
    }
}
