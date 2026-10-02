<?php

namespace App\Http\Requests;

use App\Enums\TicketStatusSlug;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateStatus', $this->route('ticket')) ?? false;
    }

    public function rules(): array
    {
        return ['status' => ['required', 'string', Rule::in(TicketStatusSlug::values())]];
    }
}
