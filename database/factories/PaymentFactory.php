<?php

namespace Database\Factories;

use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Branch;
use App\Models\CustomerProfile;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => Branch::factory(),
            'appointment_id' => null,
            'customer_profile_id' => CustomerProfile::factory(),
            'gateway' => PaymentGateway::RAZORPAY,
            'gateway_payment_id' => 'pay_'.Str::random(12),
            'gateway_order_id' => 'order_'.Str::random(12),
            'method' => PaymentMethod::CARD,
            'amount_inr' => 1000,
            'amount_paid_inr' => 0,
            'currency' => 'INR',
            'status' => PaymentStatus::PENDING,
            'gateway_response' => null,
            'metadata' => null,
            'paid_at' => null,
            'refunded_at' => null,
            'refunded_amount_inr' => 0,
            'failure_reason' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::PENDING,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::PAID,
            'amount_paid_inr' => fn (array $attributes) => $attributes['amount_inr'],
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::FAILED,
            'failure_reason' => 'Payment declined',
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::REFUNDED,
            'amount_paid_inr' => $attributes['amount_inr'],
            'refunded_at' => now(),
            'refunded_amount_inr' => $attributes['amount_inr'],
        ]);
    }

    public function payAtSalon(): static
    {
        return $this->state(fn () => [
            'gateway' => PaymentGateway::RAZORPAY,
            'method' => PaymentMethod::PAY_AT_SALON,
            'status' => PaymentStatus::PAY_AT_SALON,
        ]);
    }
}
