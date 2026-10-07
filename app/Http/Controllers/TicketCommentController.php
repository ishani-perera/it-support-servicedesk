<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketCommentRequest;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Services\TicketNotificationService;
use App\Services\TicketWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Nested route binding and the comment policy both verify the comment's real
 * parent ticket before it is exposed.
 */
class TicketCommentController extends Controller
{
    public function store(StoreTicketCommentRequest $request, Ticket $ticket, TicketNotificationService $notifications, TicketWorkflowService $workflow): JsonResponse|RedirectResponse
    {
        $comment = DB::transaction(function () use ($request, $ticket, $notifications, $workflow) {
            $comment = $ticket->comments()->create([
                'user_id' => $request->user()->getKey(),
                'body' => $request->validated('body'),
                'is_internal' => ($request->user()->isStaff() || $request->user()->isTechnician()) && $request->boolean('is_internal'),
            ]);

            if (! $comment->is_internal) {
                if ($request->user()->isEmployee()) {
                    $workflow->resumeForRequesterReply($ticket, $request->user());
                }
                $notifications->publicCommentAdded($ticket, $request->user());
            }

            return $comment;
        });

        if ($request->input('_html_form') === '1') {
            return redirect()->route('tickets.show', $ticket)->with('status', 'Your comment has been added.');
        }

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
