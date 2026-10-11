<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => 'Free',
            'slug' => 'free',
            'max_branches' => 1,
            'price_inr' => 0,
            'is_active' => true,
        ];
    }

    public function free(): static
    {
        return $this->state(fn () => [
            'name' => 'Free',
            'slug' => 'free',
            'max_branches' => 1,
            'price_inr' => 0,
        ]);
    }

    public function premium(int $maxBranches = 10): static
    {
        return $this->state(fn () => [
            'name' => 'Premium',
            'slug' => 'premium',
            'max_branches' => $maxBranches,
            'price_inr' => 2999,
        ]);
    }
}
