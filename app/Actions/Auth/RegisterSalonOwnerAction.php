<?php

namespace App\Actions\Auth;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\SubscriptionEntitlementService;
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

            // Auto-provision Free plan subscription
            $entitlementService = app(SubscriptionEntitlementService::class);
            $freePlan = $entitlementService->getDefaultFreePlan();
            $tenant->subscriptions()->create([
                'plan_id' => $freePlan->id,
                'status' => SubscriptionStatus::ACTIVE,
                'starts_at' => now(),
            ]);

            // Auto-provision initial primary branch (1 of 1 on Free plan)
            $branch = $tenant->branches()->create([
                'uuid' => (string) Str::uuid(),
                'name' => $tenant->name.' Main',
                'slug' => 'main',
                'phone' => $tenant->phone,
                'email' => $tenant->email,
                'is_active' => true,
                'is_primary' => true,
            ]);

            return [
                'user' => $user,
                'tenant' => $tenant,
                'membership' => $membership,
                'branch' => $branch,
            ];
        });
    }
}
