<?php

namespace Tests\Feature;

use App\Enums\TicketPriorityLevel;
use App\Enums\TicketStatusSlug;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAuthScenario;
use Tests\Concerns\BuildsTicketFixtures;
use Tests\Concerns\SeedsMasterData;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use BuildsAuthScenario, BuildsTicketFixtures, RefreshDatabase, SeedsMasterData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildAuthScenario();
    }

    public function test_admin_dashboard_is_db_backed_and_other_roles_are_denied(): void
    {
        $response = $this->actAs($this->admin)->get('/admin/')->assertOk()
            ->assertSee('ServiceDesk administration')->assertSee('Recent tickets')->assertSee('Recent account activity');
        $this->assertDatabaseCount('users', 5);

        $this->actAs($this->employeeA)->get('/admin/')->assertForbidden();
        $this->actAs($this->supportOne)->get('/admin/')->assertForbidden();
    }

    public function test_user_list_search_filters_pagination_and_sensitive_fields(): void
    {
        $this->actAs($this->admin)->get('/admin/users/create')->assertOk()->assertSee('Create user');
        $this->actAs($this->admin)->get('/admin/users/'.$this->employeeA->id)->assertOk()->assertSee($this->employeeA->email);
        $this->actAs($this->admin)->get('/admin/users/'.$this->employeeA->id.'/edit')->assertOk()->assertSee('Edit user');
        $this->actAs($this->admin)->get('/admin/users?search='.$this->employeeA->name.'&role=employee&department_id='.$this->employeeA->department_id.'&active=active')
            ->assertOk()->assertSee($this->employeeA->name)->assertDontSee($this->supportOne->name)
            ->assertDontSee($this->employeeA->getAuthPassword());

        $this->actAs($this->admin)->getJson('/admin/users')->assertOk()
            ->assertJsonMissingPath('data.0.password')->assertJsonMissingPath('data.0.remember_token');
    }

    public function test_user_create_validation_and_create_update_lockout_protection(): void
    {
        $this->actAs($this->admin)->post('/admin/users', [
            'name' => 'No Department', 'email' => 'nodept@example.test', 'password' => 'Short1',
            'password_confirmation' => 'Short1', 'role' => UserRole::Support->value,
        ])->assertSessionHasErrors(['password', 'department_id']);

        $this->actAs($this->admin)->post('/admin/users', [
            'name' => 'New Support', 'email' => 'new-support@example.test', 'password' => 'StrongPassword1',
            'password_confirmation' => 'StrongPassword1', 'role' => UserRole::Support->value,
            'department_id' => Department::where('name', 'IT')->value('id'), 'employee_id' => 'NEW-SUP-1',
        ])->assertRedirect();
        $created = User::where('email', 'new-support@example.test')->firstOrFail();
        $this->assertSame(UserRole::Support, $created->role);
        $this->assertNotSame('StrongPassword1', $created->getAuthPassword());

        $this->actAs($this->admin)->patchJson('/admin/users/'.$this->admin->id, ['role' => UserRole::Employee->value])->assertForbidden();
        $this->actAs($this->admin)->patchJson('/admin/users/'.$this->admin->id, ['is_active' => false])->assertForbidden();
        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_user_update_activation_and_non_admin_denial(): void
    {
        $this->actAs($this->admin)->patchJson('/admin/users/'.$this->employeeB->id, [
            'name' => 'Updated Employee', 'department_id' => $this->employeeB->department_id,
        ])->assertOk();
        $this->actAs($this->admin)->patch('/admin/users/'.$this->employeeB->id.'/status', ['is_active' => '0'])->assertRedirect();
        $this->assertFalse($this->employeeB->fresh()->is_active);
        $this->actAs($this->supportOne)->patchJson('/admin/users/'.$this->employeeA->id, ['name' => 'No'])->assertForbidden();
    }

    public function test_department_and_category_management_are_admin_only_and_non_destructive(): void
    {
        $this->actAs($this->admin)->get('/admin/departments')->assertOk()->assertSee('Departments');
        $this->actAs($this->admin)->get('/admin/departments/create')->assertOk()->assertSee('Create department');
        $this->actAs($this->admin)->get('/admin/categories/create')->assertOk()->assertSee('Create ticket category');
        $this->actAs($this->admin)->post('/admin/departments', ['name' => 'Research', 'description' => 'Research group'])->assertRedirect();
        $department = Department::where('name', 'Research')->firstOrFail();
        $this->actAs($this->admin)->get('/admin/departments/'.$department->id.'/edit')->assertOk();
        $this->actAs($this->admin)->patch('/admin/departments/'.$department->id, [
            'name' => 'Research', 'description' => 'Updated', 'is_active' => '0',
        ])->assertRedirect();
        $this->assertFalse($department->fresh()->is_active);

        $this->actAs($this->admin)->post('/admin/categories', ['name' => 'Facilities', 'description' => 'Building services'])->assertRedirect();
        $category = TicketCategory::where('name', 'Facilities')->firstOrFail();
        $this->actAs($this->admin)->get('/admin/categories/'.$category->id.'/edit')->assertOk();
        $this->actAs($this->admin)->patch('/admin/categories/'.$category->id, [
            'name' => 'Facilities', 'description' => 'Updated', 'is_active' => '0',
        ])->assertRedirect();
        $this->assertFalse($category->fresh()->is_active);

        $this->actAs($this->employeeA)->get('/admin/departments')->assertForbidden();
        $this->actAs($this->supportOne)->post('/admin/categories', ['name' => 'Forbidden'])->assertForbidden();
        $this->assertDatabaseHas('ticket_categories', ['id' => $this->ticketA->category_id]);
    }

    public function test_priority_settings_are_limited_and_status_workflow_remains_read_only(): void
    {
        $priority = TicketPriority::forLevel(TicketPriorityLevel::High);
        $this->actAs($this->admin)->get('/admin/priorities')->assertOk();
        $this->actAs($this->admin)->get('/admin/priorities/'.$priority->id.'/edit')->assertOk();
        $this->actAs($this->admin)->patch('/admin/priorities/'.$priority->id, [
            'description' => 'Updated high impact', 'color' => '#123456', 'sla_response_minutes' => 50,
            'sla_resolution_minutes' => 400, 'is_active' => '1', 'level' => 99, 'name' => 'Urgent',
        ])->assertRedirect();
        $this->assertSame(3, $priority->fresh()->level);
        $this->assertSame('High', $priority->fresh()->name);
        $this->assertSame('#123456', $priority->fresh()->color);

        $this->actAs($this->admin)->get('/admin/statuses')->assertOk()->assertSee('read-only');
        $this->assertSame(TicketStatusSlug::Open->value, TicketStatus::forSlug(TicketStatusSlug::Open)->slug);
    }

    public function test_reports_authorization_filter_aggregates_and_csv_output(): void
    {
        $ticket = $this->ticketA;
        $ticket->created_at = now()->startOfDay()->subDays(20);
        $ticket->save();
        $ticket->status()->associate(TicketStatus::forSlug(TicketStatusSlug::Resolved));
        $ticket->resolved_at = now()->subDays(2);
        $ticket->save();

        $this->actAs($this->admin)->get('/admin/reports')->assertOk()
            ->assertSee($ticket->ticket_number)->assertSee('Tickets by status')->assertSee('Resolved');

        $emptyFrom = now()->addDays(1)->toDateString();
        $emptyTo = now()->addDays(2)->toDateString();
        $this->actAs($this->admin)->get('/admin/reports?from='.$emptyFrom.'&to='.$emptyTo)
            ->assertOk()->assertSee('Tickets by status')->assertSee('No ticket data for this date range.');
        $this->actAs($this->admin)->get('/admin/reports?from=2026-10-20&to=2026-10-01')->assertSessionHasErrors('to');
        $this->actAs($this->employeeA)->get('/admin/reports')->assertForbidden();
        $this->actAs($this->supportOne)->get('/admin/reports/export')->assertForbidden();

        $ticket->category->update(['name' => '=HYPERLINK("example.test")']);

        $csv = $this->actAs($this->admin)->get('/admin/reports/export?from='.now()->subDays(30)->toDateString().'&to='.now()->toDateString())->assertOk();
        $csvContent = $csv->streamedContent();
        $csvLines = preg_split('/\r?\n/', trim($csvContent));
        $this->assertSame(['Ticket number', 'Status', 'Priority', 'Category', 'Department', 'Current support agent', 'Created at', 'Resolved at', 'Closed at'], str_getcsv($csvLines[0]));
        $this->assertStringContainsString($ticket->ticket_number, $csvContent);
        $this->assertStringContainsString("'=HYPERLINK", $csvContent);
        $this->assertStringNotContainsString($this->employeeA->email, $csvContent);
    }

    public function test_user_and_master_data_ids_do_not_bypass_admin_authorization(): void
    {
        $this->actAs($this->admin)->get('/admin/users/999999')->assertNotFound();
        $this->actAs($this->employeeA)->get('/admin/users/'.$this->employeeB->id)->assertForbidden();
        $this->actAs($this->supportOne)->patch('/admin/departments/'.Department::first()->id, ['name' => 'No'])->assertForbidden();
        $this->actAs($this->supportTwo)->get('/admin/priorities')->assertForbidden();
    }
}
