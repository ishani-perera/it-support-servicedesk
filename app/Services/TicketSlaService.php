<?php

namespace App\Services;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TicketSlaService
{
    /**
     * Resolve response and resolution deadlines using the ticket's current
     * priority targets and existing lifecycle timestamps.
     *
     * @return array{response_due_at: Carbon, resolution_due_at: Carbon, status: string, is_overdue: bool, within_sla: bool, completed_at: Carbon|null, remaining: string}
     */
    public function evaluate(Ticket $ticket, ?Carbon $now = null): array
    {
        $ticket->loadMissing(['priority', 'status']);
        $now ??= now();
        $responseDueAt = $ticket->created_at->copy()->addMinutes($ticket->priority->sla_response_minutes);
        $resolutionDueAt = $ticket->created_at->copy()->addMinutes($ticket->priority->sla_resolution_minutes);
        $completedAt = $ticket->resolved_at ?? $ticket->closed_at;
        $isOverdue = $completedAt
            ? $completedAt->greaterThan($resolutionDueAt)
            : $now->greaterThan($resolutionDueAt);
        $status = $completedAt
            ? ($isOverdue ? 'breached' : 'met')
            : ($isOverdue ? 'overdue' : 'on_track');
        $remaining = $isOverdue
            ? $resolutionDueAt->diffForHumans($completedAt ?? $now, true)
            : $now->diffForHumans($resolutionDueAt, true);

        return [
            'response_due_at' => $responseDueAt,
            'resolution_due_at' => $resolutionDueAt,
            'status' => $status,
            'is_overdue' => $isOverdue,
            'within_sla' => ! $isOverdue,
            'completed_at' => $completedAt,
            'remaining' => $remaining,
        ];
    }

    /**
     * Summarize SLA state in bounded chunks, reusing the same evaluation as
     * ticket pages. Existing priority SLA columns are the configured targets.
     *
     * @param  array{from?: string|null, to?: string|null}  $filters
     * @return array{total: int, within_sla: int, overdue: int, compliance: float, overdue_by_priority: array<string, int>, overdue_by_agent: array<string, int>}
     */
    public function report(array $filters = []): array
    {
        $metrics = [
            'total' => 0,
            'within_sla' => 0,
            'overdue' => 0,
            'overdue_by_priority' => [],
            'overdue_by_agent' => [],
        ];

        $query = Ticket::query()->with(['priority', 'status', 'currentAssignment.assignee'])
            ->when($filters['from'] ?? null, fn (Builder $builder, string $date) => $builder->where('created_at', '>=', Carbon::parse($date)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $builder, string $date) => $builder->where('created_at', '<=', Carbon::parse($date)->endOfDay()));

        $query->chunkById(300, function ($tickets) use (&$metrics): void {
            foreach ($tickets as $ticket) {
                $metrics['total']++;
                $sla = $this->evaluate($ticket);

                if (! $sla['is_overdue']) {
                    $metrics['within_sla']++;

                    continue;
                }

                $metrics['overdue']++;
                $priority = $ticket->priority->name;
                $agent = $ticket->currentAssignment?->assignee?->name ?? 'Unassigned';
                $metrics['overdue_by_priority'][$priority] = ($metrics['overdue_by_priority'][$priority] ?? 0) + 1;
                $metrics['overdue_by_agent'][$agent] = ($metrics['overdue_by_agent'][$agent] ?? 0) + 1;
            }
        });

        ksort($metrics['overdue_by_priority']);
        ksort($metrics['overdue_by_agent']);
        $metrics['compliance'] = $metrics['total'] === 0
            ? 100.0
            : round(($metrics['within_sla'] / $metrics['total']) * 100, 1);

        return $metrics;
    }
}
