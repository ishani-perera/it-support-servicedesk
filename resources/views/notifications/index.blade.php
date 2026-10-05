@extends('layouts.app')
@section('title', 'Notifications')
@section('topline', 'Notifications')
@section('content')
    <section class="ui-page-toolbar mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center"><div><p class="ui-eyebrow">Ticket activity</p><h1 class="ui-page-title text-3xl">Notifications</h1><p class="mt-2 text-sm text-slate-500">Updates for tickets you’re authorized to view.</p></div>@if ($notifications->isNotEmpty())<form method="POST" action="{{ route('notifications.read-all') }}">@csrf @method('PATCH')<button class="ui-button-secondary">Mark all read</button></form>@endif</section>
    <section class="ui-card overflow-hidden">
        @forelse ($notifications as $notification)
            <article class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 p-5 last:border-0 {{ $notification->read_at ? '' : 'bg-indigo-50/40' }}"><div class="flex min-w-0 flex-1 gap-3"><span class="mt-1.5 size-2 shrink-0 rounded-full {{ $notification->read_at ? 'bg-slate-200' : 'bg-indigo-600' }}" aria-hidden="true"></span><div class="min-w-0"><h2 class="text-sm font-semibold text-slate-900">{{ $notification->data['title'] ?? 'Ticket update' }}</h2><p class="mt-1 text-sm leading-6 text-slate-600">{{ $notification->data['message'] ?? 'A ticket has been updated.' }}</p><div class="mt-2 flex flex-wrap items-center gap-3"><time class="text-xs text-slate-500">{{ $notification->created_at->format('M j, Y g:i A') }}</time><a href="{{ route('notifications.open', $notification->id) }}" class="text-xs font-semibold text-indigo-700">{{ $notification->data['ticket_number'] ?? 'Open ticket' }}</a></div></div></div>@unless($notification->read_at)<form method="POST" action="{{ route('notifications.read', $notification->id) }}">@csrf @method('PATCH')<button class="text-xs font-semibold text-slate-600 hover:text-slate-950">Mark read</button></form>@endunless</article>
        @empty
            <div class="ui-empty m-4"><span class="mx-auto grid size-12 place-items-center rounded-2xl bg-white text-violet-700 shadow-sm" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9m-8 12h4" /></svg></span><h2 class="mt-4 text-sm font-bold text-slate-900">No notifications yet</h2><p class="mt-1 text-sm text-slate-500">Important ticket updates will show up here.</p></div>
        @endforelse
        @if ($notifications->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $notifications->links() }}</div>@endif
    </section>
@endsection
