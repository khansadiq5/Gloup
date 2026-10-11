<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentEvent>
 */
class AppointmentEventFactory extends Factory
{
    protected $model = AppointmentEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'user_id' => User::factory(),
            'event_type' => 'created',
            'from_status' => null,
            'to_status' => 'confirmed',
            'from_payment_status' => null,
            'to_payment_status' => 'pay_at_salon',
            'description' => 'Appointment created',
            'metadata' => null,
        ];
    }
}
