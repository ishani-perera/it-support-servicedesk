<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UserIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User && Gate::allows('viewAny', User::class);
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'role' => ['sometimes', 'nullable', Rule::enum(UserRole::class)],
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
            'active' => ['sometimes', 'nullable', Rule::in(['active', 'inactive'])],
            'sort' => ['sometimes', 'nullable', Rule::in(['name', 'email', 'role', 'created_at'])],
            'direction' => ['sometimes', 'nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
