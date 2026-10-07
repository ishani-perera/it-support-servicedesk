<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteTechnicianWorkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('completeWork', $this->route('ticket')) ?? false;
    }

    public function rules(): array
    {
        return [
            'work_summary' => ['required', 'string', 'min:1', 'max:20000'],
            'root_cause' => ['required', 'string', 'min:1', 'max:20000'],
            'technician_notes' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
