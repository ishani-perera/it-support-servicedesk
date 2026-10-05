<?php

namespace App\Http\Requests\Admin;

use App\Models\Department;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AdminSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        $model = $this->routeIs('admin.departments.*') ? Department::class : TicketCategory::class;

        return $this->user() instanceof User && Gate::allows('viewAny', $model);
    }

    public function rules(): array
    {
        return ['search' => ['sometimes', 'nullable', 'string', 'max:100']];
    }
}
