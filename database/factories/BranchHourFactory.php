<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\BranchHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BranchHour>
 */
class BranchHourFactory extends Factory
{
    protected $model = BranchHour::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'day_of_week' => fake()->numberBetween(0, 6),
            'open_time' => '09:00:00',
            'close_time' => '20:00:00',
            'is_closed' => false,
        ];
    }
}
