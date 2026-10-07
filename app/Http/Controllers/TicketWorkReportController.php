<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveTechnicianWorkRequest;
use App\Http\Requests\AssignTechnicianRequest;
use App\Http\Requests\CompleteTechnicianWorkRequest;
use App\Http\Requests\RequestEmployeeInformationRequest;
use App\Http\Requests\SendBackTechnicianWorkRequest;
use App\Http\Requests\StartTechnicianWorkRequest;
use App\Http\Resources\TicketWorkReportResource;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TechnicianWorkflowService;
use App\Services\TicketAssignmentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketWorkReportController extends Controller
{
    public function assignTechnician(AssignTechnicianRequest $request, Ticket $ticket, TicketAssignmentManager $assignments): JsonResponse
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

    public function index(Request $request, Ticket $ticket, TechnicianWorkflowService $workflow): JsonResponse
    {
        $this->authorize('view', $ticket);
        $reports = $workflow->reportsVisibleTo($ticket, $request->user());

        return TicketWorkReportResource::collection($reports)->response();
    }

    public function start(StartTechnicianWorkRequest $request, Ticket $ticket, TechnicianWorkflowService $workflow): TicketWorkReportResource
    {
        return new TicketWorkReportResource($workflow->startWork($ticket, $request->user()));
    }

    public function requestInformation(RequestEmployeeInformationRequest $request, Ticket $ticket, TechnicianWorkflowService $workflow): JsonResponse
    {
        $comment = $workflow->requestInformation($ticket, $request->user(), $request->validated('body'));

        return response()->json(['data' => [
            'comment_id' => $comment->id,
            'status' => $ticket->fresh()->status->slug,
        ]], 201);
    }

    public function complete(CompleteTechnicianWorkRequest $request, Ticket $ticket, TechnicianWorkflowService $workflow): TicketWorkReportResource
    {
        return new TicketWorkReportResource($workflow->completeWork($ticket, $request->user(), $request->validated()));
    }

    public function approve(ApproveTechnicianWorkRequest $request, Ticket $ticket, TechnicianWorkflowService $workflow): TicketWorkReportResource
    {
        return new TicketWorkReportResource($workflow->approveWork($ticket, $request->user(), $request->validated('review_note')));
    }

    public function sendBack(SendBackTechnicianWorkRequest $request, Ticket $ticket, TechnicianWorkflowService $workflow): TicketWorkReportResource
    {
        return new TicketWorkReportResource($workflow->sendBackWork($ticket, $request->user(), $request->validated('review_note')));
    }
}
