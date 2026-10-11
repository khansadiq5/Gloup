<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        $name = fake()->city().' Branch';

        return [
            'tenant_id' => Tenant::factory(),
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(3),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'address_line1' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => 'Maharashtra',
            'postal_code' => '400001',
            'country' => 'IN',
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => [
            'is_primary' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
