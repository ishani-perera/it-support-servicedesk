<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('uploadAttachment', $this->route('ticket')) ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,txt,png,jpg,jpeg,doc,docx,xls,xlsx'],
            'comment_id' => ['sometimes', 'integer', Rule::exists('ticket_comments', 'id')->where('ticket_id', $this->route('ticket')->getKey())],
        ];
    }
}
