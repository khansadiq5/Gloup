<?php

namespace App\Actions\Auth;

use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterSalonOwnerAction
{
    /**
     * @param  array{name: string, email: string, password: string, phone?: ?string, salon_name: string, salon_slug?: ?string}  $data
     * @return array{user: User, tenant: Tenant, membership: TenantUser}
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => strtolower(trim($data['email'])),
                'password' => Hash::make($data['password']),
                'phone' => $data['phone'] ?? null,
            ]);

            $slug = ! empty($data['salon_slug'])
                ? Str::slug($data['salon_slug'])
                : Str::slug($data['salon_name']);

            // Ensure unique slug
            $baseSlug = $slug;
            $counter = 1;
            while (Tenant::where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }

            $tenant = Tenant::create([
                'uuid' => (string) Str::uuid(),
                'name' => $data['salon_name'],
                'slug' => $slug,
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'status' => TenantStatus::ACTIVE,
            ]);

            $membership = TenantUser::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'role' => TenantRole::OWNER,
                'is_active' => true,
            ]);

            return [
                'user' => $user,
                'tenant' => $tenant,
                'membership' => $membership,
            ];
        });
    }
}
