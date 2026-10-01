<?php

namespace Database\Seeders;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Data\DemoTicketData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * DEVELOPMENT ONLY. Creates ticket conversations, including internal IT notes
 * (is_internal = true, always authored by IT staff).
 *
 * Idempotent: tickets that already have comments are skipped.
 * No attachment records or files are created (secure uploads: later phase).
 */
class DemoCommentSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::withTrashed()->get()->keyBy('email');

        DB::transaction(function () use ($users) {
            foreach (DemoTicketData::tickets() as $data) {
                if ($data['comments'] === []) {
                    continue;
                }

                $ticket = Ticket::where('ticket_number', DemoTicketSeeder::ticketNumber($data['n']))->first()
                    ?? throw new RuntimeException('Demo tickets are missing — run DemoTicketSeeder first.');

                if ($ticket->comments()->exists()) {
                    continue;
                }

                foreach ($data['comments'] as $row) {
                    [$author, $at, $body] = $row;
                    $internal = $row[3] ?? false;

                    $comment = new TicketComment([
                        'ticket_id' => $ticket->id,
                        'user_id' => ($users[$author] ?? throw new RuntimeException("Demo user [{$author}] is missing."))->id,
                        'body' => $body,
                        'is_internal' => $internal,
                    ]);
                    $comment->created_at = CarbonImmutable::parse($at, 'UTC');
                    $comment->updated_at = $comment->created_at;
                    $comment->save();
                }
            }
        });
    }
}
