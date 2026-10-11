<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\SlotReservation;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SlotReservation>
 */
class SlotReservationFactory extends Factory
{
    protected $model = SlotReservation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => Branch::factory(),
            'staff_id' => Staff::factory(),
            'start_time' => now()->addMinutes(30),
            'end_time' => now()->addMinutes(65),
            'session_token_hash' => hash('sha256', Str::random(40)),
            'expires_at' => now()->addMinutes(10),
        ];
    }
}
