<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\LoyaltyProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyProgram>
 */
class LoyaltyProgramFactory extends Factory
{
    protected $model = LoyaltyProgram::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'name' => 'Loyalty Program',
            'description' => 'Earn visits and get rewards',
            'visit_threshold' => 10,
            'is_active' => true,
            'reward_expiry_days' => 365,
            'reward_config' => null,
        ];
    }
}
