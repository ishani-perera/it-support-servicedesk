<?php

namespace App\Http\Requests\Admin;

use App\Models\TicketCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('category');

        return $target instanceof TicketCategory
            ? Gate::allows('update', $target)
            : Gate::allows('create', TicketCategory::class);
    }

    public function rules(): array
    {
        $target = $this->route('category');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('ticket_categories', 'name')->ignore($target?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
