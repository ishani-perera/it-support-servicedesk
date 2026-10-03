@props(['recentNotifications' => collect(), 'unreadCount' => 0])

<details class="relative">
    <summary class="relative grid size-10 cursor-pointer list-none place-items-center rounded-lg text-slate-600 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-600" aria-label="Notifications, {{ $unreadCount }} unread">
        <span aria-hidden="true">♧</span>
        @if ($unreadCount > 0)<span class="absolute -right-1 -top-1 grid min-w-5 place-items-center rounded-full bg-rose-600 px-1 text-[10px] font-bold leading-5 text-white">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>@endif
    </summary>
    <div class="absolute right-0 top-full z-50 mt-2 w-[min(22rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3"><div><h2 class="text-sm font-bold text-slate-950">Notifications</h2><p class="text-xs text-slate-500">{{ $unreadCount }} unread</p></div>@if ($unreadCount > 0)<form method="POST" action="{{ route('notifications.read-all') }}">@csrf @method('PATCH')<button class="text-xs font-semibold text-indigo-700 hover:text-indigo-900">Mark all read</button></form>@endif</div>
        @forelse ($recentNotifications as $notification)
            <div class="border-b border-slate-100 px-4 py-3 last:border-0 {{ $notification->read_at ? 'bg-white' : 'bg-indigo-50/50' }}">
                <div class="flex items-start justify-between gap-2"><p class="text-sm font-semibold text-slate-900">{{ $notification->data['title'] ?? 'Ticket update' }}</p>@unless($notification->read_at)<span class="mt-1.5 size-2 shrink-0 rounded-full bg-indigo-600" aria-label="Unread"></span>@endunless</div>
                <p class="mt-1 text-xs leading-5 text-slate-600">{{ $notification->data['message'] ?? 'A ticket has been updated.' }}</p>
                <div class="mt-2 flex items-center justify-between gap-3"><time class="text-[11px] text-slate-500">{{ $notification->created_at->diffForHumans() }}</time><div class="flex items-center gap-3"><a href="{{ route('notifications.open', $notification->id) }}" class="text-xs font-semibold text-indigo-700">{{ $notification->data['ticket_number'] ?? 'Open ticket' }}</a>@unless($notification->read_at)<form method="POST" action="{{ route('notifications.read', $notification->id) }}">@csrf @method('PATCH')<button class="text-[11px] font-medium text-slate-500 hover:text-slate-900">Mark read</button></form>@endunless</div></div>
            </div>
        @empty
            <p class="px-4 py-8 text-center text-sm text-slate-500">You’re all caught up.</p>
        @endforelse
        <a href="{{ route('notifications.index') }}" class="block border-t border-slate-100 px-4 py-3 text-center text-xs font-semibold text-indigo-700 hover:bg-slate-50">View all notifications</a>
    </div>
</details>
