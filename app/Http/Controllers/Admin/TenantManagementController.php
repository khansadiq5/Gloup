<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\SubscriptionEntitlementService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantManagementController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected SubscriptionEntitlementService $entitlementService
    ) {}

    /**
     * List all tenants with filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAdminDashboard');

        $query = Tenant::with(['owner', 'primaryBranch', 'activeSubscription.plan']);

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('owner', fn ($q) => $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('plan')) {
            $query->whereHas('activeSubscription', fn ($q) => $q->where('plan_id', $request->plan));
        }

        if ($request->filled('subscription_status')) {
            $query->whereHas('activeSubscription', fn ($q) => $q->where('status', $request->subscription_status));
        }

        // Sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $allowedSortFields = ['name', 'slug', 'status', 'created_at', 'email'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        }

        $tenants = $query->paginate($request->get('per_page', 20))->withQueryString();

        // Transform response
        $tenants->getCollection()->transform(function ($tenant) {
            return [
                'id' => $tenant->id,
                'uuid' => $tenant->uuid,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'status' => $tenant->status->value,
                'created_at' => $tenant->created_at->toIso8601String(),
                'owner' => $tenant->owner ? [
                    'id' => $tenant->owner->id,
                    'name' => $tenant->owner->name,
                    'email' => $tenant->owner->email,
                ] : null,
                'primary_branch' => $tenant->primaryBranch ? [
                    'id' => $tenant->primaryBranch->id,
                    'name' => $tenant->primaryBranch->name,
                    'city' => $tenant->primaryBranch->city,
                ] : null,
                'subscription' => $tenant->activeSubscription ? [
                    'id' => $tenant->activeSubscription->id,
                    'plan' => [
                        'id' => $tenant->activeSubscription->plan->id,
                        'name' => $tenant->activeSubscription->plan->name,
                        'slug' => $tenant->activeSubscription->plan->slug,
                    ],
                    'status' => $tenant->activeSubscription->status->value,
                    'starts_at' => $tenant->activeSubscription->starts_at?->toIso8601String(),
                    'ends_at' => $tenant->activeSubscription->ends_at?->toIso8601String(),
                ] : null,
                'branches_count' => $tenant->branches()->count(),
                'active_branches_count' => $tenant->activeBranches()->count(),
            ];
        });

        return response()->json([
            'data' => $tenants->items(),
            'pagination' => [
                'current_page' => $tenants->currentPage(),
                'last_page' => $tenants->lastPage(),
                'per_page' => $tenants->perPage(),
                'total' => $tenants->total(),
            ],
        ]);
    }

    /**
     * Show detailed tenant information.
     */
    public function show(Tenant $tenant): JsonResponse
    {
        $this->authorize('viewAdminDashboard');

        $tenant->load([
            'owner',
            'branches',
            'activeSubscription.plan',
            'subscriptions.plan',
        ]);

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'uuid' => $tenant->uuid,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'description' => $tenant->description,
                'bio' => $tenant->bio,
                'status' => $tenant->status->value,
                'created_at' => $tenant->created_at->toIso8601String(),
                'updated_at' => $tenant->updated_at->toIso8601String(),
                'owner' => $tenant->owner ? [
                    'id' => $tenant->owner->id,
                    'name' => $tenant->owner->name,
                    'email' => $tenant->owner->email,
                    'phone' => $tenant->owner->phone,
                ] : null,
                'branches' => $tenant->branches->map(function ($branch) {
                    return [
                        'id' => $branch->id,
                        'name' => $branch->name,
                        'slug' => $branch->slug,
                        'city' => $branch->city,
                        'is_active' => $branch->is_active,
                        'is_primary' => $branch->is_primary,
                    ];
                }),
                'subscription' => $tenant->activeSubscription ? [
                    'id' => $tenant->activeSubscription->id,
                    'plan' => [
                        'id' => $tenant->activeSubscription->plan->id,
                        'name' => $tenant->activeSubscription->plan->name,
                        'slug' => $tenant->activeSubscription->plan->slug,
                        'max_branches' => $tenant->activeSubscription->plan->max_branches,
                    ],
                    'status' => $tenant->activeSubscription->status->value,
                    'starts_at' => $tenant->activeSubscription->starts_at?->toIso8601String(),
                    'ends_at' => $tenant->activeSubscription->ends_at?->toIso8601String(),
                ] : null,
                'all_subscriptions' => $tenant->subscriptions->map(function ($sub) {
                    return [
                        'id' => $sub->id,
                        'plan' => [
                            'id' => $sub->plan->id,
                            'name' => $sub->plan->name,
                            'slug' => $sub->plan->slug,
                        ],
                        'status' => $sub->status->value,
                        'starts_at' => $sub->starts_at?->toIso8601String(),
                        'ends_at' => $sub->ends_at?->toIso8601String(),
                        'trial_ends_at' => $sub->trial_ends_at?->toIso8601String(),
                    ];
                }),
            ],
        ]);
    }

    /**
     * Update tenant status (activate/suspend).
     */
    public function updateStatus(Tenant $tenant, Request $request): JsonResponse
    {
        $this->authorize('viewAdminDashboard');

        $validated = $request->validate([
            'status' => ['required', Rule::in([TenantStatus::ACTIVE->value, TenantStatus::SUSPENDED->value])],
        ]);

        $oldStatus = $tenant->status;

        if ($oldStatus->value === $validated['status']) {
            return response()->json([
                'message' => 'Status unchanged.',
            ]);
        }

        $tenant->update(['status' => $validated['status']]);

        // Log audit event
        Tenant::where('id', $tenant->id)->first()->recordEvent(
            'status_changed',
            $oldStatus->value,
            $tenant->status->value,
            null,
            null,
            "Tenant status changed from {$oldStatus->value} to {$tenant->status->value}",
            ['changed_by' => auth()->id()],
            auth()->id()
        );

        return response()->json([
            'message' => 'Tenant status updated successfully.',
            'tenant' => [
                'id' => $tenant->id,
                'status' => $tenant->status->value,
            ],
        ]);
    }

    /**
     * Upgrade/downgrade tenant subscription.
     */
    public function changePlan(Tenant $tenant, Request $request): JsonResponse
    {
        $this->authorize('viewAdminDashboard');

        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);

        try {
            if ($plan->isFree()) {
                $this->entitlementService->downgradeToPlan($tenant, $plan);
            } else {
                $this->entitlementService->upgradeToPlan($tenant, $plan);
            }

            $tenant->load('activeSubscription.plan');

            return response()->json([
                'message' => 'Subscription plan updated successfully.',
                'subscription' => $tenant->activeSubscription ? [
                    'id' => $tenant->activeSubscription->id,
                    'plan' => [
                        'id' => $tenant->activeSubscription->plan->id,
                        'name' => $tenant->activeSubscription->plan->name,
                        'slug' => $tenant->activeSubscription->plan->slug,
                    ],
                    'status' => $tenant->activeSubscription->status->value,
                ] : null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Cancel tenant subscription.
     */
    public function cancelSubscription(Tenant $tenant, Request $request): JsonResponse
    {
        $this->authorize('viewAdminDashboard');

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $tenant->subscriptions()
            ->where('status', SubscriptionStatus::ACTIVE)
            ->update([
                'status' => SubscriptionStatus::CANCELLED,
                'metadata' => array_merge(
                    Subscription::find(request()->route('tenant'))?->activeSubscription?->metadata ?? [],
                    ['cancelled_by_admin' => auth()->id(), 'reason' => $validated['reason'] ?? null]
                ),
            ]);

        // Downgrade to free plan
        $freePlan = $this->entitlementService->getDefaultFreePlan();
        $this->entitlementService->downgradeToPlan($tenant, $freePlan);

        return response()->json([
            'message' => 'Subscription cancelled and downgraded to Free plan.',
        ]);
    }

    /**
     * Extend trial for a tenant.
     */
    public function extendTrial(Tenant $tenant, Request $request): JsonResponse
    {
        $this->authorize('viewAdminDashboard');

        $validated = $request->validate([
            'days' => 'required|integer|min:1|max:365',
        ]);

        $subscription = $tenant->activeSubscription;

        if (! $subscription) {
            return response()->json([
                'message' => 'No active subscription found.',
            ], 404);
        }

        $newTrialEnd = ($subscription->trial_ends_at ?? now())->addDays($validated['days']);

        $subscription->update([
            'trial_ends_at' => $newTrialEnd,
        ]);

        return response()->json([
            'message' => "Trial extended by {$validated['days']} days.",
            'trial_ends_at' => $newTrialEnd->toIso8601String(),
        ]);
    }

    /**
     * List all plans.
     */
    public function plans(): JsonResponse
    {
        $this->authorize('viewAdminDashboard');

        $plans = Plan::orderBy('price_inr')->get();

        return response()->json([
            'plans' => $plans->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'max_branches' => $plan->max_branches,
                    'price_inr' => $plan->price_inr,
                    'is_active' => $plan->is_active,
                    'is_free' => $plan->isFree(),
                    'is_premium' => $plan->isPremium(),
                    'subscriptions_count' => $plan->subscriptions()->count(),
                    'active_subscriptions_count' => $plan->subscriptions()->where('status', SubscriptionStatus::ACTIVE)->count(),
                ];
            }),
        ]);
    }
}
