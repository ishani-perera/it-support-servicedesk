<?php

namespace App\Http\Requests\Api;

use App\Enums\TicketStatusSlug;
use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApiTicketIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Ticket::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', 'string', Rule::in(TicketStatusSlug::values())],
            'priority_id' => ['sometimes', 'nullable', 'integer', 'exists:ticket_priorities,id'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:ticket_categories,id'],
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'requester_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'ticket_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'search' => ['sometimes', 'nullable', 'string', 'max:150'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'assignment' => ['sometimes', 'nullable', 'string', Rule::in(['mine', 'unassigned'])],
            'sort' => ['sometimes', 'nullable', 'string', Rule::in(['newest', 'oldest', 'updated', 'priority'])],
        ];
    }
}
