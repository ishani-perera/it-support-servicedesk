<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $request->user()?->can('view', $this->resource)) {
            return [];
        }

        return [
            'id' => $this->id,
            'ticket_id' => $this->ticket_id,
            'body' => $this->body,
            'is_internal' => (bool) $this->is_internal,
            'author' => new UserResource($this->whenLoaded('user')),
            'attachments' => TicketAttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at,
        ];
    }
}
