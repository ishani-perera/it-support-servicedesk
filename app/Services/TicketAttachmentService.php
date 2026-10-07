<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TicketAttachmentService
{
    public function store(Ticket $ticket, User $uploader, UploadedFile $file, ?TicketComment $comment = null): TicketAttachment
    {
        if ($comment && ($comment->ticket_id !== $ticket->getKey() || ($comment->is_internal && ! $uploader->isStaff() && ! $uploader->isTechnician()))) {
            abort(404);
        }

        $extension = strtolower($file->guessExtension() ?? '');
        if (! in_array($extension, ['pdf', 'txt', 'png', 'jpg', 'jpeg', 'doc', 'docx', 'xls', 'xlsx'], true)) {
            throw ValidationException::withMessages(['file' => 'This file type is not allowed.']);
        }

        $name = Str::uuid()->toString().'.'.$extension;
        $path = 'tickets/'.$ticket->getKey().'/'.$name;
        $disk = Storage::disk('local');
        abort_unless($disk->putFileAs('tickets/'.$ticket->getKey(), $file, $name), 500);

        try {
            return DB::transaction(fn () => TicketAttachment::create([
                'ticket_id' => $ticket->getKey(), 'comment_id' => $comment?->getKey(),
                'uploaded_by' => $uploader->getKey(), 'original_name' => mb_substr($this->displayName($file), 0, 255),
                'file_name' => $name, 'file_path' => $path, 'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'file_size' => $file->getSize(),
            ]));
        } catch (\Throwable $exception) {
            $disk->delete($path);
            throw $exception;
        }
    }

    private function displayName(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';

        return $name !== '' ? $name : 'attachment';
    }
}
