@extends('layouts.app')

@section('title', $ticket->ticket_number)
@section('topline', 'Ticket details')

@section('content')
    <a href="{{ route('tickets.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600"><span aria-hidden="true">←</span> Back to my tickets</a>
    <div class="mt-5 grid gap-6 xl:grid-cols-[minmax(0,1fr)_300px]">
        <div class="min-w-0 space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <div class="flex flex-wrap items-center gap-2"><span class="font-mono text-sm font-semibold text-slate-500">{{ $ticket->ticket_number }}</span><x-ticket-status-badge :status="$ticket->status" /></div>
                <h1 class="mt-3 break-words text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">{{ $ticket->title }}</h1>
                <p class="mt-3 text-sm leading-6 text-slate-500">Submitted {{ $ticket->created_at->format('M j, Y \a\t g:i A') }} <span class="px-1 text-slate-300">·</span> Updated {{ $ticket->updated_at->format('M j, Y \a\t g:i A') }}</p>
                <div class="mt-6 border-t border-slate-100 pt-5"><h2 class="text-xs font-bold uppercase tracking-wider text-slate-500">Description</h2><div class="mt-3 whitespace-pre-line break-words text-sm leading-7 text-slate-700">{{ $ticket->description }}</div></div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-5 sm:px-7"><div class="flex items-center justify-between gap-3"><div><h2 class="text-lg font-bold text-slate-950">Conversation</h2><p class="mt-1 text-sm text-slate-500">Updates between you and the IT team.</p></div><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ $comments->count() }} {{ Str::plural('message', $comments->count()) }}</span></div></div>
                @if ($comments->isEmpty())
                    <div class="px-6 py-10 text-center"><span class="mx-auto grid size-11 place-items-center rounded-xl bg-indigo-50 text-lg text-indigo-700" aria-hidden="true">✉</span><p class="mt-3 text-sm font-semibold text-slate-800">No replies yet</p><p class="mt-1 text-sm text-slate-500">Your IT team will respond here.</p></div>
                @else
                    <ol class="divide-y divide-slate-100 px-5 sm:px-7">
                        @foreach ($comments as $comment)
                            <li class="flex gap-3 py-5 sm:gap-4"><span class="grid size-10 shrink-0 place-items-center rounded-full {{ $comment->user->isStaff() ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-700' }} text-xs font-bold">{{ $comment->user->initials }}</span><article class="min-w-0 flex-1"><header class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1"><h3 class="text-sm font-semibold text-slate-900">{{ $comment->user->name }}@if ($comment->user->isStaff())<span class="ml-2 rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-indigo-700">IT team</span>@endif</h3><time class="text-xs text-slate-500" datetime="{{ $comment->created_at->toIso8601String() }}">{{ $comment->created_at->format('M j, Y · g:i A') }}</time></header><p class="mt-2 whitespace-pre-line break-words text-sm leading-6 text-slate-700">{{ $comment->body }}</p></article></li>
                        @endforeach
                    </ol>
                @endif
                <form method="POST" action="{{ route('tickets.comments.store', $ticket) }}" class="border-t border-slate-100 bg-slate-50/70 p-5 sm:p-7" data-loading-form>
                    @csrf
                    <input type="hidden" name="_html_form" value="1">
                    <label for="body" class="mb-2 block text-sm font-semibold text-slate-800">Add a reply</label>
                    <textarea id="body" name="body" rows="4" required maxlength="10000" placeholder="Write a message for the IT team…" class="w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500 @error('body') border-rose-400 @enderror">{{ old('body') }}</textarea>
                    @error('body')<p class="mt-1 text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
                    <div class="mt-3 flex justify-end"><button type="submit" data-loading-label="Sending…" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Send reply</button></div>
                </form>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-5 sm:px-7"><h2 class="text-lg font-bold text-slate-950">Attachments</h2><p class="mt-1 text-sm text-slate-500">Files shared on this request.</p></div>
                @if ($attachments->isEmpty())
                    <p class="px-6 py-6 text-sm text-slate-500">No attachments have been shared yet.</p>
                @else
                    <ul class="divide-y divide-slate-100 px-5 sm:px-7">@foreach ($attachments as $attachment)<li class="flex items-center gap-3 py-4"><span class="grid size-10 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-600" aria-hidden="true">▧</span><span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-slate-800">{{ $attachment->original_name }}</span><span class="mt-0.5 block text-xs text-slate-500">{{ $attachment->size_for_humans }} · Added {{ $attachment->created_at->format('M j, Y') }}</span></span><a href="{{ route('tickets.attachments.show', [$ticket, $attachment]) }}" class="shrink-0 rounded-lg px-3 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600" aria-label="Download {{ $attachment->original_name }}">Download</a></li>@endforeach</ul>
                @endif
                <form method="POST" action="{{ route('tickets.attachments.store', $ticket) }}" enctype="multipart/form-data" class="border-t border-slate-100 bg-slate-50/70 p-5 sm:p-7" data-file-picker data-loading-form>
                    @csrf
                    <input type="hidden" name="_html_form" value="1">
                    <label for="file" class="mb-1.5 block text-sm font-semibold text-slate-800">Add an attachment</label>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center"><input id="file" name="file" type="file" required accept=".pdf,.txt,.png,.jpg,.jpeg,.doc,.docx,.xls,.xlsx" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white text-sm text-slate-600 file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2.5 file:text-xs file:font-semibold file:text-slate-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600"><button type="submit" data-loading-label="Uploading…" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600">Upload file</button></div>
                    <p class="mt-1.5 text-xs text-slate-500">PDF, image, or Office document up to 10 MB.</p>
                    @error('file')<p class="mt-1 text-sm font-medium text-rose-700">{{ $message }}</p>@enderror
                    <div data-file-details class="mt-3 hidden items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white px-3 py-2.5"><span data-file-name class="min-w-0 truncate text-sm font-medium text-slate-700"></span><span data-file-size class="shrink-0 text-xs text-slate-500"></span><button type="button" data-file-remove class="shrink-0 rounded-md px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-rose-600">Remove</button></div>
                </form>
            </section>
        </div>

        <aside class="h-fit rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:sticky xl:top-24">
            <h2 class="text-sm font-bold text-slate-950">Ticket information</h2>
            <dl class="mt-4 space-y-4">
                <div><dt class="text-xs font-medium text-slate-500">Status</dt><dd class="mt-1.5"><x-ticket-status-badge :status="$ticket->status" /></dd></div>
                <div><dt class="text-xs font-medium text-slate-500">Priority</dt><dd class="mt-1.5"><x-ticket-priority-badge :priority="$ticket->priority" /></dd></div>
                <div><dt class="text-xs font-medium text-slate-500">Category</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ $ticket->category->name }}</dd></div>
                <div><dt class="text-xs font-medium text-slate-500">Department</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ $ticket->department->name }}</dd></div>
                <div><dt class="text-xs font-medium text-slate-500">Requested by</dt><dd class="mt-1 text-sm font-semibold text-slate-800">{{ $ticket->user->name }}</dd></div>
                <div><dt class="text-xs font-medium text-slate-500">Ticket number</dt><dd class="mt-1 font-mono text-sm font-semibold text-slate-800">{{ $ticket->ticket_number }}</dd></div>
            </dl>
            <div class="mt-6 rounded-xl bg-indigo-50 p-4"><p class="text-sm font-semibold text-indigo-950">Need to add more detail?</p><p class="mt-1 text-xs leading-5 text-indigo-800">Reply in the conversation and the IT team will see your update.</p></div>
        </aside>
    </div>
@endsection
