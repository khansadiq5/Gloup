<?php

namespace App\Policies;

use App\Enums\TenantRole;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionEntitlementService;

class BranchPolicy
{
    public function __construct(
        protected SubscriptionEntitlementService $entitlementService
    ) {}

    public function view(User $user, Branch $branch): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->belongsToTenant($branch->tenant_id);
    }

    public function create(User $user, Tenant $tenant): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        // Must be owner or admin of this tenant
        if (! $user->hasTenantRole($tenant, [TenantRole::OWNER, TenantRole::ADMIN])) {
            return false;
        }

        // Must satisfy plan branch limit entitlement
        return $this->entitlementService->canCreateBranch($tenant);
    }

    public function update(User $user, Branch $branch): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->hasTenantRole($branch->tenant_id, [TenantRole::OWNER, TenantRole::ADMIN, TenantRole::MANAGER]);
    }

    public function delete(User $user, Branch $branch): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        // Only owner or admin can delete branches
        return $user->hasTenantRole($branch->tenant_id, [TenantRole::OWNER, TenantRole::ADMIN]);
    }
}
