<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendBackTechnicianWorkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reviewWork', $this->route('ticket')) ?? false;
    }

    public function rules(): array
    {
        return ['review_note' => ['required', 'string', 'min:1', 'max:20000']];
    }
}
