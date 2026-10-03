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
            'status' => ['sometimes', 'string', Rule::in(TicketStatusSlug::values())],
            'priority_id' => ['sometimes', 'integer', 'exists:ticket_priorities,id'],
            'category_id' => ['sometimes', 'integer', 'exists:ticket_categories,id'],
            'department_id' => ['sometimes', 'integer', 'exists:departments,id'],
            'assigned_to' => ['sometimes', 'integer', 'exists:users,id'],
            'requester_id' => ['sometimes', 'integer', 'exists:users,id'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'ticket_number' => ['sometimes', 'string', 'max:20'],
            'search' => ['sometimes', 'string', 'max:150'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'assignment' => ['sometimes', 'string', Rule::in(['mine', 'unassigned'])],
            'sort' => ['sometimes', 'string', Rule::in(['newest', 'oldest', 'updated', 'priority'])],
        ];
    }
}
