<?php

namespace App\Enums;

enum PaymentGateway: string
{
    case RAZORPAY = 'razorpay';
    case CASHFREE = 'cashfree';
    case STRIPE = 'stripe';

    public function label(): string
    {
        return match ($this) {
            self::RAZORPAY => 'Razorpay',
            self::CASHFREE => 'Cashfree',
            self::STRIPE => 'Stripe',
        };
    }

    public function supportsRefunds(): bool
    {
        return true;
    }

    public function supportsWebhooks(): bool
    {
        return true;
    }
}
