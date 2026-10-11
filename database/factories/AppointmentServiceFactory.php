<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentService>
 */
class AppointmentServiceFactory extends Factory
{
    protected $model = AppointmentService::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'service_id' => Service::factory(),
            'price_inr' => 1000,
            'duration_minutes' => 30,
            'buffer_minutes' => 5,
        ];
    }
}
