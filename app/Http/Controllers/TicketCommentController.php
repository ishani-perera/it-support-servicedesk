<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Http\JsonResponse;

/**
 * Phase 03 authorization boundary (see TicketController). The route uses
 * scoped bindings, so a comment that does not belong to {ticket} is a 404;
 * the policy independently checks the comment's REAL parent ticket anyway.
 */
class TicketCommentController extends Controller
{
    public function show(Ticket $ticket, TicketComment $comment): JsonResponse
    {
        $this->authorize('view', $comment);

        return response()->json(['data' => [
            'id' => $comment->id,
            'ticket_id' => $comment->ticket_id,
            'body' => $comment->body,
            'is_internal' => $comment->is_internal,
            'created_at' => $comment->created_at,
        ]]);
    }
}
