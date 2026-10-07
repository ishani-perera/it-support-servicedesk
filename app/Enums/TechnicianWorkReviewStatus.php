<?php

namespace App\Enums;

enum TechnicianWorkReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case ChangesRequested = 'changes_requested';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting IT Support Review',
            self::Approved => 'Approved',
            self::ChangesRequested => 'Changes requested',
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
