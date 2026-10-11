<?php

namespace App\Enums;

enum LoyaltyVisitStatus: string
{
    case PENDING = 'pending';
    case QUALIFIED = 'qualified';
    case REVERSED = 'reversed';

    public function isQualified(): bool
    {
        return $this === self::QUALIFIED;
    }

    public function canTransitionTo(self $newStatus): bool
    {
        $transitions = [
            self::PENDING => [self::QUALIFIED, self::REVERSED],
            self::QUALIFIED => [self::REVERSED],
            self::REVERSED => [],
        ];

        return in_array($newStatus, $transitions[$this] ?? [], true);
    }
}
