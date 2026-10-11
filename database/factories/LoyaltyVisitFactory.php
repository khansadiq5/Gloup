<?php

namespace Database\Factories;

use App\Enums\LoyaltyVisitStatus;
use App\Models\Branch;
use App\Models\CustomerProfile;
use App\Models\LoyaltyVisit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyVisit>
 */
class LoyaltyVisitFactory extends Factory
{
    protected $model = LoyaltyVisit::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'customer_profile_id' => CustomerProfile::factory(),
            'source_type' => 'appointment',
            'source_id' => 1,
            'status' => LoyaltyVisitStatus::QUALIFIED,
            'visit_number' => 1,
            'metadata' => null,
        ];
    }

    public function qualified(): static
    {
        return $this->state(fn () => [
            'status' => LoyaltyVisitStatus::QUALIFIED,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => LoyaltyVisitStatus::PENDING,
        ]);
    }

    public function reversed(): static
    {
        return $this->state(fn () => [
            'status' => LoyaltyVisitStatus::REVERSED,
        ]);
    }
}
