<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CARD = 'card';
    case UPI = 'upi';
    case NETBANKING = 'netbanking';
    case WALLET = 'wallet';
    case EMI = 'emi';
    case PAY_AT_SALON = 'pay_at_salon';

    public function label(): string
    {
        return match ($this) {
            self::CARD => 'Credit/Debit Card',
            self::UPI => 'UPI',
            self::NETBANKING => 'Net Banking',
            self::WALLET => 'Wallet',
            self::EMI => 'EMI',
            self::PAY_AT_SALON => 'Pay at Salon',
        };
    }

    public function isOnline(): bool
    {
        return $this !== self::PAY_AT_SALON;
    }

    public function requiresGateway(): bool
    {
        return $this !== self::PAY_AT_SALON;
    }
}
