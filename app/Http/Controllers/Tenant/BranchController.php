<?php

namespace App\Http\Controllers\Tenant;

use App\Exceptions\BranchLimitExceededException;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Tenant;
use App\Services\SubscriptionEntitlementService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class BranchController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected SubscriptionEntitlementService $entitlementService
    ) {}

    public function index(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('view', $tenant);

        $branches = $tenant->branches()->get();
        $plan = $this->entitlementService->getActivePlan($tenant);

        return response()->json([
            'branches' => $branches,
            'entitlement' => [
                'plan_name' => $plan->name,
                'plan_slug' => $plan->slug,
                'current_branches' => $branches->count(),
                'active_branches' => $tenant->activeBranches()->count(),
                'max_branches' => $plan->max_branches,
                'can_create_more' => $this->entitlementService->canCreateBranch($tenant),
            ],
        ]);
    }

    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('view', $tenant);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:100', 'alpha_dash'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
        ]);

        try {
            $branch = DB::transaction(function () use ($tenant, $validated) {
                // Lock tenant to prevent race conditions during concurrent branch creations
                Tenant::where('id', $tenant->id)->lockForUpdate()->first();

                // Server-side entitlement check
                $this->entitlementService->ensureCanCreateBranch($tenant);

                $slug = ! empty($validated['slug'])
                    ? Str::slug($validated['slug'])
                    : Str::slug($validated['name']);

                // Ensure unique slug within tenant
                $baseSlug = $slug;
                $counter = 1;
                while ($tenant->branches()->where('slug', $slug)->exists()) {
                    $slug = "{$baseSlug}-{$counter}";
                    $counter++;
                }

                $validated['slug'] = $slug;
                $validated['uuid'] = (string) Str::uuid();
                $validated['is_active'] = true;
                $validated['is_primary'] = $tenant->branches()->count() === 0;

                return $tenant->branches()->create($validated);
            });

            return response()->json([
                'message' => 'Branch created successfully.',
                'branch' => $branch,
            ], Response::HTTP_CREATED);
        } catch (BranchLimitExceededException $e) {
            return response()->json([
                'error' => 'plan_limit_exceeded',
                'message' => $e->getMessage(),
            ], Response::HTTP_FORBIDDEN);
        }
    }

    public function show(Tenant $tenant, Branch $branch): JsonResponse
    {
        if ($branch->tenant_id !== $tenant->id) {
            abort(Response::HTTP_NOT_FOUND, 'Branch not found for this salon.');
        }

        $this->authorize('view', $branch);

        return response()->json([
            'branch' => $branch,
        ]);
    }

    public function update(Request $request, Tenant $tenant, Branch $branch): JsonResponse
    {
        if ($branch->tenant_id !== $tenant->id) {
            abort(Response::HTTP_NOT_FOUND, 'Branch not found for this salon.');
        }

        $this->authorize('update', $branch);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $branch->update($validated);

        return response()->json([
            'message' => 'Branch updated successfully.',
            'branch' => $branch->fresh(),
        ]);
    }

    public function destroy(Tenant $tenant, Branch $branch): JsonResponse
    {
        if ($branch->tenant_id !== $tenant->id) {
            abort(Response::HTTP_NOT_FOUND, 'Branch not found for this salon.');
        }

        $this->authorize('delete', $branch);

        if ($branch->is_primary && $tenant->branches()->count() > 1) {
            return response()->json([
                'message' => 'Cannot delete primary branch. Please designate another primary branch first.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $branch->delete();

        return response()->json([
            'message' => 'Branch deleted successfully.',
        ]);
    }
}
