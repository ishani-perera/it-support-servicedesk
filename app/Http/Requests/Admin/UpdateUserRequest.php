<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Admin user management. Authorization is checked PER FIELD against
 * UserPolicy (updateRole / updateStatus / updateDepartment), so each
 * privileged field has its own rule, in addition to the `role:admin` route
 * middleware.
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $target = $this->route('user');

        if (! $actor instanceof User || ! $target instanceof User) {
            return false;
        }

        $checks = [
            'role' => 'updateRole',
            'is_active' => 'updateStatus',
            'department_id' => 'updateDepartment',
        ];

        $touched = array_filter(array_keys($checks), fn (string $field) => $this->has($field));

        $profileFields = $this->expectsJson() ? [] : ['name', 'email', 'employee_id', 'phone'];
        foreach ($profileFields as $field) {
            if ($this->has($field) && ! $actor->can('update', $target)) {
                return false;
            }
        }

        if ($touched === [] && ! collect($profileFields)->contains(fn (string $field) => $this->has($field))) {
            return false;
        }

        foreach ($touched as $field) {
            if (! $actor->can($checks[$field], $target)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'role' => ['sometimes', 'required', Rule::enum(UserRole::class)],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'department_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Department::class, 'id')->whereNull('deleted_at')->where('is_active', true)],
        ];

        if (! $this->expectsJson()) {
            $rules = array_merge([
                'name' => ['sometimes', 'required', 'string', 'max:255'],
                'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user')?->id)],
                'employee_id' => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('users', 'employee_id')->ignore($this->route('user')?->id)],
                'phone' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[0-9 +().-]*$/'],
            ], $rules);
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $target = $this->route('user');
            if (! $target instanceof User) {
                return;
            }

            $role = UserRole::tryFrom($this->input('role', $target->role->value));
            if ($role === null || $role === UserRole::Admin || (! $this->has('role') && ! $this->has('department_id'))) {
                return;
            }

            $departmentId = $this->input('department_id', $target->department_id);
            if (! $departmentId && $this->expectsJson()) {
                $departmentId = Department::query()->active()->orderBy('id')->value('id');
            }
            if (! $departmentId || ! Department::query()->active()->whereKey($departmentId)->exists()) {
                $validator->errors()->add('department_id', 'Employees, IT Support users, and Technicians need an active department.');
            }
        });
    }
}
