<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatusSlug;
use App\Http\Requests\AssignTicketRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Requests\UpdateTicketStatusRequest;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\User;
use App\Services\TicketAssignmentManager;
use App\Services\TicketQueryService;
use App\Services\TicketService;
use App\Services\TicketWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
    public function index(Request $request, TicketQueryService $tickets): JsonResponse
    {
        $this->authorize('viewAny', Ticket::class);
        $filters = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(TicketStatusSlug::values())], 'priority_id' => ['sometimes', 'integer', 'exists:ticket_priorities,id'],
            'category_id' => ['sometimes', 'integer', 'exists:ticket_categories,id'], 'department_id' => ['sometimes', 'integer', 'exists:departments,id'],
            'assigned_to' => ['sometimes', 'integer', 'exists:users,id'], 'requester_id' => ['sometimes', 'integer', 'exists:users,id'],
            'from' => ['sometimes', 'date'], 'to' => ['sometimes', 'date', 'after_or_equal:from'], 'ticket_number' => ['sometimes', 'string', 'max:20'],
            'search' => ['sometimes', 'string', 'max:150'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $page = $tickets->paginate($request->user(), $filters);
        $page->through(fn (Ticket $ticket) => [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'title' => $ticket->title,
            'status' => $ticket->status->name,
            'priority' => $ticket->priority->name,
            'category' => $ticket->category->name,
            'department' => $ticket->department->name,
            'requester' => $ticket->user->name,
            'created_at' => $ticket->created_at,
        ]);

        return response()->json($page);
    }

    public function store(StoreTicketRequest $request, TicketService $tickets): JsonResponse
    {
        $ticket = $tickets->create($request->user(), $request->validated());

        return response()->json(['data' => ['id' => $ticket->id, 'ticket_number' => $ticket->ticket_number]], 201);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket, TicketService $tickets): JsonResponse
    {
        $this->authorize('update', $ticket);
        $tickets->update($ticket, $request->validated());

        return response()->json(['data' => ['id' => $ticket->id, 'ticket_number' => $ticket->ticket_number, 'title' => $ticket->title]]);
    }

    public function status(UpdateTicketStatusRequest $request, Ticket $ticket, TicketWorkflowService $workflow): JsonResponse
    {
        $workflow->transition($ticket, TicketStatusSlug::from($request->validated('status')), $request->user());

        return response()->json(['data' => ['id' => $ticket->id, 'status' => $ticket->fresh()->status->name]]);
    }

    public function assign(AssignTicketRequest $request, Ticket $ticket, TicketAssignmentManager $assignments): JsonResponse
    {
        $assignment = $assignments->assign($ticket, User::active()->findOrFail($request->validated('assigned_to')), $request->user(), $request->validated('note'));

        return response()->json(['data' => ['id' => $assignment->id, 'assigned_to' => $assignment->assigned_to]], 201);
    }

    public function unassign(Request $request, Ticket $ticket, TicketAssignmentManager $assignments): JsonResponse
    {
        $this->authorize('create', [TicketAssignment::class, $ticket]);
        $assignments->unassign($ticket, $request->user());

        return response()->json(['data' => ['id' => $ticket->id, 'current_assignment' => null]]);
    }

    public function show(Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);

        $ticket->load(['user', 'department', 'category', 'priority', 'status', 'currentAssignment.assignee']);
        $comments = $ticket->comments()->visibleTo(request()->user())->with('user')->oldest()->get();
        $currentAssignment = $ticket->currentAssignment;
        if ($currentAssignment) {
            $currentAssignment->setRelation('ticket', $ticket);
        }
        $canViewCurrentAssignment = $currentAssignment && request()->user()->can('view', $currentAssignment);
        $assignments = collect();
        if (request()->user()->isStaff()) {
            $assignments = $ticket->assignments()->with(['assignee', 'assigner'])->oldest('assigned_at')->get();
            $assignments->each(fn ($assignment) => $assignment->setRelation('ticket', $ticket));
            $assignments = $assignments->filter(fn ($item) => request()->user()->can('view', $item))->values();
        }

        return response()->json(['data' => [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'title' => $ticket->title,
            'description' => $ticket->description,
            'status' => $ticket->status->name,
            'priority' => $ticket->priority->name,
            'requester' => ['id' => $ticket->user->id, 'name' => $ticket->user->name],
            'department' => $ticket->department->name,
            'category' => $ticket->category->name,
            'current_assignment' => $canViewCurrentAssignment ? ['assignee' => $currentAssignment->assignee->name] : null,
            'comments' => $comments->map(fn ($comment) => ['id' => $comment->id, 'body' => $comment->body, 'is_internal' => $comment->is_internal, 'author' => $comment->user->name, 'created_at' => $comment->created_at]),
            'assignment_history' => $assignments->map(fn ($item) => ['assignee' => $item->assignee->name, 'assigner' => $item->assigner->name, 'assigned_at' => $item->assigned_at, 'unassigned_at' => $item->unassigned_at, 'note' => $item->note]),
            'created_at' => $ticket->created_at,
        ]]);
    }
}
