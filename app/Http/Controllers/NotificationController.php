<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\NotificationAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request, NotificationAccessService $notifications): View
    {
        $items = $notifications->visibleTo($request->user())->latest()->paginate(20);

        return view('notifications.index', ['notifications' => $items]);
    }

    public function read(Request $request, string $notification, NotificationAccessService $notifications): RedirectResponse
    {
        $item = $notifications->visibleTo($request->user())->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        return back()->with('status', 'Notification marked as read.');
    }

    public function readAll(Request $request, NotificationAccessService $notifications): RedirectResponse
    {
        $notifications->visibleTo($request->user())->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('status', 'Notifications marked as read.');
    }

    public function open(Request $request, string $notification, NotificationAccessService $notifications): RedirectResponse
    {
        $item = $notifications->visibleTo($request->user())->whereKey($notification)->firstOrFail();
        $ticketId = $item->data['ticket_id'] ?? null;
        abort_unless(is_numeric($ticketId), 404);

        $ticket = Ticket::query()->findOrFail($ticketId);
        $this->authorize('view', $ticket);
        $item->markAsRead();

        return redirect()->route('tickets.show', $ticket);
    }
}
