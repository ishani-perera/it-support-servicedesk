<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TicketNumberService
{
    /** Allocate a unique per-year sequence while the caller holds a transaction. */
    public function next(): string
    {
        $year = (int) now()->format('Y');

        DB::table('ticket_number_sequences')->insertOrIgnore([
            'year' => $year,
            'last_number' => 0,
        ]);

        $sequence = DB::table('ticket_number_sequences')->where('year', $year)->lockForUpdate()->first();
        $next = $sequence->last_number + 1;

        DB::table('ticket_number_sequences')->where('year', $year)->update(['last_number' => $next]);

        return sprintf('TKT-%04d-%06d', $year, $next);
    }
}
