<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketWorkReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $request->user()?->can('view', $this->resource)) {
            return [];
        }

        return [
            'id' => $this->id,
            'assignment' => [
                'id' => $this->assignment?->id,
                'assigned_at' => $this->assignment?->assigned_at,
                'assignee' => $this->assignment?->assignee?->only(['id', 'name', 'employee_id']),
                'assigned_by' => $this->assignment?->assigner?->only(['id', 'name']),
            ],
            'started_by' => $this->whenLoaded('startedBy', fn () => $this->startedBy?->only(['id', 'name', 'employee_id'])),
            'work_started_at' => $this->work_started_at,
            'work_completed_at' => $this->work_completed_at,
            'work_summary' => $this->work_summary,
            'root_cause' => $this->root_cause,
            'technician_notes' => $this->technician_notes,
            'review_status' => $this->review_status?->value,
            'reviewed_by' => $this->whenLoaded('reviewer', fn () => $this->reviewer?->only(['id', 'name'])),
            'reviewed_at' => $this->reviewed_at,
            'review_note' => $this->review_note,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
