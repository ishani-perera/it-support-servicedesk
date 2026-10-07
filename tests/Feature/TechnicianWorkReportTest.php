<?php

namespace Tests\Feature;

use App\Enums\TechnicianWorkReviewStatus;
use App\Models\Department;
use App\Models\User;
use App\Services\TicketAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTicketFixtures;
use Tests\Concerns\SeedsMasterData;
use Tests\TestCase;

class TechnicianWorkReportTest extends TestCase
{
    use BuildsTicketFixtures;
    use RefreshDatabase;
    use SeedsMasterData;

    public function test_work_report_links_to_assignment_technician_and_it_support_review(): void
    {
        $this->seedMasterData();
        $departmentId = Department::where('name', 'IT')->value('id');
        $technician = User::factory()->technician()->inDepartment($departmentId)->create();
        $reviewer = User::factory()->support()->inDepartment($departmentId)->create();
        $ticket = $this->makeTicket();
        $assignment = app(TicketAssignmentService::class)->assign(
            $ticket,
            $technician,
            User::factory()->admin()->create(),
        );

        $startedAt = now()->subHour();
        $reviewedAt = now();
        $report = $assignment->workReports()->create([
            'work_summary' => 'Replaced the failing network adapter.',
            'root_cause' => 'Adapter hardware failure.',
            'technician_notes' => 'Device passed connectivity checks.',
            'review_note' => 'Verified and approved.',
        ]);
        $report->started_by = $technician->id;
        $report->work_started_at = $startedAt;
        $report->work_completed_at = $reviewedAt;
        $report->review_status = TechnicianWorkReviewStatus::Approved;
        $report->reviewed_by = $reviewer->id;
        $report->reviewed_at = $reviewedAt;
        $report->save();

        $this->assertTrue($report->assignment->is($assignment));
        $this->assertTrue($report->startedBy->is($technician));
        $this->assertTrue($report->reviewer->is($reviewer));
        $this->assertSame(TechnicianWorkReviewStatus::Approved, $report->review_status);
        $this->assertTrue($ticket->workReports()->first()->is($report));
        $this->assertTrue($technician->startedWorkReports()->first()->is($report));
        $this->assertTrue($reviewer->reviewedWorkReports()->first()->is($report));
    }

    public function test_deleting_ticket_cascades_its_work_reports_through_assignment_history(): void
    {
        $this->seedMasterData();
        $departmentId = Department::where('name', 'IT')->value('id');
        $technician = User::factory()->technician()->inDepartment($departmentId)->create();
        $ticket = $this->makeTicket();
        $assignment = app(TicketAssignmentService::class)->assign(
            $ticket,
            $technician,
            User::factory()->admin()->create(),
        );
        $report = $assignment->workReports()->create();

        $ticket->delete();

        $this->assertDatabaseMissing('ticket_work_reports', ['id' => $report->id]);
    }
}
