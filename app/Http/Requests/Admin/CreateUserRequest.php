<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && Gate::allows('create', User::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
            'department_id' => [
                Rule::requiredIf(fn () => $this->input('role') !== UserRole::Admin->value),
                'nullable', 'integer', Rule::exists(Department::class, 'id')->whereNull('deleted_at')->where('is_active', true),
            ],
            'employee_id' => ['nullable', 'string', 'max:50', 'unique:users,employee_id'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 +().-]*$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (in_array($this->input('role'), [UserRole::Employee->value, UserRole::Support->value], true)
                && ! $this->filled('department_id')) {
                $validator->errors()->add('department_id', 'Employees and IT Support users need an active department.');
            }
        });
    }
}
