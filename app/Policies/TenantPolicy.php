<?php

namespace App\Policies;

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function view(User $user, Tenant $tenant): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->belongsToTenant($tenant);
    }

    public function update(User $user, Tenant $tenant): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->hasTenantRole($tenant, [TenantRole::OWNER, TenantRole::ADMIN]);
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->hasTenantRole($tenant, [TenantRole::OWNER]);
    }

    public function manageMembers(User $user, Tenant $tenant): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->hasTenantRole($tenant, [TenantRole::OWNER]);
    }

    public function viewDashboard(User $user, Tenant $tenant): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->belongsToTenant($tenant);
    }
}
