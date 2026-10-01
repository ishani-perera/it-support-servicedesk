<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase 03 authorization boundary (see TicketController): the ONLY way to read
 * an attachment's bytes. Files live on the private `local` disk (never under
 * /public), so there is no guessable URL that bypasses this check.
 * Uploading is a later phase.
 */
class TicketAttachmentController extends Controller
{
    public function show(Ticket $ticket, TicketAttachment $attachment): StreamedResponse
    {
        $this->authorize('download', $attachment);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($attachment->file_path), 404);

        return $disk->download($attachment->file_path, $attachment->original_name, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
