<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\TicketAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignTechnicianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', [TicketAssignment::class, $this->route('ticket')]) ?? false;
    }

    public function rules(): array
    {
        $ticket = $this->route('ticket');
        $currentAssignment = $ticket?->currentAssignment()->with('assignee')->first();
        $isTechnicianHandoff = $currentAssignment?->assignee?->isTechnician() ?? false;

        return [
            'assigned_to' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('is_active', true)->where('role', UserRole::Technician->value),
            ],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
