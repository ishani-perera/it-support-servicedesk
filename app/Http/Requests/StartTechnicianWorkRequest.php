<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartTechnicianWorkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('startWork', $this->route('ticket')) ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
