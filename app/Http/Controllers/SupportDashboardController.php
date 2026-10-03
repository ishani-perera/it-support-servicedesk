<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Services\TicketQueryService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportDashboardController extends Controller
{
    public function __invoke(Request $request, TicketQueryService $tickets): View
    {
        $this->authorize('viewAny', Ticket::class);
        abort_unless($request->user()->isSupport(), 403);

        $visible = Ticket::query()->visibleTo($request->user());
        $priorityTickets = (clone $visible)->whereHas('priority', fn ($query) => $query->where('level', '>=', 3))
            ->withListRelations()->latest('updated_at')->limit(5)->get();
        $recentlyAssigned = (clone $visible)->assignedTo($request->user())->withListRelations()
            ->orderByDesc(TicketAssignment::query()->select('assigned_at')->whereColumn('ticket_assignments.ticket_id', 'tickets.id')->whereNull('unassigned_at'))
            ->limit(8)->get();

        return view('support.dashboard', [
            'stats' => $tickets->supportStats($request->user()),
            'recentlyAssigned' => $recentlyAssigned,
            'priorityTickets' => $priorityTickets,
        ]);
    }
}
