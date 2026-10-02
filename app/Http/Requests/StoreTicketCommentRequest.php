<?php

namespace App\Http\Requests;

use App\Models\TicketComment;
use Illuminate\Foundation\Http\FormRequest;

class StoreTicketCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');
        $internal = $this->boolean('is_internal');

        return $this->user()?->can('create', [TicketComment::class, $ticket, $internal]) ?? false;
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'min:1', 'max:10000'], 'is_internal' => ['sometimes', 'boolean']];
    }
}
