<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketAttachmentRequest;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Services\TicketAttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase 03 authorization boundary (see TicketController): the ONLY way to read
 * an attachment's bytes. Files live on the private `local` disk (never under
 * /public), so there is no guessable URL that bypasses this check.
 * Uploads use the private disk after request and policy authorization.
 */
class TicketAttachmentController extends Controller
{
    public function store(StoreTicketAttachmentRequest $request, Ticket $ticket, TicketAttachmentService $attachments): JsonResponse|RedirectResponse
    {
        $comment = isset($request->validated()['comment_id'])
            ? $ticket->comments()->findOrFail($request->validated('comment_id'))
            : null;
        $attachment = $attachments->store($ticket, $request->user(), $request->file('file'), $comment);

        if ($request->input('_html_form') === '1') {
            return redirect()->route('tickets.show', $ticket)->with('status', 'Your attachment has been uploaded.');
        }

        return response()->json(['data' => ['id' => $attachment->id, 'name' => $attachment->original_name, 'size' => $attachment->file_size]], 201);
    }

    public function show(Ticket $ticket, TicketAttachment $attachment): StreamedResponse
    {
        $this->authorize('download', $attachment);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($attachment->file_path), 404);

        return $disk->download($attachment->file_path, $attachment->original_name, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
