<?php

namespace Tests\Feature\Database;

use App\Enums\TicketPriorityLevel;
use App\Enums\TicketStatusSlug;
use App\Models\Department;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsMasterData;
use Tests\TestCase;

class MasterDataSeedingTest extends TestCase
{
    use RefreshDatabase;
    use SeedsMasterData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMasterData();
    }

    public function test_departments_are_seeded(): void
    {
        $this->assertEqualsCanonicalizing(
            ['IT', 'Finance', 'HR', 'Sales', 'Marketing', 'Operations', 'Management'],
            Department::pluck('name')->all()
        );
    }

    public function test_categories_are_seeded(): void
    {
        $this->assertEqualsCanonicalizing(
            ['Hardware', 'Software', 'Network', 'Email', 'Account & Access', 'Security', 'Printer', 'System Issue', 'Other'],
            TicketCategory::pluck('name')->all()
        );
    }

    public function test_priorities_are_seeded_in_level_order_with_sla_values(): void
    {
        $priorities = TicketPriority::ordered()->get();

        $this->assertSame(['Low', 'Medium', 'High', 'Critical'], $priorities->pluck('name')->all());
        $this->assertSame([1, 2, 3, 4], $priorities->pluck('level')->all());

        foreach ($priorities as $priority) {
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $priority->color);
            $this->assertGreaterThan(0, $priority->sla_response_minutes);
            $this->assertGreaterThan($priority->sla_response_minutes, $priority->sla_resolution_minutes);
            $this->assertNotEmpty($priority->description);
        }

        // More urgent priorities must have tighter SLAs.
        $this->assertSame(
            $priorities->pluck('sla_resolution_minutes')->sortDesc()->values()->all(),
            $priorities->pluck('sla_resolution_minutes')->all()
        );
    }

    public function test_statuses_are_seeded_in_workflow_order(): void
    {
        $statuses = TicketStatus::ordered()->get();

        $this->assertSame(
            ['Open', 'Assigned', 'In Progress', 'Waiting for User', 'Resolved', 'Closed'],
            $statuses->pluck('name')->all()
        );
        $this->assertSame(
            ['open', 'assigned', 'in_progress', 'waiting_for_user', 'resolved', 'closed'],
            $statuses->pluck('slug')->all()
        );
        $this->assertSame(TicketStatusSlug::values(), $statuses->pluck('slug')->all());
    }

    public function test_lookup_helpers_resolve_every_enum_case(): void
    {
        foreach (TicketStatusSlug::cases() as $slug) {
            $this->assertSame($slug->value, TicketStatus::forSlug($slug)->slug);
        }
        foreach (TicketPriorityLevel::cases() as $level) {
            $this->assertSame($level->value, TicketPriority::forLevel($level)->level);
        }
    }

    public function test_all_seeded_master_data_is_active(): void
    {
        $this->assertSame(0, Department::where('is_active', false)->count());
        $this->assertSame(0, TicketCategory::where('is_active', false)->count());
        $this->assertSame(0, TicketPriority::where('is_active', false)->count());
        $this->assertSame(0, TicketStatus::where('is_active', false)->count());
    }

    public function test_seeding_is_idempotent(): void
    {
        $this->seedMasterData();

        $this->assertSame(7, Department::count());
        $this->assertSame(9, TicketCategory::count());
        $this->assertSame(4, TicketPriority::count());
        $this->assertSame(6, TicketStatus::count());
    }

    public function test_master_data_seeders_do_not_create_user_accounts(): void
    {
        $this->assertSame(0, User::count());
    }
}
