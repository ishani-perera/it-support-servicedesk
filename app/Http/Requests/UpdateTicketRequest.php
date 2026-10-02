<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('ticket')) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'], 'description' => ['sometimes', 'required', 'string', 'max:20000'],
            'category_id' => ['sometimes', 'integer', Rule::exists('ticket_categories', 'id')->where('is_active', true)],
            'priority_id' => ['sometimes', 'integer', Rule::exists('ticket_priorities', 'id')->where('is_active', true)],
        ];
    }
}
