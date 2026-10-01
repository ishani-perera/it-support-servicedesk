<?php

namespace App\Enums;

/**
 * Canonical definition of the built-in ticket priorities.
 *
 * Backed by the numeric `level` stored in `ticket_priorities.level`
 * (higher = more urgent), so ordering never depends on names or row IDs.
 * The seeder reads every attribute from here; application code should look
 * priorities up via `TicketPriority::forLevel(...)`.
 *
 * (Named "...Level" to avoid clashing with the App\Models\TicketPriority model.)
 *
 * SLA values are DEFAULTS that seed the table; admins may tune them later
 * in the database without code changes.
 */
enum TicketPriorityLevel: int
{
    case Low = 1;
    case Medium = 2;
    case High = 3;
    case Critical = 4;

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::Critical => 'Critical',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Low => 'Minor issue or request with no impact on work. Handled in normal queue order.',
            self::Medium => 'Issue affecting a single user with a workaround available.',
            self::High => 'Significant impact on a user or team; no reasonable workaround.',
            self::Critical => 'Business-critical outage or security incident affecting many users.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low => '#22C55E',
            self::Medium => '#EAB308',
            self::High => '#F97316',
            self::Critical => '#DC2626',
        };
    }

    /**
     * Target minutes until first response.
     */
    public function slaResponseMinutes(): int
    {
        return match ($this) {
            self::Low => 480,       // 8 hours
            self::Medium => 240,    // 4 hours
            self::High => 60,       // 1 hour
            self::Critical => 15,   // 15 minutes
        };
    }

    /**
     * Target minutes until resolution.
     */
    public function slaResolutionMinutes(): int
    {
        return match ($this) {
            self::Low => 4320,      // 72 hours
            self::Medium => 2880,   // 48 hours
            self::High => 480,      // 8 hours
            self::Critical => 240,  // 4 hours
        };
    }

    /**
     * @return list<int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
