<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\LoyaltyRedemptionStatus;
use App\Enums\LoyaltyRewardType;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CustomerProfile;
use App\Models\LoyaltyProgram;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\Tenant;
use App\Services\LoyaltyService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected LoyaltyService $loyaltyService
    ) {}

    /**
     * Get loyalty program for current branch.
     */
    public function getProgram(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('viewDashboard', $tenant);

        $branch = $this->getCurrentBranch($request, $tenant);

        if (! $branch) {
            return response()->json([
                'message' => 'No active branch found.',
            ], 404);
        }

        $program = $this->loyaltyService->getProgramForBranch($branch->id);

        if (! $program) {
            return response()->json([
                'has_program' => false,
            ]);
        }

        $program->load('rewards');

        return response()->json([
            'has_program' => true,
            'program' => [
                'id' => $program->id,
                'name' => $program->name,
                'description' => $program->description,
                'visit_threshold' => $program->visit_threshold,
                'reward_expiry_days' => $program->reward_expiry_days,
                'is_active' => $program->is_active,
                'rewards' => $program->rewards->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'description' => $r->description,
                    'type' => $r->type->value,
                    'config' => $r->config,
                    'required_visits' => $r->required_visits,
                    'is_active' => $r->is_active,
                    'expiry_days' => $r->expiry_days,
                ]),
            ],
        ]);
    }

    /**
     * Create or update loyalty program for branch.
     */
    public function upsertProgram(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('viewDashboard', $tenant);

        $branch = $this->getCurrentBranch($request, $tenant);

        if (! $branch) {
            return response()->json([
                'message' => 'No active branch found.',
            ], 404);
        }

        // Only owners and admins can manage loyalty programs
        $user = $request->user();
        if (! $user->hasTenantRole($tenant, ['owner', 'admin'])) {
            return response()->json([
                'message' => 'Insufficient permissions.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'visit_threshold' => 'required|integer|min:1|max:1000',
            'is_active' => 'boolean',
            'reward_expiry_days' => 'nullable|integer|min:1',
            'reward_config' => 'nullable|array',
        ]);

        $program = $this->loyaltyService->upsertProgram($branch->id, $validated);

        return response()->json([
            'message' => 'Loyalty program updated successfully.',
            'program' => $program,
        ]);
    }

    /**
     * Get customer loyalty progress.
     */
    public function getCustomerProgress(Request $request, Tenant $tenant, CustomerProfile $customerProfile): JsonResponse
    {
        $this->authorize('viewDashboard', $tenant);

        $branch = $this->getCurrentBranch($request, $tenant);

        if (! $branch) {
            return response()->json([
                'message' => 'No active branch found.',
            ], 404);
        }

        // Verify customer belongs to this tenant
        if ($customerProfile->tenant_id !== $tenant->id) {
            return response()->json([
                'message' => 'Customer not found.',
            ], 404);
        }

        $progress = $this->loyaltyService->getCustomerProgress($branch->id, $customerProfile->id);

        return response()->json($progress);
    }

    /**
     * Get customer's redemptions.
     */
    public function getCustomerRedemptions(Request $request, Tenant $tenant, CustomerProfile $customerProfile): JsonResponse
    {
        $this->authorize('viewDashboard', $tenant);

        $branch = $this->getCurrentBranch($request, $tenant);

        if (! $branch) {
            return response()->json([
                'message' => 'No active branch found.',
            ], 404);
        }

        if ($customerProfile->tenant_id !== $tenant->id) {
            return response()->json([
                'message' => 'Customer not found.',
            ], 404);
        }

        $redemptions = $this->loyaltyService->getCustomerRedemptions($branch->id, $customerProfile->id);

        return response()->json([
            'redemptions' => $redemptions->map(fn ($r) => [
                'id' => $r->id,
                'reward' => [
                    'id' => $r->reward->id,
                    'name' => $r->reward->name,
                    'type' => $r->reward->type->value,
                    'config' => $r->reward->config,
                ],
                'status' => $r->status->value,
                'redeemed_at' => $r->redeemed_at?->toIso8601String(),
                'redeemed_by' => $r->redeemedByStaff ? [
                    'id' => $r->redeemedByStaff->id,
                    'name' => $r->redeemedByStaff->name,
                ] : null,
            ]),
        ]);
    }

    /**
     * Redeem a reward for a customer.
     */
    public function redeemReward(Request $request, Tenant $tenant, CustomerProfile $customerProfile): JsonResponse
    {
        $this->authorize('viewDashboard', $tenant);

        $branch = $this->getCurrentBranch($request, $tenant);

        if (! $branch) {
            return response()->json([
                'message' => 'No active branch found.',
            ], 404);
        }

        if ($customerProfile->tenant_id !== $tenant->id) {
            return response()->json([
                'message' => 'Customer not found.',
            ], 404);
        }

        $validated = $request->validate([
            'reward_id' => 'required|exists:rewards,id',
        ]);

        try {
            $redemption = $this->loyaltyService->redeemReward(
                $branch->id,
                $customerProfile->id,
                $validated['reward_id'],
                $request->user()->id
            );

            return response()->json([
                'message' => 'Reward redeemed successfully.',
                'redemption' => [
                    'id' => $redemption->id,
                    'reward' => [
                        'id' => $redemption->reward->id,
                        'name' => $redemption->reward->name,
                        'type' => $redemption->reward->type->value,
                        'config' => $redemption->reward->config,
                    ],
                    'status' => $redemption->status->value,
                    'redeemed_at' => $redemption->redeemed_at?->toIso8601String(),
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 422);
        }
    }

    /**
     * Cancel a pending redemption.
     */
    public function cancelRedemption(Request $request, Tenant $tenant, RewardRedemption $redemption): JsonResponse
    {
        $this->authorize('viewDashboard', $tenant);

        $branch = $this->getCurrentBranch($request, $tenant);

        if (! $branch || $redemption->branch_id !== $branch->id) {
            return response()->json([
                'message' => 'Redemption not found.',
            ], 404);
        }

        if ($redemption->status !== LoyaltyRedemptionStatus::PENDING) {
            return response()->json([
                'message' => 'Only pending redemptions can be cancelled.',
            ], 422);
        }

        $redemption = $this->loyaltyService->cancelRedemption($redemption->id, $request->user()->id);

        return response()->json([
            'message' => 'Redemption cancelled successfully.',
            'redemption' => $redemption,
        ]);
    }

    /**
     * Refund a redeemed reward.
     */
    public function refundRedemption(Request $request, Tenant $tenant, RewardRedemption $redemption): JsonResponse
    {
        $this->authorize('viewDashboard', $tenant);

        $branch = $this->getCurrentBranch($request, $tenant);

        if (! $branch || $redemption->branch_id !== $branch->id) {
            return response()->json([
                'message' => 'Redemption not found.',
            ], 404);
        }

        if ($redemption->status !== LoyaltyRedemptionStatus::REDEEMED) {
            return response()->json([
                'message' => 'Only redeemed rewards can be refunded.',
            ], 422);
        }

        $redemption = $this->loyaltyService->refundRedemption($redemption->id, $request->user()->id);

        return response()->json([
            'message' => 'Reward refunded successfully.',
            'redemption' => $redemption,
        ]);
    }

    /**
     * Get all rewards for the branch's loyalty program.
     */
    public function getRewards(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('viewDashboard', $tenant);

        $branch = $this->getCurrentBranch($request, $tenant);

        if (! $branch) {
            return response()->json([
                'message' => 'No active branch found.',
            ], 404);
        }

        $program = LoyaltyProgram::where('branch_id', $branch->id)
            ->where('is_active', true)
            ->first();

        if (! $program) {
            return response()->json([
                'rewards' => [],
            ]);
        }

        $rewards = $program->rewards()
            ->where('is_active', true)
            ->get();

        return response()->json([
            'rewards' => $rewards->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'description' => $r->description,
                'type' => $r->type->value,
                'config' => $r->config,
                'required_visits' => $r->required_visits,
                'is_active' => $r->is_active,
                'expiry_days' => $r->expiry_days,
            ]),
        ]);
    }

    /**
     * Create a new reward.
     */
    public function createReward(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('viewDashboard', $tenant);

        $branch = $this->getCurrentBranch($request, $tenant);

        if (! $branch) {
            return response()->json([
                'message' => 'No active branch found.',
            ], 404);
        }

        // Only owners and admins
        $user = $request->user();
        if (! $user->hasTenantRole($tenant, ['owner', 'admin'])) {
            return response()->json([
                'message' => 'Insufficient permissions.',
            ], 403);
        }

        $program = LoyaltyProgram::where('branch_id', $branch->id)
            ->where('is_active', true)
            ->first();

        if (! $program) {
            return response()->json([
                'message' => 'No active loyalty program for this branch.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:'.implode(',', array_column(LoyaltyRewardType::cases(), 'value')),
            'config' => 'nullable|array',
            'required_visits' => 'required|integer|min:1|max:1000',
            'is_active' => 'boolean',
            'expiry_days' => 'nullable|integer|min:1',
        ]);

        $reward = Reward::create([
            'loyalty_program_id' => $program->id,
            ...$validated,
        ]);

        return response()->json([
            'message' => 'Reward created successfully.',
            'reward' => $reward,
        ], 201);
    }

    /**
     * Update a reward.
     */
    public function updateReward(Request $request, Tenant $tenant, Reward $reward): JsonResponse
    {
        $this->authorize('viewDashboard', $tenant);

        $branch = $this->getCurrentBranch($request, $tenant);

        if (! $branch || $reward->loyaltyProgram->branch_id !== $branch->id) {
            return response()->json([
                'message' => 'Reward not found.',
            ], 404);
        }

        $user = $request->user();
        if (! $user->hasTenantRole($tenant, ['owner', 'admin'])) {
            return response()->json([
                'message' => 'Insufficient permissions.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|required|in:'.implode(',', array_column(LoyaltyRewardType::cases(), 'value')),
            'config' => 'nullable|array',
            'required_visits' => 'sometimes|required|integer|min:1|max:1000',
            'is_active' => 'boolean',
            'expiry_days' => 'nullable|integer|min:1',
        ]);

        $reward->update($validated);

        return response()->json([
            'message' => 'Reward updated successfully.',
            'reward' => $reward->fresh(),
        ]);
    }

    /**
     * Delete a reward.
     */
    public function deleteReward(Request $request, Tenant $tenant, Reward $reward): JsonResponse
    {
        $this->authorize('viewDashboard', $tenant);

        $branch = $this->getCurrentBranch($request, $tenant);

        if (! $branch || $reward->loyaltyProgram->branch_id !== $branch->id) {
            return response()->json([
                'message' => 'Reward not found.',
            ], 404);
        }

        $user = $request->user();
        if (! $user->hasTenantRole($tenant, ['owner', 'admin'])) {
            return response()->json([
                'message' => 'Insufficient permissions.',
            ], 403);
        }

        $reward->delete();

        return response()->json([
            'message' => 'Reward deleted successfully.',
        ]);
    }

    /**
     * Get current branch from session or default.
     */
    protected function getCurrentBranch(Request $request, Tenant $tenant): ?Branch
    {
        $branchId = $request->session()->get("tenant_{$tenant->id}_branch_id");

        if ($branchId) {
            return $tenant->branches()->where('id', $branchId)->where('is_active', true)->first();
        }

        return $tenant->primaryBranch;
    }
}
