<?php

namespace Database\Seeders;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Data\DemoTicketData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * DEVELOPMENT ONLY. Creates the demo tickets (without assignments/comments —
 * see DemoAssignmentSeeder and DemoCommentSeeder).
 *
 * Idempotent: a ticket is matched on its ticket_number and, if present, left
 * untouched. Ticket numbers are fixed here because the concurrency-safe
 * generator belongs to a later phase.
 */
class DemoTicketSeeder extends Seeder
{
    public static function ticketNumber(int $sequence): string
    {
        return sprintf('INC-2026-%06d', $sequence);
    }

    public function run(): void
    {
        $users = User::withTrashed()->get()->keyBy('email');
        $categories = TicketCategory::withTrashed()->pluck('id', 'name');
        $priorities = TicketPriority::withTrashed()->pluck('id', 'level');
        $statuses = TicketStatus::pluck('id', 'slug');

        DB::transaction(function () use ($users, $categories, $priorities, $statuses) {
            foreach (DemoTicketData::tickets() as $data) {
                $number = self::ticketNumber($data['n']);

                if (Ticket::where('ticket_number', $number)->exists()) {
                    continue;
                }

                $requester = $users[$data['by']]
                    ?? throw new RuntimeException("Demo user [{$data['by']}] is missing — run DemoUserSeeder first.");

                $created = CarbonImmutable::parse($data['created'], 'UTC');
                $resolved = isset($data['resolved']) ? CarbonImmutable::parse($data['resolved'], 'UTC') : null;
                $closed = isset($data['closed']) ? CarbonImmutable::parse($data['closed'], 'UTC') : null;

                $ticket = new Ticket([
                    'user_id' => $requester->id,
                    // The requester's department is captured on the ticket at creation.
                    'department_id' => $requester->department_id,
                    'category_id' => $categories[$data['category']]
                        ?? throw new RuntimeException("Category [{$data['category']}] is missing."),
                    'priority_id' => $priorities[$data['priority']->value],
                    'status_id' => $statuses[$data['status']->value],
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'resolution' => $data['resolution'] ?? null,
                ]);

                // System-managed columns are not fillable: set them explicitly.
                $ticket->ticket_number = $number;
                $ticket->resolved_at = $resolved;
                $ticket->closed_at = $closed;
                $ticket->created_at = $created;
                $ticket->updated_at = $closed ?? $resolved ?? $created;
                $ticket->save();
            }
        });
    }
}
