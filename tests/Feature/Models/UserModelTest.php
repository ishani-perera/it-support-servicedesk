<?php

namespace Tests\Feature\Models;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsTicketFixtures;
use Tests\Concerns\SeedsMasterData;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use BuildsTicketFixtures;
    use RefreshDatabase;
    use SeedsMasterData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMasterData();
    }

    public function test_role_is_cast_to_the_enum_and_persisted_as_its_value(): void
    {
        $user = User::factory()->support()->create();

        $this->assertSame(UserRole::Support, $user->fresh()->role);
        $this->assertSame('support', $user->getRawOriginal('role'));
    }

    public function test_factory_default_is_an_active_employee(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UserRole::Employee, $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_role_helper_methods(): void
    {
        $employee = User::factory()->create();
        $support = User::factory()->support()->create();
        $admin = User::factory()->admin()->create();

        $this->assertTrue($employee->isEmployee());
        $this->assertFalse($employee->isStaff());

        $this->assertTrue($support->isSupport());
        $this->assertTrue($support->isStaff());
        $this->assertFalse($support->isAdmin());

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->isStaff());

        $this->assertTrue($support->hasRole(UserRole::Support, UserRole::Admin));
        $this->assertFalse($employee->hasRole(UserRole::Support, UserRole::Admin));
    }

    public function test_department_relationship(): void
    {
        $it = Department::where('name', 'IT')->firstOrFail();
        $user = User::factory()->inDepartment($it)->create();

        $this->assertTrue($user->department->is($it));
        $this->assertNull(User::factory()->create()->department);
    }

    public function test_active_scope(): void
    {
        $active = User::factory()->create();
        User::factory()->inactive()->create();

        $this->assertSame([$active->id], User::active()->pluck('id')->all());
    }

    public function test_role_scopes(): void
    {
        $employee = User::factory()->create();
        $support = User::factory()->support()->create();
        $admin = User::factory()->admin()->create();

        $this->assertSame([$employee->id], User::employees()->pluck('id')->all());
        $this->assertSame([$support->id], User::supportAgents()->pluck('id')->all());
        $this->assertSame([$admin->id], User::admins()->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$support->id, $admin->id], User::staff()->pluck('id')->all());
    }

    public function test_scopes_compose(): void
    {
        User::factory()->support()->inactive()->create();
        $available = User::factory()->support()->create();

        $this->assertSame([$available->id], User::supportAgents()->active()->pluck('id')->all());
    }

    public function test_initials_accessor(): void
    {
        $this->assertSame('PF', User::factory()->make(['name' => 'Priya Fernando'])->initials);
        $this->assertSame('CJ', User::factory()->make(['name' => '  chamara   de   jayasinghe '])->initials);
        $this->assertSame('M', User::factory()->make(['name' => 'Madonna'])->initials);
    }

    public function test_ticket_related_relationships(): void
    {
        $requester = $this->makeUser();
        $staff = $this->makeUser(UserRole::Support);
        $admin = $this->makeUser(UserRole::Admin);
        $ticket = $this->makeTicket($requester);

        $comment = $this->makeComment($ticket, $requester);
        $attachment = $this->makeAttachment($ticket, $requester, $comment);
        $assignment = $this->makeAssignment($ticket, $staff, $admin);

        $this->assertTrue($requester->tickets->contains($ticket));
        $this->assertTrue($requester->comments->contains($comment));
        $this->assertTrue($requester->attachments->contains($attachment));
        $this->assertTrue($staff->assignments->contains($assignment));
        $this->assertTrue($staff->assignedTickets->contains($ticket));
        $this->assertTrue($admin->createdAssignments->contains($assignment));
        $this->assertNotNull($staff->assignedTickets->first()->assignment->assigned_at);
    }

    public function test_sensitive_fields_are_not_mass_assignable_and_secrets_are_hidden(): void
    {
        $fillable = (new User)->getFillable();

        foreach (['role', 'is_active', 'email_verified_at', 'remember_token'] as $protected) {
            $this->assertNotContains($protected, $fillable);
        }
        $this->assertEqualsCanonicalizing(['password', 'remember_token'], (new User)->getHidden());
    }

    public function test_password_is_hashed_automatically(): void
    {
        $user = User::factory()->create(['password' => 'a-plain-password']);

        $this->assertStringStartsWith('$2y$', $user->getRawOriginal('password'));
    }
}
