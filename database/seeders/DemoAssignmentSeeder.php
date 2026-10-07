<?php

namespace Database\Seeders;

use App\Enums\TicketStatusSlug;
use App\Models\Ticket;
use App\Models\TicketStatus;
use App\Models\User;
use App\Services\TicketAssignmentService;
use Carbon\CarbonImmutable;
use Database\Seeders\Data\DemoTicketData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * DEVELOPMENT ONLY. Builds assignment HISTORY through TicketAssignmentService,
 * so every reassignment closes the previous row and respects the database rule
 * of one open assignment per ticket.
 *
 * Idempotent: tickets that already have any assignment row are skipped.
 */
class DemoAssignmentSeeder extends Seeder
{
    public function run(TicketAssignmentService $assignments): void
    {
        $users = User::withTrashed()->get()->keyBy('email');

        DB::transaction(function () use ($assignments, $users) {
            foreach (DemoTicketData::tickets() as $data) {
                if ($data['assignments'] === []) {
                    continue;
                }

                $ticket = Ticket::where('ticket_number', DemoTicketSeeder::ticketNumber($data['n']))->first()
                    ?? throw new RuntimeException('Demo tickets are missing — run DemoTicketSeeder first.');

                if ($ticket->assignments()->exists()) {
                    continue;
                }

                foreach ($data['assignments'] as [$to, $by, $at, $note]) {
                    $assignments->assign(
                        $ticket,
                        $users[$to] ?? throw new RuntimeException("Demo user [{$to}] is missing."),
                        $users[$by] ?? throw new RuntimeException("Demo user [{$by}] is missing."),
                        $note,
                        CarbonImmutable::parse($at, 'UTC'),
                    );
                }

                if ($data['status'] === TicketStatusSlug::ItSupportReview) {
                    $ticket->status_id = TicketStatus::forSlug(TicketStatusSlug::ItSupportReview)->getKey();
                    $ticket->save();
                }
            }
        });
    }
}
