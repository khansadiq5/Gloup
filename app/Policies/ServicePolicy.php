<?php

namespace App\Policies;

use App\Enums\TenantRole;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;

class ServicePolicy
{
    public function view(User $user, Service $service): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->belongsToTenant($service->tenant_id);
    }

    public function create(User $user, Tenant $tenant): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->hasTenantRole($tenant, [TenantRole::OWNER, TenantRole::ADMIN]);
    }

    public function update(User $user, Service $service): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->hasTenantRole($service->tenant_id, [TenantRole::OWNER, TenantRole::ADMIN]);
    }

    public function delete(User $user, Service $service): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->hasTenantRole($service->tenant_id, [TenantRole::OWNER, TenantRole::ADMIN]);
    }
}
