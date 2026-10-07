<?php

use App\Enums\TicketStatusSlug;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $status = TicketStatusSlug::ItSupportReview;

        DB::table('ticket_statuses')->updateOrInsert(
            ['slug' => $status->value],
            [
                'name' => $status->label(),
                'description' => $status->description(),
                'color' => $status->color(),
                'sort_order' => $status->sortOrder(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        $statusId = DB::table('ticket_statuses')->where('slug', TicketStatusSlug::ItSupportReview->value)->value('id');
        if ($statusId && DB::table('tickets')->where('status_id', $statusId)->exists()) {
            throw new RuntimeException('Tickets are awaiting technician review. Move them to another status before rolling back this migration.');
        }

        DB::table('ticket_statuses')->where('slug', TicketStatusSlug::ItSupportReview->value)->delete();
    }
};
