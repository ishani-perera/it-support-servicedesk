<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketNotificationResource;
use App\Services\NotificationAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request, NotificationAccessService $notifications): JsonResponse
    {
        $page = $notifications->visibleTo($request->user())->latest()->paginate(20)->withQueryString();

        return TicketNotificationResource::collection($page)->response();
    }

    public function unreadCount(Request $request, NotificationAccessService $notifications): JsonResponse
    {
        return response()->json(['data' => ['unread_count' => $notifications->visibleTo($request->user())->whereNull('read_at')->count()]]);
    }

    public function read(Request $request, string $notification, NotificationAccessService $notifications): JsonResponse
    {
        $item = $notifications->visibleTo($request->user())->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        return response()->json(['data' => ['id' => $item->id, 'read_at' => $item->read_at]]);
    }

    public function readAll(Request $request, NotificationAccessService $notifications): JsonResponse
    {
        $count = $notifications->visibleTo($request->user())->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['data' => ['updated' => $count]]);
    }
}
