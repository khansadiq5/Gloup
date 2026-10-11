<?php

namespace Database\Factories;

use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    public function definition(): array
    {
        return [
            'gateway' => 'razorpay',
            'event_type' => 'payment.captured',
            'event_id' => 'evt_'.Str::random(16),
            'payload' => ['test' => 'data'],
            'status' => 'pending',
            'error_message' => null,
            'retry_count' => 0,
            'processed_at' => null,
        ];
    }

    public function processed(): static
    {
        return $this->state(fn () => [
            'status' => 'processed',
            'processed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'error_message' => 'Processing failed',
        ]);
    }

    public function razorpay(): static
    {
        return $this->state(fn () => [
            'gateway' => 'razorpay',
        ]);
    }

    public function cashfree(): static
    {
        return $this->state(fn () => [
            'gateway' => 'cashfree',
        ]);
    }

    public function stripe(): static
    {
        return $this->state(fn () => [
            'gateway' => 'stripe',
        ]);
    }
}
