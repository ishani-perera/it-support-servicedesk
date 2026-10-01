<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

        if ($touched === []) {
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
        return [
            'role' => ['sometimes', 'required', Rule::enum(UserRole::class)],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'department_id' => ['sometimes', 'nullable', 'integer', Rule::exists(Department::class, 'id')->whereNull('deleted_at')],
        ];
    }
}
