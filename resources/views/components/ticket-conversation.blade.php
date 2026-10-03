@props(['ticket', 'comments', 'attachments'])

<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="conversation-heading">
    <div class="border-b border-slate-100 px-5 py-5 sm:px-7">
        <div class="flex items-center justify-between gap-3">
            <div><h2 id="conversation-heading" class="text-lg font-bold text-slate-950">Conversation</h2><p class="mt-1 text-sm text-slate-500">Ticket updates and replies.</p></div>
            <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ $comments->count() }} {{ Str::plural('message', $comments->count()) }}</span>
        </div>
    </div>

    @if ($comments->isEmpty())
        <div class="px-6 py-10 text-center"><span class="mx-auto grid size-11 place-items-center rounded-xl bg-indigo-50 text-lg text-indigo-700" aria-hidden="true">✉</span><p class="mt-3 text-sm font-semibold text-slate-800">No replies yet</p><p class="mt-1 text-sm text-slate-500">Messages about this ticket will appear here.</p></div>
    @else
        <ol class="divide-y divide-slate-100 px-5 sm:px-7">
            @foreach ($comments as $comment)
                @if (! $comment->is_internal || auth()->user()->can('viewInternalNotes', $ticket))
                    <li class="flex gap-3 py-5 sm:gap-4">
                        <span class="grid size-10 shrink-0 place-items-center rounded-full {{ $comment->user->isStaff() ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-700' }} text-xs font-bold" aria-hidden="true">{{ $comment->user->initials ?: Str::upper(Str::substr($comment->user->name, 0, 1)) }}</span>
                        <article class="min-w-0 flex-1">
                            <header class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                <h3 class="text-sm font-semibold text-slate-900">{{ $comment->user->name }}@if ($comment->user->isStaff())<span class="ml-2 rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-indigo-700">IT team</span>@endif</h3>
                                <time class="text-xs text-slate-500" datetime="{{ $comment->created_at->toIso8601String() }}">{{ $comment->created_at->format('M j, Y · g:i A') }}</time>
                            </header>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                @if ($comment->is_internal)
                                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-800">Internal note</span>
                                @else
                                    <span class="rounded-full bg-sky-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-sky-700">Public reply</span>
                                @endif
                            </div>
                            <p class="mt-2 whitespace-pre-line break-words text-sm leading-6 text-slate-700">{{ $comment->body }}</p>
                            @if ($comment->attachments->isNotEmpty())
                                <ul class="mt-3 space-y-2" aria-label="Attachments on this message">
                                    @foreach ($comment->attachments as $attachment)
                                        <li><a href="{{ route('tickets.attachments.show', [$ticket, $attachment]) }}" class="inline-flex max-w-full flex-wrap items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600"><span aria-hidden="true">▧</span><span class="truncate">{{ $attachment->original_name }}</span><span class="shrink-0 font-normal text-slate-500">{{ $attachment->size_for_humans }}</span><span class="w-full pl-5 text-left text-[11px] font-normal text-slate-500">Uploaded by {{ $attachment->uploader->name }} · {{ $attachment->created_at->format('M j, Y g:i A') }}</span></a></li>
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
        <form method="POST" action="{{ route('tickets.comments.store', $ticket) }}" class="border-t border-slate-100 bg-slate-50/70 p-5 sm:p-7" data-loading-form>
            @csrf
            <input type="hidden" name="_html_form" value="1">
            <label for="ticket-comment-body" class="mb-2 block text-sm font-semibold text-slate-800">Add a reply</label>
            <textarea id="ticket-comment-body" name="body" rows="4" required maxlength="10000" placeholder="Write a message about this ticket…" class="w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500 @error('body') border-rose-400 @enderror">{{ old('body') }}</textarea>
            @error('body')<p class="mt-1 text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
            @can('create', [\App\Models\TicketComment::class, $ticket, true])
                <label class="mt-3 inline-flex items-center gap-2 text-sm font-medium text-slate-700"><input type="checkbox" name="is_internal" value="1" @checked(old('is_internal')) class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"> Add as an internal note <span class="text-xs text-slate-500">(visible to IT Support only)</span></label>
            @endcan
            <div class="mt-3 flex justify-end"><button type="submit" data-loading-label="Sending…" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Send message</button></div>
        </form>
    @elseif (auth()->user()->isSupport())
        <p class="border-t border-slate-100 bg-slate-50/70 px-5 py-4 text-sm text-slate-600 sm:px-7">You can view this ticket, but replies and notes are limited to its assignee and the unassigned queue.</p>
    @endcan
</section>

<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="attachments-heading">
    <div class="border-b border-slate-100 px-5 py-5 sm:px-7"><h2 id="attachments-heading" class="text-lg font-bold text-slate-950">Attachments</h2><p class="mt-1 text-sm text-slate-500">Files available to you on this ticket.</p></div>
    @php($ticketFiles = $attachments->whereNull('comment_id'))
    @if ($ticketFiles->isEmpty())
        <p class="px-6 py-6 text-sm text-slate-500">No ticket-level attachments.</p>
    @else
        <ul class="divide-y divide-slate-100 px-5 sm:px-7">
            @foreach ($ticketFiles as $attachment)
                <li class="flex items-center gap-3 py-4"><span class="grid size-10 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-600" aria-hidden="true">▧</span><span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-slate-800">{{ $attachment->original_name }}</span><span class="mt-0.5 block text-xs text-slate-500">{{ $attachment->size_for_humans }} · Uploaded by {{ $attachment->uploader->name }} · {{ $attachment->created_at->format('M j, Y g:i A') }}</span></span><a href="{{ route('tickets.attachments.show', [$ticket, $attachment]) }}" class="shrink-0 rounded-lg px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600">Download</a></li>
            @endforeach
        </ul>
    @endif

    @can('uploadAttachment', $ticket)
        <form method="POST" action="{{ route('tickets.attachments.store', $ticket) }}" enctype="multipart/form-data" class="border-t border-slate-100 bg-slate-50/70 p-5 sm:p-7" data-file-picker data-loading-form>
            @csrf
            <input type="hidden" name="_html_form" value="1">
            <label for="ticket-attachment-file" class="mb-1.5 block text-sm font-semibold text-slate-800">Add an attachment</label>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center"><input id="ticket-attachment-file" name="file" type="file" required accept=".pdf,.txt,.png,.jpg,.jpeg,.doc,.docx,.xls,.xlsx" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white text-sm text-slate-600 file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2.5 file:text-xs file:font-semibold file:text-slate-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600"><button type="submit" data-loading-label="Uploading…" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600">Upload file</button></div>
            <p class="mt-1.5 text-xs text-slate-500">PDF, image, or Office document up to 10 MB.</p>
            @error('file')<p class="mt-1 text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
            <div data-file-details class="mt-3 hidden items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5"><span data-file-name class="min-w-0 truncate text-sm font-medium text-slate-700"></span><span data-file-size class="shrink-0 text-xs text-slate-500"></span><button type="button" data-file-remove class="shrink-0 rounded-md px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-rose-600">Remove</button></div>
        </form>
    @endcan
</section>
