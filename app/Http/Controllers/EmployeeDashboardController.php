<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatusSlug;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $this->authorize('viewAny', Ticket::class);

        $counts = Ticket::query()
            ->visibleTo($request->user())
            ->join('ticket_statuses', 'tickets.status_id', '=', 'ticket_statuses.id')
            ->selectRaw('ticket_statuses.slug, COUNT(*) as aggregate')
            ->groupBy('ticket_statuses.slug')
            ->pluck('aggregate', 'slug');

        $statusStats = collect(TicketStatusSlug::cases())->map(fn (TicketStatusSlug $status) => [
            'slug' => $status->value,
            'label' => $status->label(),
            'count' => (int) ($counts[$status->value] ?? 0),
        ]);
        $stats = collect([['label' => 'My tickets', 'count' => $statusStats->sum('count')]])
            ->merge($statusStats->reject(fn (array $status) => $status['slug'] === TicketStatusSlug::Assigned->value));

        $recentTickets = Ticket::query()
            ->visibleTo($request->user())
            ->withListRelations()
            ->latest('updated_at')
            ->limit(6)
            ->get();

        return view('employee.dashboard', [
            'stats' => $stats,
            'recentTickets' => $recentTickets,
        ]);
    }
}
