<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveTechnicianWorkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reviewWork', $this->route('ticket')) ?? false;
    }

    public function rules(): array
    {
        return ['review_note' => ['nullable', 'string', 'max:20000']];
    }
}
