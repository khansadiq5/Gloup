<?php

namespace App\Enums;

enum LoyaltyRewardType: string
{
    case SERVICE_DISCOUNT = 'service_discount';
    case FREE_SERVICE = 'free_service';
    case PERCENTAGE_DISCOUNT = 'percentage_discount';
    case FIXED_AMOUNT_DISCOUNT = 'fixed_amount_discount';
    case PRODUCT = 'product';

    public function label(): string
    {
        return match ($this) {
            self::SERVICE_DISCOUNT => 'Service Discount',
            self::FREE_SERVICE => 'Free Service',
            self::PERCENTAGE_DISCOUNT => 'Percentage Discount',
            self::FIXED_AMOUNT_DISCOUNT => 'Fixed Amount Discount',
            self::PRODUCT => 'Product',
        };
    }
}
