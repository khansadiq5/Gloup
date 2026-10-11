<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case UNPAID = 'unpaid';
    case PAY_AT_SALON = 'pay_at_salon';
    case PENDING = 'pending';
    case PAID = 'paid';
    case REFUNDED = 'refunded';
    case FAILED = 'failed';
    case PARTIALLY_REFUNDED = 'partially_refunded';

    public function isPaid(): bool
    {
        return in_array($this, [self::PAID, self::PARTIALLY_REFUNDED], true);
    }

    public function isRefunded(): bool
    {
        return in_array($this, [self::REFUNDED, self::PARTIALLY_REFUNDED], true);
    }

    public function canTransitionTo(self $newStatus): bool
    {
        $transitions = [
            self::UNPAID->value => [self::PAY_AT_SALON, self::PENDING],
            self::PAY_AT_SALON->value => [self::PAID, self::FAILED],
            self::PENDING->value => [self::PAID, self::FAILED],
            self::PAID->value => [self::REFUNDED, self::PARTIALLY_REFUNDED],
            self::REFUNDED->value => [],
            self::FAILED->value => [self::PENDING, self::PAY_AT_SALON],
            self::PARTIALLY_REFUNDED->value => [self::REFUNDED],
        ];

        return in_array($newStatus, $transitions[$this->value] ?? [], true);
    }
}
