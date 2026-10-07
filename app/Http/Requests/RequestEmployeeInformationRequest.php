<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RequestEmployeeInformationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('requestInformation', $this->route('ticket')) ?? false;
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'min:1', 'max:10000']];
    }
}
