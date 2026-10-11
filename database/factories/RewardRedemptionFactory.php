<?php

namespace Database\Factories;

use App\Enums\LoyaltyRedemptionStatus;
use App\Models\Branch;
use App\Models\CustomerProfile;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\Staff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RewardRedemption>
 */
class RewardRedemptionFactory extends Factory
{
    protected $model = RewardRedemption::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'customer_profile_id' => CustomerProfile::factory(),
            'reward_id' => Reward::factory(),
            'redeemed_by_staff_id' => Staff::factory(),
            'status' => LoyaltyRedemptionStatus::REDEEMED,
            'metadata' => null,
            'redeemed_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => LoyaltyRedemptionStatus::PENDING,
            'redeemed_at' => null,
        ]);
    }

    public function redeemed(): static
    {
        return $this->state(fn () => [
            'status' => LoyaltyRedemptionStatus::REDEEMED,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => LoyaltyRedemptionStatus::CANCELLED,
            'redeemed_at' => null,
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn () => [
            'status' => LoyaltyRedemptionStatus::REFUNDED,
        ]);
    }
}
