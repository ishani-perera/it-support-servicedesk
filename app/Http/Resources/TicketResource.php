<?php

namespace App\Http\Resources;

use App\Services\TicketSlaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'title' => $this->title,
            'description' => $this->when($request->routeIs('api.tickets.show'), $this->description),
            'status' => $this->whenLoaded('status', fn () => ['id' => $this->status->id, 'name' => $this->status->name, 'slug' => $this->status->slug]),
            'priority' => $this->whenLoaded('priority', fn () => ['id' => $this->priority->id, 'name' => $this->priority->name, 'level' => $this->priority->level]),
            'category' => $this->whenLoaded('category', fn () => ['id' => $this->category->id, 'name' => $this->category->name]),
            'department' => $this->whenLoaded('department', fn () => ['id' => $this->department->id, 'name' => $this->department->name]),
            'requester' => new UserResource($this->whenLoaded('user')),
            'current_assignee' => $this->whenLoaded('currentAssignment', fn () => $this->currentAssignment?->assignee ? new UserResource($this->currentAssignment->assignee) : null),
            'resolved_at' => $this->resolved_at,
            'closed_at' => $this->closed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if ($request->user()) {
            $data['sla'] = app(TicketSlaService::class)->evaluate($this->resource);
        }

        $data['comments'] = TicketCommentResource::collection($this->whenLoaded('comments'));
        $data['attachments'] = TicketAttachmentResource::collection($this->whenLoaded('attachments'));

        return $data;
    }
}
