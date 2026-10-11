<?php

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->company().' Salon';

        return [
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(4),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'status' => TenantStatus::ACTIVE,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => [
            'status' => TenantStatus::SUSPENDED,
        ]);
    }
}
