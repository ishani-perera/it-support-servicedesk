<?php

namespace App\Http\Requests\Admin;

use App\Models\TicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdatePriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $priority = $this->route('priority');

        return $priority instanceof TicketPriority && Gate::allows('update', $priority);
    }

    public function rules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sla_response_minutes' => ['required', 'integer', 'min:1', 'max:525600'],
            'sla_resolution_minutes' => ['required', 'integer', 'min:1', 'max:525600'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
