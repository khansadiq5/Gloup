<?php

namespace App\Enums;

enum LoyaltyRedemptionStatus: string
{
    case PENDING = 'pending';
    case REDEEMED = 'redeemed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function isActive(): bool
    {
        return $this === self::REDEEMED;
    }

    public function canTransitionTo(self $newStatus): bool
    {
        $transitions = [
            self::PENDING => [self::REDEEMED, self::CANCELLED],
            self::REDEEMED => [self::REFUNDED],
            self::CANCELLED => [],
            self::REFUNDED => [],
        ];

        return in_array($newStatus, $transitions[$this] ?? [], true);
    }
}
