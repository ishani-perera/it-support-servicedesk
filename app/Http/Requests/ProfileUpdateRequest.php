<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Self-service profile edit. ONLY the fields listed in rules() can ever be
 * applied, because the controller uses $request->validated(). Role, active
 * flag, department, e-mail and password are intentionally absent (they are
 * managed by Admin / the password flows).
 */
class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->user()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 +().-]*$/'],
        ];
    }
}
