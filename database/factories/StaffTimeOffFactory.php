<?php

namespace Database\Factories;

use App\Models\Staff;
use App\Models\StaffTimeOff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffTimeOff>
 */
class StaffTimeOffFactory extends Factory
{
    protected $model = StaffTimeOff::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'staff_id' => Staff::factory(),
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'reason' => 'Time off',
        ];
    }
}
