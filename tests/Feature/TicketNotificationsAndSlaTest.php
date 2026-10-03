<?php

namespace Tests\Feature;

use App\Enums\TicketPriorityLevel;
use App\Models\TicketPriority;
use App\Notifications\TicketEventNotification;
use App\Services\TicketAssignmentManager;
use App\Services\TicketService;
use App\Services\TicketSlaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsAuthScenario;
use Tests\Concerns\BuildsTicketFixtures;
use Tests\Concerns\SeedsMasterData;
use Tests\TestCase;

class TicketNotificationsAndSlaTest extends TestCase
{
    use BuildsAuthScenario, BuildsTicketFixtures, RefreshDatabase, SeedsMasterData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildAuthScenario();
    }

    public function test_ticket_creation_notifies_support_but_not_the_actor(): void
    {
        $ticket = app(TicketService::class)->create($this->employeeA, [
            'category_id' => $this->ticketA->category_id,
            'priority_id' => $this->ticketA->priority_id,
            'title' => 'New printer request',
            'description' => 'Printer queue is unavailable.',
        ]);

        $this->assertSame(1, $this->supportOne->notifications()->count());
        $this->assertSame(1, $this->supportTwo->notifications()->count());
        $this->assertSame(0, $this->employeeA->notifications()->count());
        $notification = $this->supportOne->notifications()->firstOrFail();
        $this->assertSame('New ticket submitted', $notification->data['title']);
        $this->assertSame('A new support request needs triage.', $notification->data['message']);
        $this->assertSame($ticket->id, $notification->data['ticket_id']);
        $this->assertSame($ticket->ticket_number, $notification->data['ticket_number']);
        $this->assertArrayNotHasKey('description', $notification->data);
    }

    public function test_assignment_and_reassignment_notify_relevant_users_without_notifying_actor(): void
    {
        $manager = app(TicketAssignmentManager::class);
        $manager->assign($this->ticketQueueB, $this->supportOne, $this->supportOne);

        $this->assertSame(0, $this->supportTwo->notifications()->count());
        $this->assertSame(1, $this->employeeB->notifications()->count());
        $this->assertSame('Ticket status updated', $this->employeeB->notifications()->first()->data['title']);

        $manager->assign($this->ticketQueueB, $this->supportTwo, $this->admin);
        $this->assertSame(1, $this->supportTwo->notifications()->count());
        $this->assertSame('Ticket assigned to you', $this->supportTwo->notifications()->first()->data['title']);
        $this->assertSame(1, $this->supportOne->notifications()->count());
        $this->assertSame('Ticket reassigned', $this->supportOne->notifications()->first()->data['title']);
        $this->assertSame(0, $this->admin->notifications()->count());
    }

    public function test_public_replies_notify_the_other_side_and_internal_notes_do_not_notify(): void
    {
        $this->actAs($this->employeeA)->postJson('/tickets/'.$this->ticketA->id.'/comments', [
            'body' => 'Printer is back online.',
        ])->assertCreated();

        $this->assertSame(1, $this->supportOne->notifications()->count());
        $this->assertSame(0, $this->employeeA->notifications()->count());

        $this->actAs($this->supportOne)->postJson('/tickets/'.$this->ticketA->id.'/comments', [
            'body' => 'Public response from support.',
        ])->assertCreated();
        $employeeNotification = $this->employeeA->notifications()->firstOrFail();
        $this->assertSame('IT Support replied', $employeeNotification->data['title']);
        $this->assertSame('IT Support added a public response to your ticket.', $employeeNotification->data['message']);
        $this->assertArrayNotHasKey('body', $employeeNotification->data);

        $this->actAs($this->supportOne)->postJson('/tickets/'.$this->ticketA->id.'/comments', [
            'body' => 'Private investigation detail must never enter notification data.',
            'is_internal' => true,
        ])->assertCreated();
        $this->assertSame(1, $this->employeeA->notifications()->count());
        $this->assertSame(1, $this->supportOne->notifications()->count());
    }

    public function test_notifications_are_user_scoped_markable_and_ticket_authorized(): void
    {
        $this->actAs($this->employeeA)->postJson('/tickets/'.$this->ticketA->id.'/comments', ['body' => 'Requester update'])->assertCreated();
        $this->actAs($this->supportOne)->postJson('/tickets/'.$this->ticketA->id.'/comments', ['body' => 'Support response'])->assertCreated();
        $employeeNotification = $this->employeeA->notifications()->firstOrFail();
        $this->actAs($this->employeeA)->patch('/notifications/'.$employeeNotification->id.'/read')->assertRedirect();
        $this->assertNotNull($employeeNotification->fresh()->read_at);

        $this->actAs($this->supportOne)->postJson('/tickets/'.$this->ticketA->id.'/comments', ['body' => 'Another response'])->assertCreated();
        $supportNotification = $this->supportOne->notifications()->firstOrFail();
        $this->actAs($this->employeeB)->get('/notifications/'.$supportNotification->id.'/open')->assertNotFound();

        $this->actAs($this->employeeB)->get('/notifications')->assertOk()->assertDontSee($this->ticketA->ticket_number);
        $this->actAs($this->employeeA)->get('/notifications/'.$employeeNotification->id.'/open')->assertRedirect(route('tickets.show', $this->ticketA));

        $this->actAs($this->supportOne)->postJson('/tickets/'.$this->ticketA->id.'/comments', ['body' => 'Third response'])->assertCreated();
        $this->actAs($this->employeeA)->patch('/notifications/read-all')->assertRedirect();
        $this->assertSame(0, $this->employeeA->unreadNotifications()->count());
    }

    public function test_stale_or_forged_notification_links_are_not_visible_or_openable(): void
    {
        $this->employeeA->notify(new TicketEventNotification('Ticket update', 'A ticket changed.', $this->ticketB->id, $this->ticketB->ticket_number));
        $notification = $this->employeeA->notifications()->firstOrFail();

        $this->actAs($this->employeeA)->get('/notifications')->assertOk()->assertDontSee($this->ticketB->ticket_number);
        $this->actAs($this->employeeA)->get('/notifications/'.$notification->id.'/open')->assertNotFound();
    }

    public function test_sla_targets_follow_priority_and_detect_overdue_met_and_breached_timing(): void
    {
        $service = app(TicketSlaService::class);
        $now = Carbon::parse('2026-10-03 12:00:00');
        $ticket = $this->ticketA;
        $priority = TicketPriority::forLevel(TicketPriorityLevel::Critical);
        $ticket->priority_id = $priority->id;
        $ticket->created_at = Carbon::parse('2026-10-03 07:00:00');
        $ticket->resolved_at = null;
        $ticket->closed_at = null;
        $ticket->save();

        $sla = $service->evaluate($ticket, $now);
        $this->assertSame('2026-10-03 11:00:00', $sla['resolution_due_at']->format('Y-m-d H:i:s'));
        $this->assertSame('overdue', $sla['status']);
        $this->assertTrue($sla['is_overdue']);

        $ticket->resolved_at = Carbon::parse('2026-10-03 10:00:00');
        $ticket->closed_at = Carbon::parse('2026-10-03 12:30:00');
        $ticket->save();
        $sla = $service->evaluate($ticket, $now);
        $this->assertSame('met', $sla['status']);
        $this->assertEquals($ticket->resolved_at, $sla['completed_at']);

        $ticket->resolved_at = null;
        $ticket->closed_at = Carbon::parse('2026-10-03 11:30:00');
        $ticket->save();
        $this->assertSame('breached', $service->evaluate($ticket, $now)['status']);
    }

    public function test_sla_reporting_uses_the_same_calculation_and_employee_ticket_visibility(): void
    {
        $ticket = $this->ticketA;
        $ticket->priority_id = TicketPriority::forLevel(TicketPriorityLevel::High)->id;
        $ticket->created_at = now()->subDays(3);
        $ticket->resolved_at = null;
        $ticket->save();

        $metrics = app(TicketSlaService::class)->report(['from' => now()->subDays(5)->toDateString(), 'to' => now()->toDateString()]);
        $this->assertSame(4, $metrics['total']);
        $this->assertSame(1, $metrics['overdue']);
        $this->assertSame(75.0, $metrics['compliance']);
        $this->assertSame(1, $metrics['overdue_by_priority']['High']);
        $this->assertSame(1, $metrics['overdue_by_agent'][$this->supportOne->name]);

        $this->actAs($this->admin)->get('/admin/reports')->assertOk()->assertSee('SLA compliance')->assertSee('Overdue by priority');
        $this->actAs($this->employeeA)->get('/tickets/'.$this->ticketA->id)->assertOk()->assertSee('Resolution target')->assertSee('Overdue')
            ->assertDontSee('Response target');
        $this->actAs($this->employeeA)->get('/tickets/'.$this->ticketB->id)->assertForbidden();
    }
}
