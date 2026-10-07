<?php

namespace App\Http\Controllers\Api;

use App\Enums\TicketStatusSlug;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ApiTicketIndexRequest;
use App\Http\Requests\AssignTicketRequest;
use App\Http\Requests\StoreTicketAttachmentRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Requests\UpdateTicketStatusRequest;
use App\Http\Resources\TicketAttachmentResource;
use App\Http\Resources\TicketCommentResource;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\User;
use App\Services\TechnicianWorkflowService;
use App\Services\TicketAssignmentManager;
use App\Services\TicketAttachmentService;
use App\Services\TicketQueryService;
use App\Services\TicketService;
use App\Services\TicketWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    public function index(ApiTicketIndexRequest $request, TicketQueryService $tickets): JsonResponse
    {
        $page = $tickets->paginate($request->user(), $request->validated());

        return TicketResource::collection($page)->response();
    }

    public function store(StoreTicketRequest $request, TicketService $tickets, TicketAttachmentService $attachments): JsonResponse
    {
        $attributes = $request->validated();
        $file = $attributes['file'] ?? null;
        unset($attributes['file']);

        $ticket = DB::transaction(function () use ($request, $tickets, $attachments, $attributes, $file): Ticket {
            $ticket = $tickets->create($request->user(), $attributes);
            if ($file) {
                $this->authorize('uploadAttachment', $ticket);
                $attachments->store($ticket, $request->user(), $file);
            }

            return $ticket;
        });

        return (new TicketResource($ticket->load(['user', 'department', 'category', 'priority', 'status', 'currentAssignment.assignee'])))->response()->setStatusCode(201);
    }

    public function show(Ticket $ticket, TechnicianWorkflowService $technicianWorkflow): TicketResource
    {
        $this->authorize('view', $ticket);
        $ticket->load(['user', 'department', 'category', 'priority', 'status', 'currentAssignment.assignee']);
        $ticket->setRelation('workReports', $technicianWorkflow->reportsVisibleTo($ticket, request()->user()));

        return new TicketResource($ticket);
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket, TicketService $tickets): TicketResource
    {
        $this->authorize('update', $ticket);
        $tickets->update($ticket, $request->validated());
        $ticket->load(['user', 'department', 'category', 'priority', 'status', 'currentAssignment.assignee']);

        return new TicketResource($ticket);
    }

    public function status(UpdateTicketStatusRequest $request, Ticket $ticket, TicketWorkflowService $workflow): TicketResource
    {
        $validated = $request->validated();
        $workflow->transition($ticket, TicketStatusSlug::from($validated['status']), $request->user(), $validated['resolution'] ?? null);
        $ticket->refresh()->load(['user', 'department', 'category', 'priority', 'status', 'currentAssignment.assignee']);

        return new TicketResource($ticket);
    }

    public function assign(AssignTicketRequest $request, Ticket $ticket, TicketAssignmentManager $assignments): JsonResponse
    {
        $assignment = $assignments->assign(
            $ticket,
            User::active()->findOrFail($request->validated('assigned_to')),
            $request->user(),
            $request->validated('note'),
        );

        return response()->json(['data' => [
            'id' => $assignment->id,
            'ticket_id' => $ticket->id,
            'assigned_to' => $assignment->assigned_to,
            'assigned_at' => $assignment->assigned_at,
        ]], 201);
    }

    public function unassign(Request $request, Ticket $ticket, TicketAssignmentManager $assignments): JsonResponse
    {
        $this->authorize('create', [TicketAssignment::class, $ticket]);
        $assignments->unassign($ticket, $request->user());

        return response()->json(['data' => ['ticket_id' => $ticket->id, 'current_assignment' => null]]);
    }

    public function comments(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);
        $comments = $ticket->comments()->visibleTo($request->user())->with('user')->oldest()->paginate(25)->withQueryString();

        return TicketCommentResource::collection($comments)->response();
    }

    public function attachments(Request $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('view', $ticket);
        $query = $ticket->attachments()->with(['uploader', 'comment']);
        if (! $request->user()->isStaff() && ! $request->user()->isTechnician()) {
            $query->where(function ($builder): void {
                $builder->whereNull('comment_id')->orWhereHas('comment', fn ($comments) => $comments->where('is_internal', false));
            });
        }
        $page = $query->latest()->paginate(25)->withQueryString();
        $page->setCollection($page->getCollection()->filter(function ($attachment) use ($request, $ticket): bool {
            $attachment->setRelation('ticket', $ticket);

            return $request->user()->can('view', $attachment);
        })->values());

        return TicketAttachmentResource::collection($page)->response();
    }

    public function uploadAttachment(StoreTicketAttachmentRequest $request, Ticket $ticket, TicketAttachmentService $attachments): JsonResponse
    {
        $comment = isset($request->validated()['comment_id'])
            ? $ticket->comments()->findOrFail($request->validated('comment_id'))
            : null;
        $attachment = $attachments->store($ticket, $request->user(), $request->file('file'), $comment);
        $attachment->load('uploader');

        return (new TicketAttachmentResource($attachment))->response()->setStatusCode(201);
    }
}
