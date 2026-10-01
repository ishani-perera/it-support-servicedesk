<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\JsonResponse;

/**
 * Phase 03 AUTHORIZATION BOUNDARY — deliberately minimal.
 *
 * Route model binding loads the ticket, but binding is NOT authorization: the
 * Policy decides. Responses are small JSON documents (no HTML UI, no API
 * resource layer); Phase 04 / the UI phases replace the bodies and keep the
 * authorize() calls.
 */
class TicketController extends Controller
{
    public function show(Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $ticket->load(['status', 'priority']);

        return response()->json(['data' => [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'title' => $ticket->title,
            'description' => $ticket->description,
            'status' => $ticket->status->name,
            'priority' => $ticket->priority->name,
            'created_at' => $ticket->created_at,
        ]]);
    }
}
