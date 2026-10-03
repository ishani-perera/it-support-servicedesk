<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TicketPriorityLevel;
use App\Enums\TicketStatusSlug;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReportFilterRequest;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketSlaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(ReportFilterRequest $request, TicketSlaService $sla): View
    {
        $this->authorize('viewAny', User::class);
        $filters = $request->validated();

        return view('admin.reports.index', [
            'filters' => $filters,
            'byStatus' => $this->grouped($filters, 'ticket_statuses.name', 'ticket_statuses.sort_order'),
            'byPriority' => $this->grouped($filters, 'ticket_priorities.name', 'ticket_priorities.level'),
            'byCategory' => $this->grouped($filters, 'ticket_categories.name', 'ticket_categories.name'),
            'byDepartment' => $this->grouped($filters, 'departments.name', 'departments.name'),
            'byAgent' => $this->agentTotals($filters),
            'overTime' => $this->createdOverTime($filters),
            'recentlyClosed' => $this->recentlyClosed($filters),
            'highPriorityOpen' => $this->highPriorityOpen($filters),
            'slaMetrics' => $sla->report($filters),
        ]);
    }

    public function export(ReportFilterRequest $request): StreamedResponse
    {
        $this->authorize('viewAny', User::class);
        $filters = $request->validated();
        $rows = $this->ticketRows($filters);

        return response()->streamDownload(function () use ($rows): void {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, ['Ticket number', 'Status', 'Priority', 'Category', 'Department', 'Current support agent', 'Created at', 'Resolved at', 'Closed at']);
            foreach ($rows->cursor() as $row) {
                fputcsv($stream, array_map(fn ($value) => $this->safeCsv($value), [
                    $row->ticket_number, $row->status, $row->priority, $row->category, $row->department,
                    $row->agent ?? 'Unassigned', $row->created_at, $row->resolved_at, $row->closed_at,
                ]));
            }
            fclose($stream);
        }, 'servicedesk-ticket-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function grouped(array $filters, string $group, string $order)
    {
        return $this->ticketRows($filters, false)
            ->selectRaw("{$group} as label, COUNT(tickets.id) as total")
            ->groupBy($group)->orderBy($order)->get();
    }

    private function agentTotals(array $filters)
    {
        return $this->ticketRows($filters, false)
            ->leftJoin('ticket_assignments as current_assignment', function ($join): void {
                $join->on('current_assignment.ticket_id', '=', 'tickets.id')->whereNull('current_assignment.unassigned_at');
            })
            ->leftJoin('users as agents', 'agents.id', '=', 'current_assignment.assigned_to')
            ->selectRaw("COALESCE(agents.name, 'Unassigned') as label, COUNT(DISTINCT tickets.id) as total")
            ->groupBy('agents.id', 'agents.name')->orderBy('label')->get();
    }

    private function createdOverTime(array $filters)
    {
        return $this->ticketRows($filters, false)
            ->selectRaw('DATE(tickets.created_at) as label, COUNT(*) as total')
            ->groupByRaw('DATE(tickets.created_at)')->orderBy('label')->get();
    }

    private function recentlyClosed(array $filters)
    {
        return $this->ticketRows($filters)
            ->whereIn('ticket_statuses.slug', [TicketStatusSlug::Resolved->value, TicketStatusSlug::Closed->value])
            ->select(['tickets.ticket_number', 'tickets.title', 'ticket_statuses.name as status', 'tickets.resolved_at', 'tickets.closed_at'])
            ->orderByRaw('COALESCE(tickets.closed_at, tickets.resolved_at) DESC')->limit(10)->get();
    }

    private function highPriorityOpen(array $filters)
    {
        return $this->ticketRows($filters)
            ->whereNotIn('ticket_statuses.slug', [TicketStatusSlug::Resolved->value, TicketStatusSlug::Closed->value])
            ->where('ticket_priorities.level', '>=', TicketPriorityLevel::High->value)
            ->select(['tickets.ticket_number', 'tickets.title', 'ticket_statuses.name as status', 'ticket_priorities.name as priority', 'departments.name as department'])
            ->latest('tickets.created_at')->limit(15)->get();
    }

    private function ticketRows(array $filters, bool $selectColumns = true): Builder
    {
        $query = Ticket::query()
            ->join('ticket_statuses', 'ticket_statuses.id', '=', 'tickets.status_id')
            ->join('ticket_priorities', 'ticket_priorities.id', '=', 'tickets.priority_id')
            ->join('ticket_categories', 'ticket_categories.id', '=', 'tickets.category_id')
            ->join('departments', 'departments.id', '=', 'tickets.department_id')
            ->leftJoin('ticket_assignments as current_for_report', function ($join): void {
                $join->on('current_for_report.ticket_id', '=', 'tickets.id')->whereNull('current_for_report.unassigned_at');
            })
            ->leftJoin('users as report_agent', 'report_agent.id', '=', 'current_for_report.assigned_to')
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->where('tickets.created_at', '>=', Carbon::parse($date)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->where('tickets.created_at', '<=', Carbon::parse($date)->endOfDay()));

        if ($selectColumns) {
            $query->select([
                'tickets.id', 'tickets.ticket_number', 'tickets.title', 'tickets.created_at', 'tickets.resolved_at', 'tickets.closed_at',
                'ticket_statuses.name as status', 'ticket_statuses.slug as status_slug', 'ticket_statuses.sort_order as status_order',
                'ticket_priorities.name as priority', 'ticket_priorities.level as priority_level',
                'ticket_categories.name as category', 'departments.name as department', 'report_agent.name as agent',
            ]);
        }

        return $query;
    }

    private function safeCsv(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[\s]*[=+@\-\t\r]/u', $value) === 1 ? "'{$value}" : $value;
    }
}
