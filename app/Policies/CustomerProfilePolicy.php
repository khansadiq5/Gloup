<?php

namespace App\Policies;

use App\Enums\TenantRole;
use App\Models\CustomerProfile;
use App\Models\User;

class CustomerProfilePolicy
{
    public function view(User $user, CustomerProfile $profile): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        // The customer themselves can view their own profile
        if ($profile->user_id !== null && $user->id === $profile->user_id) {
            return true;
        }

        // Salon staff can view customer profiles only within their own tenant
        return $user->belongsToTenant($profile->tenant_id);
    }

    public function create(User $user, int $tenantId): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->belongsToTenant($tenantId);
    }

    public function update(User $user, CustomerProfile $profile): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        // Customer can update their own personal info
        if ($profile->user_id !== null && $user->id === $profile->user_id) {
            return true;
        }

        // Salon staff can update customer profiles within their own tenant
        return $user->belongsToTenant($profile->tenant_id);
    }

    public function delete(User $user, CustomerProfile $profile): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->hasTenantRole($profile->tenant_id, [TenantRole::OWNER, TenantRole::ADMIN]);
    }
}
