<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TicketPriorityLevel;
use App\Enums\TicketStatusSlug;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        $this->authorize('viewAny', User::class);

        $statusCounts = Ticket::query()
            ->join('ticket_statuses', 'tickets.status_id', '=', 'ticket_statuses.id')
            ->selectRaw('ticket_statuses.slug, COUNT(*) as total')
            ->groupBy('ticket_statuses.slug')
            ->pluck('total', 'slug');

        $counts = collect(TicketStatusSlug::cases())->mapWithKeys(fn (TicketStatusSlug $status) => [
            $status->value => (int) ($statusCounts[$status->value] ?? 0),
        ]);

        return view('admin.dashboard', [
            'totalUsers' => User::query()->count(),
            'activeEmployees' => User::query()->active()->where('role', UserRole::Employee)->count(),
            'activeSupport' => User::query()->active()->where('role', UserRole::Support)->count(),
            'statusCounts' => $counts,
            'highPriorityCount' => Ticket::query()->whereHas('priority', fn ($q) => $q->where('level', '>=', TicketPriorityLevel::High->value))->count(),
            'recentTickets' => Ticket::query()->withListRelations()->latest('created_at')->limit(8)->get(),
            'recentUsers' => User::query()->with('department:id,name')->latest('updated_at')->limit(6)
                ->get(['id', 'name', 'email', 'role', 'is_active', 'department_id', 'updated_at']),
            'statuses' => TicketStatus::query()->orderBy('sort_order')->get(['id', 'name', 'slug', 'is_active']),
            'priorities' => TicketPriority::query()->orderBy('level')->get(['id', 'name', 'level']),
        ]);
    }
}
