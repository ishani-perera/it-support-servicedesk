<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Ticket::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'], 'description' => ['required', 'string', 'max:20000'],
            'category_id' => ['required', 'integer', Rule::exists('ticket_categories', 'id')->where('is_active', true)],
            'priority_id' => ['required', 'integer', Rule::exists('ticket_priorities', 'id')->where('is_active', true)],
            'department_id' => [Rule::requiredIf(fn () => $this->user()?->department_id === null), 'nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
        ];
    }
}
