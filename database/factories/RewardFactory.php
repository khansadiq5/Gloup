<?php

namespace Database\Factories;

use App\Enums\LoyaltyRewardType;
use App\Models\LoyaltyProgram;
use App\Models\Reward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reward>
 */
class RewardFactory extends Factory
{
    protected $model = Reward::class;

    public function definition(): array
    {
        return [
            'loyalty_program_id' => LoyaltyProgram::factory(),
            'name' => 'Free Haircut',
            'description' => 'Get a free haircut after 10 visits',
            'type' => LoyaltyRewardType::FREE_SERVICE,
            'config' => ['service_id' => null],
            'required_visits' => 10,
            'is_active' => true,
            'expiry_days' => 365,
        ];
    }
}
