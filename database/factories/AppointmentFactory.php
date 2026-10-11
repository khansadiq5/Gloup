<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\CustomerProfile;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

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
            'customer_profile_id' => CustomerProfile::factory(),
            'staff_id' => Staff::factory(),
            'status' => AppointmentStatus::CONFIRMED->value,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHour()->addMinutes(35),
            'total_price_inr' => 1000,
            'total_duration_minutes' => 35,
            'notes' => null,
            'payment_status' => PaymentStatus::PAY_AT_SALON->value,
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the appointment is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::PENDING->value,
        ]);
    }

    /**
     * Indicate that the appointment is confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::CONFIRMED->value,
        ]);
    }

    /**
     * Indicate that the appointment is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::COMPLETED->value,
        ]);
    }

    /**
     * Indicate that the appointment is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::CANCELLED->value,
        ]);
    }

    /**
     * Indicate that the appointment is no-show.
     */
    public function noShow(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::NO_SHOW->value,
        ]);
    }
}
