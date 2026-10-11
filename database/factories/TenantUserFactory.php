<?php

namespace Database\Factories;

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantUser>
 */
class TenantUserFactory extends Factory
{
    protected $model = TenantUser::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'role' => TenantRole::STAFF,
            'is_active' => true,
        ];
    }

    public function owner(): static
    {
        return $this->state(fn () => [
            'role' => TenantRole::OWNER,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role' => TenantRole::ADMIN,
        ]);
    }

    public function manager(): static
    {
        return $this->state(fn () => [
            'role' => TenantRole::MANAGER,
        ]);
    }

    public function staff(): static
    {
        return $this->state(fn () => [
            'role' => TenantRole::STAFF,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
