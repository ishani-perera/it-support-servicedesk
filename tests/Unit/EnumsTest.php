<?php

namespace Tests\Unit;

use App\Enums\TicketPriorityLevel;
use App\Enums\TicketStatusSlug;
use App\Enums\UserRole;
use PHPUnit\Framework\TestCase;

class EnumsTest extends TestCase
{
    public function test_user_role_values_include_existing_roles_and_technician(): void
    {
        $this->assertSame(['employee', 'support', 'admin', 'technician'], UserRole::values());
    }

    public function test_user_role_helpers(): void
    {
        $this->assertTrue(UserRole::Employee->isEmployee());
        $this->assertFalse(UserRole::Employee->isStaff());

        $this->assertTrue(UserRole::Support->isSupport());
        $this->assertTrue(UserRole::Support->isStaff());
        $this->assertFalse(UserRole::Support->isAdmin());

        $this->assertTrue(UserRole::Admin->isAdmin());
        $this->assertTrue(UserRole::Admin->isStaff());
        $this->assertFalse(UserRole::Admin->isEmployee());

        $this->assertEqualsCanonicalizing([UserRole::Support, UserRole::Admin], UserRole::staff());
        $this->assertTrue(UserRole::Technician->isTechnician());
        $this->assertFalse(UserRole::Technician->isStaff());
        $this->assertTrue(UserRole::Technician->canBeAssignedTickets());
        $this->assertEqualsCanonicalizing(
            [UserRole::Support, UserRole::Admin, UserRole::Technician],
            UserRole::ticketAssignees()
        );
    }

    public function test_exactly_one_role_helper_is_true_for_every_role(): void
    {
        foreach (UserRole::cases() as $role) {
            $this->assertSame(1, (int) $role->isEmployee() + (int) $role->isSupport() + (int) $role->isAdmin() + (int) $role->isTechnician());
            $this->assertNotSame('', $role->label());
        }
    }

    public function test_status_slugs_match_the_required_workflow_and_are_consistent(): void
    {
        $this->assertSame(
            ['open', 'assigned', 'in_progress', 'waiting_for_user', 'resolved', 'it_support_review', 'closed'],
            TicketStatusSlug::values()
        );

        $orders = array_map(fn (TicketStatusSlug $s) => $s->sortOrder(), TicketStatusSlug::cases());
        $this->assertSame($orders, array_values(array_unique($orders)), 'sort orders must be unique');
        $this->assertSame($orders, collect($orders)->sort()->values()->all(), 'cases are declared in workflow order');

        foreach (TicketStatusSlug::cases() as $status) {
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $status->color());
            $this->assertNotSame('', $status->label());
            $this->assertNotSame('', $status->description());
        }
    }

    public function test_priority_levels_are_ordered_and_slas_tighten_with_urgency(): void
    {
        $this->assertSame([1, 2, 3, 4], TicketPriorityLevel::values());

        $cases = TicketPriorityLevel::cases();
        for ($i = 1; $i < count($cases); $i++) {
            $this->assertLessThan($cases[$i - 1]->slaResponseMinutes(), $cases[$i]->slaResponseMinutes());
            $this->assertLessThan($cases[$i - 1]->slaResolutionMinutes(), $cases[$i]->slaResolutionMinutes());
        }

        foreach ($cases as $level) {
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $level->color());
            $this->assertLessThan($level->slaResolutionMinutes(), $level->slaResponseMinutes());
        }
    }
}
