<?php

namespace Tests\Feature;

use App\Enums\TicketPriorityLevel;
use App\Enums\TicketStatusSlug;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Services\TicketQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAuthScenario;
use Tests\Concerns\BuildsTicketFixtures;
use Tests\Concerns\SeedsMasterData;
use Tests\TestCase;

class SupportDashboardTest extends TestCase
{
    use BuildsAuthScenario, BuildsTicketFixtures, RefreshDatabase, SeedsMasterData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildAuthScenario();
    }

    public function test_support_dashboard_is_role_gated_and_uses_real_authorized_statistics(): void
    {
        $this->ticketA->forceFill(['status_id' => TicketStatus::forSlug(TicketStatusSlug::InProgress)->id])->save();
        $this->ticketA2->forceFill(['status_id' => TicketStatus::forSlug(TicketStatusSlug::Open)->id])->save();

        $this->actingAs($this->supportOne)->get('/support/dashboard')->assertOk()
            ->assertSee('Assigned to me')->assertSee('Priority attention')->assertSee($this->ticketA->ticket_number)
            ->assertDontSee($this->ticketB->ticket_number);
        $stats = app(TicketQueryService::class)->supportStats($this->supportOne);
        $this->assertSame(1, $stats[0]['count']);
        $this->assertSame(3, $stats[1]['count']);
        $this->assertSame(1, $stats[2]['count']);
        $this->assertSame(4, $stats[5]['count']);
        $this->actingAs($this->employeeA)->get('/support/dashboard')->assertForbidden();
        $this->actingAs($this->admin)->get('/support/dashboard')->assertForbidden();
    }

    public function test_support_list_and_kanban_use_authorized_scope_search_filters_and_sorting(): void
    {
        $this->ticketA->update(['title' => 'Requester needle ticket', 'priority_id' => TicketPriority::forLevel(TicketPriorityLevel::Critical)->id]);
        $category = TicketCategory::firstOrFail();

        $this->actingAs($this->supportOne)->get('/tickets?search='.$this->employeeA->name.'&assignment=mine&category_id='.$category->id.'&sort=priority&view=table')
            ->assertOk()->assertSee($this->ticketA->ticket_number)->assertDontSee($this->ticketB->ticket_number);
        $ordered = app(TicketQueryService::class)->paginate($this->supportOne, ['sort' => 'priority']);
        $this->assertSame($this->ticketA->id, $ordered->first()->id);
        $this->actingAs($this->supportOne)->get('/tickets?priority_id='.$this->ticketA->priority_id.'&view=table')
            ->assertOk()->assertSee($this->ticketA->ticket_number)->assertDontSee($this->ticketA2->ticket_number);
        $this->actingAs($this->supportOne)->get('/tickets?status=open&assignment=unassigned&view=board')
            ->assertOk()->assertSee('Unassigned')->assertSee($this->ticketA2->ticket_number)->assertDontSee($this->ticketB->ticket_number);
        $this->actingAs($this->supportOne)->get('/tickets?assigned_to='.$this->supportTwo->id.'&view=board')
            ->assertOk()->assertSee($this->ticketB->ticket_number)->assertDontSee($this->ticketA->ticket_number);
        $this->actingAs($this->supportOne)->get('/tickets?view=board')->assertOk()
            ->assertSee($this->ticketB->ticket_number)->assertSee($this->supportTwo->name);
    }

    public function test_support_can_open_authorized_details_and_cannot_use_idor(): void
    {
        $this->actingAs($this->supportOne)->get('/tickets/'.$this->ticketA->id)->assertOk()
            ->assertSee($this->ticketA->title)->assertSee('Assignment history');
        $this->actingAs($this->supportOne)->get('/tickets/'.$this->ticketB->id)->assertOk()->assertSee($this->ticketB->title);
        $this->actingAs($this->supportOne)->getJson('/tickets/'.$this->ticketB->id)->assertOk()
            ->assertJsonPath('data.ticket_number', $this->ticketB->ticket_number)
            ->assertJsonFragment(['body' => $this->internalB->body]);
        $this->actingAs($this->employeeA)->get('/tickets/'.$this->ticketB->id)->assertForbidden();
    }

    public function test_status_changes_use_existing_workflow_and_reject_invalid_or_unauthorized_transitions(): void
    {
        $this->actingAs($this->supportOne)->patchJson('/tickets/'.$this->ticketA->id.'/status', ['status' => TicketStatusSlug::Assigned->value])->assertOk();
        $this->assertSame(TicketStatusSlug::Assigned->value, $this->ticketA->fresh()->status->slug);

        $this->actingAs($this->supportOne)->patchJson('/tickets/'.$this->ticketA->id.'/status', ['status' => TicketStatusSlug::Closed->value])->assertUnprocessable();
        $this->assertSame(TicketStatusSlug::Assigned->value, $this->ticketA->fresh()->status->slug);
        $this->actingAs($this->supportOne)->patchJson('/tickets/'.$this->ticketB->id.'/status', ['status' => TicketStatusSlug::InProgress->value])->assertForbidden();
        $this->actingAs($this->supportOne)->postJson('/tickets/'.$this->ticketB->id.'/assignments', ['assigned_to' => $this->supportOne->id])->assertForbidden();
    }

    public function test_support_can_claim_reassign_and_unassign_only_authorized_tickets_with_history(): void
    {
        $this->actingAs($this->supportOne)->postJson('/tickets/'.$this->ticketA2->id.'/assignments', ['assigned_to' => $this->supportOne->id])->assertCreated();
        $this->assertSame(TicketStatusSlug::Assigned->value, $this->ticketA2->fresh()->status->slug);

        $this->actingAs($this->supportOne)->deleteJson('/tickets/'.$this->ticketA2->id.'/assignments/current')->assertOk();
        $this->assertSame(1, $this->ticketA2->assignments()->whereNotNull('unassigned_at')->count());
        $this->assertSame(TicketStatusSlug::Open->value, $this->ticketA2->fresh()->status->slug);

        $this->actingAs($this->supportOne)->postJson('/tickets/'.$this->ticketA2->id.'/assignments', ['assigned_to' => $this->supportOne->id])->assertCreated();

        $this->actingAs($this->supportOne)->postJson('/tickets/'.$this->ticketA2->id.'/assignments', ['assigned_to' => $this->supportTwo->id])->assertCreated();
        $this->assertSame(3, $this->ticketA2->assignments()->count());
        $this->assertSame($this->supportTwo->id, $this->ticketA2->assignments()->current()->value('assigned_to'));

        $this->actingAs($this->supportOne)->postJson('/tickets/'.$this->ticketB->id.'/assignments', ['assigned_to' => $this->supportOne->id])->assertForbidden();
    }
}
