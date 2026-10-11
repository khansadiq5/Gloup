<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        $name = fake()->words(2, true).' Service';

        return [
            'tenant_id' => Tenant::factory(),
            'category_id' => null,
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(3),
            'description' => fake()->sentence(),
            'duration_minutes' => fake()->randomElement([30, 45, 60, 90]),
            'buffer_minutes' => fake()->randomElement([0, 5, 10, 15]),
            'price_inr' => fake()->randomElement([500, 800, 1200, 1500, 2500]),
            'is_active' => true,
        ];
    }
}
