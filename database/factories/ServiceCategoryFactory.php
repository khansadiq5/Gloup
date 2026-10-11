<?php

namespace Database\Factories;

use App\Models\ServiceCategory;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ServiceCategory>
 */
class ServiceCategoryFactory extends Factory
{
    protected $model = ServiceCategory::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Haircut & Styling',
            'Color & Highlights',
            'Facial & Skincare',
            'Massage & Spa',
            'Beard & Shaving',
            'Nail Care',
        ]);

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(3),
            'description' => fake()->sentence(),
            'sort_order' => fake()->numberBetween(1, 10),
            'is_active' => true,
        ];
    }
}
