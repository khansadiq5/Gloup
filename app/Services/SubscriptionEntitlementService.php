<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Exceptions\BranchLimitExceededException;
use App\Models\Branch;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class SubscriptionEntitlementService
{
    /**
     * Get the default Free plan (branch limit = 1).
     */
    public function getDefaultFreePlan(): Plan
    {
        return Plan::firstOrCreate(
            ['slug' => 'free'],
            [
                'name' => 'Free',
                'max_branches' => 1,
                'price_inr' => 0,
                'is_active' => true,
            ]
        );
    }

    /**
     * Get the default Premium plan (branch limit = 10).
     */
    public function getDefaultPremiumPlan(): Plan
    {
        return Plan::firstOrCreate(
            ['slug' => 'premium'],
            [
                'name' => 'Premium',
                'max_branches' => 10,
                'price_inr' => 2999,
                'is_active' => true,
            ]
        );
    }

    /**
     * Get active plan for a tenant, defaulting to Free if no active subscription exists.
     */
    public function getActivePlan(Tenant $tenant): Plan
    {
        $subscription = $tenant->activeSubscription;

        if ($subscription && $subscription->isValid()) {
            return $subscription->plan;
        }

        return $this->getDefaultFreePlan();
    }

    public function getMaxBranches(Tenant $tenant): int
    {
        return $this->getActivePlan($tenant)->max_branches;
    }

    public function getBranchCount(Tenant $tenant): int
    {
        return $tenant->branches()->count();
    }

    public function canCreateBranch(Tenant $tenant): bool
    {
        return $this->getBranchCount($tenant) < $this->getMaxBranches($tenant);
    }

    /**
     * @throws BranchLimitExceededException
     */
    public function ensureCanCreateBranch(Tenant $tenant): void
    {
        if (! $this->canCreateBranch($tenant)) {
            throw new BranchLimitExceededException(
                "Your {$this->getActivePlan($tenant)->name} plan allows a maximum of {$this->getMaxBranches($tenant)} branch(es). Please upgrade to Premium to add more."
            );
        }
    }

    /**
     * Upgrade tenant to a given plan.
     */
    public function upgradeToPlan(Tenant $tenant, Plan $plan): Subscription
    {
        return DB::transaction(function () use ($tenant, $plan) {
            // Cancel current active subscriptions
            $tenant->subscriptions()
                ->where('status', SubscriptionStatus::ACTIVE)
                ->update(['status' => SubscriptionStatus::CANCELLED]);

            $subscription = $tenant->subscriptions()->create([
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::ACTIVE,
                'starts_at' => now(),
            ]);

            // Reactivate inactive branches up to new limit
            $inactiveBranches = $tenant->branches()->where('is_active', false)->get();
            $allowedReactivations = max(0, $plan->max_branches - $tenant->activeBranches()->count());

            foreach ($inactiveBranches->take($allowedReactivations) as $branch) {
                $branch->update(['is_active' => true]);
            }

            return $subscription;
        });
    }

    /**
     * Downgrade tenant to a given plan safely without deleting data.
     */
    public function downgradeToPlan(Tenant $tenant, Plan $plan): Subscription
    {
        return DB::transaction(function () use ($tenant, $plan) {
            $tenant->subscriptions()
                ->where('status', SubscriptionStatus::ACTIVE)
                ->update(['status' => SubscriptionStatus::CANCELLED]);

            $subscription = $tenant->subscriptions()->create([
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::ACTIVE,
                'starts_at' => now(),
            ]);

            // If tenant has more active branches than allowed, deactivate excess branches (never delete!)
            $activeBranches = $tenant->branches()->where('is_active', true)->get();

            if ($activeBranches->count() > $plan->max_branches) {
                // Ensure primary branch stays active
                $primary = $activeBranches->firstWhere('is_primary', true) ?? $activeBranches->first();

                // Non-primary branches exceeding limit are set to inactive
                $excessCount = $activeBranches->count() - $plan->max_branches;
                $nonPrimary = $activeBranches->where('id', '!=', $primary?->id)->take($excessCount);

                foreach ($nonPrimary as $branch) {
                    $branch->update(['is_active' => false]);
                }
            }

            return $subscription;
        });
    }
}
