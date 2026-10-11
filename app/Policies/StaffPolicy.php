<?php

namespace App\Policies;

use App\Enums\TenantRole;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;

class StaffPolicy
{
    public function view(User $user, Staff $staff): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->belongsToTenant($staff->tenant_id);
    }

    public function create(User $user, Tenant $tenant): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->hasTenantRole($tenant, [TenantRole::OWNER, TenantRole::ADMIN]);
    }

    public function update(User $user, Staff $staff): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->hasTenantRole($staff->tenant_id, [TenantRole::OWNER, TenantRole::ADMIN, TenantRole::MANAGER]);
    }

    public function delete(User $user, Staff $staff): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->hasTenantRole($staff->tenant_id, [TenantRole::OWNER, TenantRole::ADMIN]);
    }
}
