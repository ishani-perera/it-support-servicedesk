<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\TicketAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', [TicketAssignment::class, $this->route('ticket')]) ?? false;
    }

    public function rules(): array
    {
        return [
            'assigned_to' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)->whereIn('role', array_map(fn (UserRole $role) => $role->value, UserRole::ticketAssignees()))],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
