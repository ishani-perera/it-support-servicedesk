<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsAuthScenario;
use Tests\Concerns\BuildsTicketFixtures;
use Tests\Concerns\SeedsMasterData;
use Tests\TestCase;

/**
 * Privilege-escalation and mass-assignment protection around users.
 */
class UserSecurityTest extends TestCase
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

    /* ----- own role ----- */

    public function test_employee_cannot_change_their_own_role_through_the_profile_endpoint(): void
    {
        $this->actingAs($this->employeeA)->patchJson('/profile', [
            'name' => 'Still Employee',
            'role' => 'admin',
            'is_active' => true,
            'department_id' => Department::where('name', 'IT')->value('id'),
            'email_verified_at' => now()->toDateTimeString(),
        ])->assertNoContent();

        $fresh = $this->employeeA->fresh();
        $this->assertSame('Still Employee', $fresh->name);          // allowed field applied
        $this->assertSame(UserRole::Employee, $fresh->role);         // privileged fields ignored
        $this->assertSame($this->employeeA->department_id, $fresh->department_id);
    }

    public function test_support_cannot_change_their_own_role_through_the_profile_endpoint(): void
    {
        $this->actingAs($this->supportOne)->patchJson('/profile', ['name' => 'Agent', 'role' => 'admin'])->assertNoContent();

        $this->assertSame(UserRole::Support, $this->supportOne->fresh()->role);
    }

    public function test_the_profile_endpoint_cannot_touch_email_password_or_status(): void
    {
        $before = $this->employeeA->fresh();

        $this->actingAs($this->employeeA)->patchJson('/profile', [
            'name' => 'Same Person',
            'email' => 'takeover@example.net',
            'password' => 'Hijacked-Passw0rd-1',
            'is_active' => false,
            'remember_token' => 'x',
        ])->assertNoContent();

        $after = $this->employeeA->fresh();
        $this->assertSame($before->email, $after->email);
        $this->assertSame($before->password, $after->password);
        $this->assertTrue($after->is_active);
    }

    public function test_profile_input_is_validated(): void
    {
        $this->actingAs($this->employeeA);

        $this->patchJson('/profile', ['name' => ''])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->patchJson('/profile', ['name' => str_repeat('a', 256)])->assertUnprocessable();
        $this->patchJson('/profile', ['name' => 'Ok', 'phone' => '<script>alert(1)</script>'])->assertUnprocessable()->assertJsonValidationErrors('phone');
    }

    public function test_employee_cannot_change_their_own_role_through_the_admin_endpoint(): void
    {
        $this->actingAs($this->employeeA)
            ->patchJson('/admin/users/'.$this->employeeA->id, ['role' => 'admin'])
            ->assertForbidden();

        $this->assertSame(UserRole::Employee, $this->employeeA->fresh()->role);
    }

    public function test_support_cannot_change_their_own_role_through_the_admin_endpoint(): void
    {
        $this->actingAs($this->supportOne)
            ->patchJson('/admin/users/'.$this->supportOne->id, ['role' => 'admin'])
            ->assertForbidden();

        $this->assertSame(UserRole::Support, $this->supportOne->fresh()->role);
    }

    /* ----- someone else's role ----- */

    public function test_non_admins_cannot_modify_another_users_role_status_or_department(): void
    {
        foreach ([$this->employeeA, $this->supportOne] as $actor) {
            $this->actAs($actor);

            $this->patchJson('/admin/users/'.$this->employeeB->id, ['role' => 'support'])->assertForbidden();
            $this->patchJson('/admin/users/'.$this->employeeB->id, ['is_active' => false])->assertForbidden();
            $this->patchJson('/admin/users/'.$this->employeeB->id, [
                'department_id' => Department::where('name', 'IT')->value('id'),
            ])->assertForbidden();
        }

        $fresh = $this->employeeB->fresh();
        $this->assertSame(UserRole::Employee, $fresh->role);
        $this->assertTrue($fresh->is_active);
        $this->assertSame($this->employeeB->department_id, $fresh->department_id);
    }

    /* ----- admin ----- */

    public function test_admin_can_manage_roles(): void
    {
        $this->actingAs($this->admin);

        $this->patchJson('/admin/users/'.$this->employeeB->id, ['role' => 'support'])
            ->assertOk()
            ->assertJsonPath('data.role', 'support');
        $this->assertSame(UserRole::Support, $this->employeeB->fresh()->role);

        $this->patchJson('/admin/users/'.$this->employeeB->id, ['role' => 'employee'])->assertOk();
        $this->assertSame(UserRole::Employee, $this->employeeB->fresh()->role);
    }

    public function test_admin_can_activate_deactivate_and_move_departments(): void
    {
        $this->actingAs($this->admin);
        $it = Department::where('name', 'IT')->firstOrFail();

        $this->patchJson('/admin/users/'.$this->employeeB->id, ['is_active' => false])->assertOk();
        $this->assertFalse($this->employeeB->fresh()->is_active);

        $this->patchJson('/admin/users/'.$this->employeeB->id, ['is_active' => true, 'department_id' => $it->id])->assertOk();
        $fresh = $this->employeeB->fresh();
        $this->assertTrue($fresh->is_active);
        $this->assertSame($it->id, $fresh->department_id);
    }

    public function test_admin_can_list_users_without_password_data(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/admin/users')->assertOk();

        $response->assertJsonPath('total', User::count());
        $this->assertStringNotContainsString('password', $response->getContent());
        $this->assertStringNotContainsString('remember_token', $response->getContent());
    }

    public function test_admin_cannot_change_their_own_role_or_deactivate_themselves(): void
    {
        $this->actingAs($this->admin);

        $this->patchJson('/admin/users/'.$this->admin->id, ['role' => 'employee'])->assertForbidden();
        $this->patchJson('/admin/users/'.$this->admin->id, ['is_active' => false])->assertForbidden();

        $fresh = $this->admin->fresh();
        $this->assertSame(UserRole::Admin, $fresh->role);
        $this->assertTrue($fresh->is_active);
    }

    public function test_an_admin_request_touching_a_forbidden_field_is_rejected_as_a_whole(): void
    {
        // department is allowed on self, role is not: nothing may be applied.
        $it = Department::where('name', 'IT')->firstOrFail();
        $originalDepartment = $this->admin->department_id;

        $this->actingAs($this->admin)
            ->patchJson('/admin/users/'.$this->admin->id, ['department_id' => $this->employeeA->department_id, 'role' => 'employee'])
            ->assertForbidden();

        $this->assertSame($originalDepartment, $this->admin->fresh()->department_id);
        $this->assertNotNull($it);
    }

    public function test_admin_input_is_validated(): void
    {
        $this->actingAs($this->admin);

        $this->patchJson('/admin/users/'.$this->employeeB->id, ['role' => 'superuser'])->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->patchJson('/admin/users/'.$this->employeeB->id, ['role' => ''])->assertUnprocessable();
        $this->patchJson('/admin/users/'.$this->employeeB->id, ['is_active' => 'maybe'])->assertUnprocessable();
        $this->patchJson('/admin/users/'.$this->employeeB->id, ['department_id' => 999999])->assertUnprocessable();
        $this->patchJson('/admin/users/'.$this->employeeB->id, [])->assertForbidden(); // nothing to update

        $this->assertSame(UserRole::Employee, $this->employeeB->fresh()->role);
    }

    public function test_the_admin_endpoint_ignores_fields_it_does_not_manage(): void
    {
        $before = $this->employeeB->fresh();

        $this->actingAs($this->admin)->patchJson('/admin/users/'.$this->employeeB->id, [
            'role' => 'support',
            'email' => 'changed@example.net',
            'password' => 'Hijacked-Passw0rd-1',
            'name' => 'Changed Name',
        ])->assertOk();

        $after = $this->employeeB->fresh();
        $this->assertSame($before->email, $after->email);
        $this->assertSame($before->password, $after->password);
        $this->assertSame($before->name, $after->name);
    }

    public function test_a_deactivated_user_loses_access_immediately(): void
    {
        $this->actingAs($this->admin)->patchJson('/admin/users/'.$this->employeeB->id, ['is_active' => false])->assertOk();

        $this->actAs($this->employeeB->fresh())->getJson('/tickets/'.$this->ticketB->id)->assertForbidden();
    }

    public function test_a_demoted_admin_loses_admin_access_immediately(): void
    {
        $second = User::factory()->admin()->create();

        $this->actingAs($this->admin)->patchJson('/admin/users/'.$second->id, ['role' => 'employee'])->assertOk();

        $this->actAs($second->fresh())->getJson('/admin/users')->assertForbidden();
    }

    /* ----- mass assignment at the model layer (defence behind the HTTP layer) ----- */

    public function test_privileged_columns_are_not_mass_assignable(): void
    {
        $user = new User;

        foreach (['role', 'is_active', 'email_verified_at', 'remember_token', 'id'] as $column) {
            $this->assertFalse($user->isFillable($column), "{$column} must not be fillable");
        }

        $created = User::create([
            'name' => 'Sneaky', 'email' => 'sneaky@example.com', 'password' => 'Sneaky-Passw0rd-1',
            'role' => 'admin', 'is_active' => false,
        ]);

        $fresh = $created->fresh();
        $this->assertSame(UserRole::Employee, $fresh->role, 'default role, never the injected one');
        $this->assertTrue($fresh->is_active);
    }

    public function test_there_is_no_public_registration_route(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'x', 'email' => 'x@example.com', 'password' => 'Aaaaaaaaaaaa1', 'password_confirmation' => 'Aaaaaaaaaaaa1', 'role' => 'admin'])
            ->assertStatus(404);
        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
    }
}
