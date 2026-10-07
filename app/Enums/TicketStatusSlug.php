<?php

namespace App\Enums;

/**
 * Canonical slugs of the built-in ticket statuses.
 *
 * Statuses are stored in the `ticket_statuses` table (so they can carry
 * colours/ordering and be referenced by foreign key). This enum is the single
 * source of truth for the built-in rows: the seeder reads from it and
 * application code should look statuses up via `TicketStatus::forSlug(...)`
 * instead of using magic strings or numeric IDs.
 *
 * (Named "...Slug" to avoid clashing with the App\Models\TicketStatus model.)
 *
 * Transition rules live in TicketWorkflowService so enum values remain
 * stable identifiers rather than the workflow engine.
 */
enum TicketStatusSlug: string
{
    case Open = 'open';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case WaitingForUser = 'waiting_for_user';
    case Resolved = 'resolved';
    case ItSupportReview = 'it_support_review';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Assigned => 'Assigned',
            self::InProgress => 'In Progress',
            self::WaitingForUser => 'Waiting for User',
            self::Resolved => 'Resolved',
            self::ItSupportReview => 'IT Support Review',
            self::Closed => 'Closed',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Open => 'Ticket has been submitted and is awaiting triage.',
            self::Assigned => 'Ticket has been assigned to IT Support or a Technician.',
            self::InProgress => 'IT Support is actively working on the ticket.',
            self::WaitingForUser => 'IT Support is waiting for a response or action from the requester.',
            self::Resolved => 'A resolution has been provided and is awaiting confirmation.',
            self::ItSupportReview => 'Technician work is complete and awaiting IT Support review.',
            self::Closed => 'Ticket is complete and no further action is required.',
        };
    }

    /**
     * Hex colour used for badges.
     */
    public function color(): string
    {
        return match ($this) {
            self::Open => '#3B82F6',
            self::Assigned => '#8B5CF6',
            self::InProgress => '#F59E0B',
            self::WaitingForUser => '#F97316',
            self::Resolved => '#10B981',
            self::ItSupportReview => '#6366F1',
            self::Closed => '#6B7280',
        };
    }

    /**
     * Display / workflow ordering (1-based).
     */
    public function sortOrder(): int
    {
        return match ($this) {
            self::Open => 1,
            self::Assigned => 2,
            self::InProgress => 3,
            self::WaitingForUser => 4,
            self::Resolved => 5,
            self::ItSupportReview => 6,
            self::Closed => 7,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
