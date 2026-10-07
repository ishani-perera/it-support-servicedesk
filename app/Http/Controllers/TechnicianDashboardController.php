<?php

namespace App\Http\Controllers;

use App\Enums\TechnicianWorkReviewStatus;
use App\Enums\TicketStatusSlug;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TechnicianDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Ticket::class);
        abort_unless($request->user()->isTechnician(), 403);

        $assigned = Ticket::query()->visibleTo($request->user());
        $counts = (clone $assigned)
            ->join('ticket_statuses', 'tickets.status_id', '=', 'ticket_statuses.id')
            ->selectRaw('ticket_statuses.slug, COUNT(*) as aggregate')
            ->groupBy('ticket_statuses.slug')
            ->pluck('aggregate', 'slug');
        $sentBack = (clone $assigned)->whereHas('currentAssignment.workReports', fn (Builder $reports) => $reports
            ->where('review_status', TechnicianWorkReviewStatus::ChangesRequested))->count();

        $stats = [
            ['label' => 'Assigned to me', 'count' => (clone $assigned)->count(), 'status' => 'all', 'accent' => 'indigo'],
            ['label' => 'New assignments', 'count' => (int) ($counts[TicketStatusSlug::Assigned->value] ?? 0), 'status' => TicketStatusSlug::Assigned->value, 'accent' => 'violet'],
            ['label' => 'In progress', 'count' => (int) ($counts[TicketStatusSlug::InProgress->value] ?? 0), 'status' => TicketStatusSlug::InProgress->value, 'accent' => 'blue'],
            ['label' => 'Waiting for employee', 'count' => (int) ($counts[TicketStatusSlug::WaitingForUser->value] ?? 0), 'status' => TicketStatusSlug::WaitingForUser->value, 'accent' => 'amber'],
            ['label' => 'Needs further work', 'count' => $sentBack, 'status' => 'sent_back', 'accent' => 'rose'],
            ['label' => 'Waiting for review', 'count' => (int) ($counts[TicketStatusSlug::ItSupportReview->value] ?? 0), 'status' => TicketStatusSlug::ItSupportReview->value, 'accent' => 'cyan'],
            ['label' => 'Completed / closed', 'count' => (int) ($counts[TicketStatusSlug::Closed->value] ?? 0) + (int) ($counts[TicketStatusSlug::Resolved->value] ?? 0), 'status' => 'completed', 'accent' => 'emerald'],
        ];

        $statusFilter = $request->query('status');
        abort_if($statusFilter !== null && ! in_array($statusFilter, [...TicketStatusSlug::values(), 'all', 'sent_back', 'completed'], true), 404);
        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:150'],
        ]);
        $query = Ticket::query()->visibleTo($request->user())->withListRelations()
            ->with(['currentAssignment.workReports' => fn ($reports) => $reports->latest('id')]);

        if (filled($filters['search'] ?? null)) {
            $term = $filters['search'];
            $query->where(fn (Builder $tickets) => $tickets->where('title', 'like', '%'.$term.'%')
                ->orWhere('ticket_number', 'like', '%'.$term.'%')
                ->orWhereHas('user', fn (Builder $requester) => $requester->where('name', 'like', '%'.$term.'%')));
        }

        if ($statusFilter === 'sent_back') {
            $query->whereHas('currentAssignment.workReports', fn (Builder $reports) => $reports
                ->where('review_status', TechnicianWorkReviewStatus::ChangesRequested));
        } elseif ($statusFilter === 'completed') {
            $query->whereHas('status', fn (Builder $status) => $status->whereIn('slug', [TicketStatusSlug::Closed->value, TicketStatusSlug::Resolved->value]));
        } elseif ($statusFilter !== null && $statusFilter !== 'all') {
            $query->whereHas('status', fn (Builder $status) => $status->where('slug', $statusFilter));
        }

        $tickets = $query->latest('tickets.updated_at')->paginate(12)->withQueryString();

        return view($request->routeIs('technician.dashboard') ? 'technician.dashboard' : 'technician.tickets.index', [
            'stats' => $stats,
            'tickets' => $tickets,
            'statusFilter' => $statusFilter ?? 'all',
            'filters' => $filters,
        ]);
    }
}
