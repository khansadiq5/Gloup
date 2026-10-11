<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case NO_SHOW = 'no_show';

    public function isActive(): bool
    {
        return in_array($this, [self::PENDING, self::CONFIRMED], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::CANCELLED, self::NO_SHOW], true);
    }

    public function canTransitionTo(self $newStatus): bool
    {
        $transitions = [
            self::PENDING->value => [self::CONFIRMED, self::CANCELLED],
            self::CONFIRMED->value => [self::COMPLETED, self::CANCELLED, self::NO_SHOW],
            self::COMPLETED->value => [],
            self::CANCELLED->value => [],
            self::NO_SHOW->value => [],
        ];

        return in_array($newStatus, $transitions[$this->value] ?? [], true);
    }
}
