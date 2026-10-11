<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Staff;
use App\Models\StaffShift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffShift>
 */
class StaffShiftFactory extends Factory
{
    protected $model = StaffShift::class;

    public function definition(): array
    {
        return [
            'staff_id' => Staff::factory(),
            'branch_id' => Branch::factory(),
            'day_of_week' => fake()->numberBetween(0, 6),
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'is_working' => true,
        ];
    }
}
