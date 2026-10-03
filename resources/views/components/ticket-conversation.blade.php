@props(['ticket', 'comments', 'attachments'])

<section class="ui-card overflow-hidden" aria-labelledby="conversation-heading">
    <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-5 sm:px-7">
        <div><h2 id="conversation-heading" class="text-lg font-bold tracking-tight text-slate-950">Conversation</h2><p class="mt-1 text-sm text-slate-500">Ticket updates and replies in one place.</p></div>
        <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold tabular-nums text-slate-600">{{ $comments->count() }} {{ Str::plural('message', $comments->count()) }}</span>
    </div>

    @if ($comments->isEmpty())
        <div class="px-6 py-11 text-center"><span class="mx-auto grid size-12 place-items-center rounded-2xl bg-indigo-50 text-indigo-700" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h6m-8 8 2.3-3H18a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v11l1 3Z"/></svg></span><p class="mt-3 text-sm font-semibold text-slate-800">No replies yet</p><p class="mt-1 text-sm text-slate-500">Messages about this ticket will appear here.</p></div>
    @else
        <ol class="space-y-4 px-4 py-5 sm:px-6 sm:py-6">
            @foreach ($comments as $comment)
                @if (! $comment->is_internal || auth()->user()->can('viewInternalNotes', $ticket))
                    <li class="flex items-start gap-3 sm:gap-4">
                        <span class="grid size-10 shrink-0 place-items-center rounded-full ring-2 ring-white {{ $comment->user->isStaff() ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-100 text-slate-700' }} text-xs font-bold shadow-sm" aria-hidden="true">{{ $comment->user->initials ?: Str::upper(Str::substr($comment->user->name, 0, 1)) }}</span>
                        <article @class(['min-w-0 flex-1 rounded-2xl border p-4 sm:p-5', 'border-amber-200 bg-amber-50/50' => $comment->is_internal, 'border-slate-200 bg-white' => ! $comment->is_internal])>
                            <header class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                                <div class="min-w-0"><h3 class="text-sm font-semibold text-slate-950">{{ $comment->user->name }}@if ($comment->user->isStaff())<span class="ml-2 inline-flex rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-indigo-800">IT team</span>@endif</h3><time class="mt-1 block text-xs text-slate-500" datetime="{{ $comment->created_at->toIso8601String() }}">{{ $comment->created_at->format('M j, Y · g:i A') }}</time></div>
                                @if ($comment->is_internal)<span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-amber-900"><span class="size-1.5 rounded-full bg-amber-600" aria-hidden="true"></span>Internal note</span>@else<span class="inline-flex items-center gap-1.5 rounded-full bg-sky-50 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-sky-800"><span class="size-1.5 rounded-full bg-sky-500" aria-hidden="true"></span>Public reply</span>@endif
                            </header>
                            <p class="mt-3 whitespace-pre-line break-words text-sm leading-7 text-slate-700">{{ $comment->body }}</p>
                            @if ($comment->attachments->isNotEmpty())
                                <ul class="mt-4 space-y-2 border-t border-slate-200/70 pt-3" aria-label="Attachments on this message">
                                    @foreach ($comment->attachments as $attachment)
                                        <li><a href="{{ route('tickets.attachments.show', [$ticket, $attachment]) }}" class="inline-flex max-w-full items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-indigo-700 shadow-sm transition hover:border-indigo-200 hover:bg-indigo-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-4 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="m8 12.5 5.8-5.8a3 3 0 0 1 4.2 4.2l-8.5 8.5a5 5 0 0 1-7.1-7.1l8.5-8.5"/></svg><span class="max-w-48 truncate">{{ $attachment->original_name }}</span><span class="shrink-0 font-normal text-slate-500">{{ $attachment->size_for_humans }}</span></a><span class="mt-1 block text-[11px] text-slate-500">Uploaded by {{ $attachment->uploader->name }} · {{ $attachment->created_at->format('M j, Y g:i A') }}</span></li>
                                    @endforeach
                                </ul>
                            @endif
                        </article>
                    </li>
                @endif
            @endforeach
        </ol>
    @endif

    @can('create', [\App\Models\TicketComment::class, $ticket, false])
        <form method="POST" action="{{ route('tickets.comments.store', $ticket) }}" class="border-t border-slate-100 bg-slate-50/80 p-5 sm:p-7" data-loading-form>
            @csrf
            <input type="hidden" name="_html_form" value="1">
            <label for="ticket-comment-body" class="mb-2 block text-sm font-semibold text-slate-800">Add a reply</label>
            <textarea id="ticket-comment-body" name="body" rows="4" required maxlength="10000" placeholder="Write a message about this ticket…" class="w-full resize-y rounded-xl border-slate-300 bg-white text-sm leading-6 shadow-sm placeholder:text-slate-400 @error('body') border-rose-400 @enderror" aria-describedby="comment-visibility comment-error">{{ old('body') }}</textarea>
            <p id="comment-visibility" class="mt-1.5 text-xs text-slate-500">Replies are visible to the requester and IT Support.</p>
            @error('body')<p id="comment-error" class="mt-1 text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
            @can('create', [\App\Models\TicketComment::class, $ticket, true])
                <label class="mt-3 inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-lg px-2 text-sm font-medium text-slate-700 hover:bg-amber-50"><input type="checkbox" name="is_internal" value="1" @checked(old('is_internal')) class="size-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"> Add as an internal note <span class="text-xs font-normal text-slate-500">(IT Support only)</span></label>
            @endcan
            <div class="mt-3 flex justify-end"><button type="submit" data-loading-label="Sending…" class="ui-button-primary">Send message <span aria-hidden="true">→</span></button></div>
        </form>
    @elseif (auth()->user()->isSupport())
        <p class="border-t border-slate-100 bg-slate-50/80 px-5 py-4 text-sm text-slate-600 sm:px-7">You can view this ticket, but replies and notes are limited to its assignee and the unassigned queue.</p>
    @endcan
</section>

<section class="ui-card overflow-hidden" aria-labelledby="attachments-heading">
    <div class="border-b border-slate-100 px-5 py-5 sm:px-7"><h2 id="attachments-heading" class="text-lg font-bold text-slate-950">Attachments</h2><p class="mt-1 text-sm text-slate-500">Files available to you on this ticket.</p></div>
    @php($ticketFiles = $attachments->whereNull('comment_id'))
    @if ($ticketFiles->isEmpty())
        <p class="px-6 py-7 text-sm text-slate-500">No ticket-level attachments.</p>
    @else
        <ul class="divide-y divide-slate-100 px-5 sm:px-7">
            @foreach ($ticketFiles as $attachment)
                <li class="flex items-center gap-3 py-4"><span class="grid size-10 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-600" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 3h6l5 5v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h1Zm6 0v5h5M8 13h8m-8 4h8"/></svg></span><span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-slate-800">{{ $attachment->original_name }}</span><span class="mt-0.5 block text-xs leading-5 text-slate-500">{{ $attachment->size_for_humans }} · Uploaded by {{ $attachment->uploader->name }} · {{ $attachment->created_at->format('M j, Y g:i A') }}</span></span><a href="{{ route('tickets.attachments.show', [$ticket, $attachment]) }}" class="shrink-0 rounded-lg px-3 py-2 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600">Download</a></li>
            @endforeach
        </ul>
    @endif

    @can('uploadAttachment', $ticket)
        <form method="POST" action="{{ route('tickets.attachments.store', $ticket) }}" enctype="multipart/form-data" class="border-t border-slate-100 bg-slate-50/80 p-5 sm:p-7" data-file-picker data-loading-form>
            @csrf
            <input type="hidden" name="_html_form" value="1">
            <label for="ticket-attachment-file" class="mb-1.5 block text-sm font-semibold text-slate-800">Add an attachment</label>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center"><input id="ticket-attachment-file" name="file" type="file" required accept=".pdf,.txt,.png,.jpg,.jpeg,.doc,.docx,.xls,.xlsx" class="min-w-0 flex-1 rounded-xl border border-slate-300 bg-white text-sm text-slate-600 file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2.5 file:text-xs file:font-semibold file:text-slate-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600"><button type="submit" data-loading-label="Uploading…" class="ui-button-secondary">Upload file</button></div>
            <p class="mt-1.5 text-xs text-slate-500">PDF, image, or Office document up to 10 MB.</p>
            @error('file')<p class="mt-1 text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
            <div data-file-details class="mt-3 hidden items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5"><span data-file-name class="min-w-0 truncate text-sm font-medium text-slate-700"></span><span data-file-size class="shrink-0 text-xs text-slate-500"></span><button type="button" data-file-remove class="shrink-0 rounded-lg px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-rose-600">Remove</button></div>
        </form>
    @endcan
</section>
