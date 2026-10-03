<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $this->user() instanceof User && $target instanceof User && $this->user()->can('updateStatus', $target);
    }

    public function rules(): array
    {
        return ['is_active' => ['required', 'boolean']];
    }
}
