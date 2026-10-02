<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketCommentRequest;
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
    public function store(StoreTicketCommentRequest $request, Ticket $ticket): JsonResponse
    {
        $comment = $ticket->comments()->create([
            'user_id' => $request->user()->getKey(),
            'body' => $request->validated('body'),
            'is_internal' => $request->user()->isStaff() && $request->boolean('is_internal'),
        ]);

        return response()->json(['data' => ['id' => $comment->id, 'body' => $comment->body, 'is_internal' => $comment->is_internal]], 201);
    }

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
