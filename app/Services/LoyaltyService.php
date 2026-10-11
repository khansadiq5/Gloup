<?php

namespace App\Services;

use App\Enums\LoyaltyRedemptionStatus;
use App\Enums\LoyaltyVisitStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\LoyaltyProgram;
use App\Models\LoyaltyVisit;
use App\Models\Reward;
use App\Models\RewardRedemption;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoyaltyService
{
    /**
     * Process a qualifying visit for loyalty program.
     * Called when an appointment is completed or walk-in is created.
     *
     * @param  string  $sourceType  'appointment' or 'walk_in'
     */
    public function processQualifyingVisit(string $sourceType, int $sourceId, int $branchId, int $customerProfileId): ?LoyaltyVisit
    {
        return DB::transaction(function () use ($sourceType, $sourceId, $branchId, $customerProfileId) {
            // Check if loyalty program exists and is active for this branch
            $program = LoyaltyProgram::where('branch_id', $branchId)
                ->where('is_active', true)
                ->first();

            if (! $program) {
                return null;
            }

            // Validate source exists and belongs to branch
            $this->validateSource($sourceType, $sourceId, $branchId);

            // Check if visit already processed (idempotency)
            $existingVisit = LoyaltyVisit::where('branch_id', $branchId)
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->first();

            if ($existingVisit) {
                Log::info('Loyalty visit already processed', [
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'branch_id' => $branchId,
                ]);

                return $existingVisit;
            }

            // Get customer's current visit count for this branch
            $currentVisits = LoyaltyVisit::where('branch_id', $branchId)
                ->where('customer_profile_id', $customerProfileId)
                ->where('status', LoyaltyVisitStatus::QUALIFIED)
                ->count();

            $visitNumber = $currentVisits + 1;

            // Create the visit record
            $visit = LoyaltyVisit::create([
                'branch_id' => $branchId,
                'customer_profile_id' => $customerProfileId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'status' => LoyaltyVisitStatus::QUALIFIED,
                'visit_number' => $visitNumber,
                'metadata' => [
                    'program_threshold' => $program->visit_threshold,
                    'program_id' => $program->id,
                ],
            ]);

            // Check if customer earned a reward
            $this->checkAndIssueRewards($customerProfileId, $branchId, $visitNumber, $program);

            return $visit;
        });
    }

    /**
     * Process visit when appointment is completed.
     */
    public function processAppointmentCompletion(Appointment $appointment): ?LoyaltyVisit
    {
        return $this->processQualifyingVisit(
            'appointment',
            $appointment->id,
            $appointment->branch_id,
            $appointment->customer_profile_id
        );
    }

    /**
     * Process visit when walk-in is completed.
     */
    public function processWalkInCompletion(int $branchId, int $customerProfileId, int $sourceId): ?LoyaltyVisit
    {
        return $this->processQualifyingVisit(
            'walk_in',
            $sourceId,
            $branchId,
            $customerProfileId
        );
    }

    /**
     * Reverse a qualifying visit (e.g., appointment cancelled/no-show).
     */
    public function reverseVisit(string $sourceType, int $sourceId, int $branchId): bool
    {
        return DB::transaction(function () use ($sourceType, $sourceId, $branchId) {
            $visit = LoyaltyVisit::where('branch_id', $branchId)
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->first();

            if (! $visit) {
                return false;
            }

            if ($visit->status === LoyaltyVisitStatus::REVERSED) {
                return false;
            }

            $customerProfileId = $visit->customer_profile_id;
            $visitNumber = $visit->visit_number;

            // Mark visit as reversed
            $visit->update([
                'status' => LoyaltyVisitStatus::REVERSED,
            ]);

            // Check if any rewards need to be adjusted
            $this->adjustRewardsAfterReversal($customerProfileId, $branchId, $visitNumber);

            return true;
        });
    }

    /**
     * Get customer's loyalty progress for a branch.
     */
    public function getCustomerProgress(int $branchId, int $customerProfileId): array
    {
        $program = LoyaltyProgram::where('branch_id', $branchId)
            ->where('is_active', true)
            ->first();

        if (! $program) {
            return [
                'has_program' => false,
            ];
        }

        $qualifiedVisits = LoyaltyVisit::where('branch_id', $branchId)
            ->where('customer_profile_id', $customerProfileId)
            ->where('status', LoyaltyVisitStatus::QUALIFIED)
            ->count();

        $pendingVisits = LoyaltyVisit::where('branch_id', $branchId)
            ->where('customer_profile_id', $customerProfileId)
            ->where('status', LoyaltyVisitStatus::PENDING)
            ->count();

        $totalVisits = $qualifiedVisits + $pendingVisits;
        $progress = $program->visit_threshold > 0
            ? min(100, round(($qualifiedVisits / $program->visit_threshold) * 100))
            : 0;

        $nextReward = $program->activeRewards()
            ->where('required_visits', '>', $qualifiedVisits)
            ->orderBy('required_visits')
            ->first();

        $visitsUntilNextReward = $nextReward
            ? $nextReward->required_visits - $qualifiedVisits
            : null;

        return [
            'has_program' => true,
            'program' => [
                'id' => $program->id,
                'name' => $program->name,
                'visit_threshold' => $program->visit_threshold,
                'reward_expiry_days' => $program->reward_expiry_days,
            ],
            'qualified_visits' => $qualifiedVisits,
            'pending_visits' => $pendingVisits,
            'total_visits' => $totalVisits,
            'progress_percentage' => $progress,
            'next_reward' => $nextReward ? [
                'id' => $nextReward->id,
                'name' => $nextReward->name,
                'type' => $nextReward->type->value,
                'required_visits' => $nextReward->required_visits,
            ] : null,
            'visits_until_next_reward' => $visitsUntilNextReward,
            'rewards_earned' => $this->getEarnedRewards($branchId, $customerProfileId),
        ];
    }

    /**
     * Get customer's earned but unredeemed rewards.
     */
    protected function getEarnedRewards(int $branchId, int $customerProfileId): array
    {
        $program = LoyaltyProgram::where('branch_id', $branchId)
            ->where('is_active', true)
            ->first();

        if (! $program) {
            return [];
        }

        $qualifiedVisits = LoyaltyVisit::where('branch_id', $branchId)
            ->where('customer_profile_id', $customerProfileId)
            ->where('status', LoyaltyVisitStatus::QUALIFIED)
            ->count();

        $earnedRewards = $program->activeRewards()
            ->where('required_visits', '<=', $qualifiedVisits)
            ->get();

        $redeemedRewards = RewardRedemption::where('branch_id', $branchId)
            ->where('customer_profile_id', $customerProfileId)
            ->whereIn('status', ['pending', 'redeemed'])
            ->pluck('reward_id')
            ->toArray();

        return $earnedRewards->filter(fn ($r) => ! in_array($r->id, $redeemedRewards))
            ->map(function ($reward) {
                return [
                    'id' => $reward->id,
                    'name' => $reward->name,
                    'type' => $reward->type->value,
                    'config' => $reward->config,
                    'required_visits' => $reward->required_visits,
                ];
            })->values()->toArray();
    }

    /**
     * Check and issue rewards after a visit.
     */
    protected function checkAndIssueRewards(int $customerProfileId, int $branchId, int $visitNumber, LoyaltyProgram $program): void
    {
        $eligibleRewards = $program->activeRewards()
            ->where('required_visits', '<=', $visitNumber)
            ->get();

        foreach ($eligibleRewards as $reward) {
            // Check if already redeemed/pending
            $existing = RewardRedemption::where('branch_id', $branchId)
                ->where('customer_profile_id', $customerProfileId)
                ->where('reward_id', $reward->id)
                ->whereIn('status', ['pending', 'redeemed'])
                ->exists();

            if (! $existing) {
                RewardRedemption::create([
                    'branch_id' => $branchId,
                    'customer_profile_id' => $customerProfileId,
                    'reward_id' => $reward->id,
                    'status' => 'pending',
                    'metadata' => [
                        'auto_issued' => true,
                        'visit_number' => $visitNumber,
                    ],
                ]);
            }
        }
    }

    /**
     * Adjust rewards after visit reversal.
     */
    protected function adjustRewardsAfterReversal(int $customerProfileId, int $branchId, int $reversedVisitNumber): void
    {
        $program = LoyaltyProgram::where('branch_id', $branchId)
            ->where('is_active', true)
            ->first();

        if (! $program) {
            return;
        }

        // Find rewards that were earned at this visit number
        $rewardsToCheck = $program->activeRewards()
            ->where('required_visits', '<=', $reversedVisitNumber)
            ->get();

        foreach ($rewardsToCheck as $reward) {
            // Count remaining qualified visits
            $qualifiedVisits = LoyaltyVisit::where('branch_id', $branchId)
                ->where('customer_profile_id', $customerProfileId)
                ->where('status', LoyaltyVisitStatus::QUALIFIED)
                ->count();

            // If customer no longer qualifies for this reward and hasn't redeemed it, cancel pending redemption
            if ($qualifiedVisits < $reward->required_visits) {
                RewardRedemption::where('branch_id', $branchId)
                    ->where('customer_profile_id', $customerProfileId)
                    ->where('reward_id', $reward->id)
                    ->where('status', 'pending')
                    ->update(['status' => 'cancelled']);
            }
        }
    }

    /**
     * Redeem a reward for a customer.
     */
    public function redeemReward(
        int $branchId,
        int $customerProfileId,
        int $rewardId,
        int $staffId
    ): RewardRedemption {
        return DB::transaction(function () use ($branchId, $customerProfileId, $rewardId, $staffId) {
            $reward = Reward::where('id', $rewardId)
                ->whereHas('loyaltyProgram', fn ($q) => $q->where('branch_id', $branchId)->where('is_active', true))
                ->where('is_active', true)
                ->first();

            if (! $reward) {
                throw new \Exception('Reward not found or not available.', 404);
            }

            // Check if customer qualifies
            $qualifiedVisits = LoyaltyVisit::where('branch_id', $branchId)
                ->where('customer_profile_id', $customerProfileId)
                ->where('status', LoyaltyVisitStatus::QUALIFIED)
                ->count();

            if ($qualifiedVisits < $reward->required_visits) {
                throw new \Exception('Customer does not qualify for this reward.', 422);
            }

            // Check if already redeemed/pending
            $existing = RewardRedemption::where('branch_id', $branchId)
                ->where('customer_profile_id', $customerProfileId)
                ->where('reward_id', $rewardId)
                ->whereIn('status', ['pending', 'redeemed'])
                ->exists();

            if ($existing) {
                throw new \Exception('Reward already redeemed or pending.', 422);
            }

            // Create redemption
            $redemption = RewardRedemption::create([
                'branch_id' => $branchId,
                'customer_profile_id' => $customerProfileId,
                'reward_id' => $rewardId,
                'redeemed_by_staff_id' => $staffId,
                'status' => LoyaltyRedemptionStatus::REDEEMED,
                'redeemed_at' => now(),
                'metadata' => [
                    'reward_type' => $reward->type->value,
                    'reward_config' => $reward->config,
                ],
            ]);

            return $redemption;
        });
    }

    /**
     * Get customer's redemptions for a branch.
     */
    public function getCustomerRedemptions(int $branchId, int $customerProfileId): Collection
    {
        return RewardRedemption::where('branch_id', $branchId)
            ->where('customer_profile_id', $customerProfileId)
            ->with(['reward', 'redeemedByStaff'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Cancel a pending redemption.
     */
    public function cancelRedemption(int $redemptionId, int $staffId): RewardRedemption
    {
        $redemption = RewardRedemption::findOrFail($redemptionId);

        if ($redemption->status !== 'pending') {
            throw new \Exception('Only pending redemptions can be cancelled.', 422);
        }

        $redemption->update([
            'status' => LoyaltyRedemptionStatus::CANCELLED,
            'metadata' => array_merge($redemption->metadata ?? [], [
                'cancelled_by' => $staffId,
                'cancelled_at' => now()->toIso8601String(),
            ]),
        ]);

        return $redemption;
    }

    /**
     * Refund a redeemed reward.
     */
    public function refundRedemption(int $redemptionId, int $staffId): RewardRedemption
    {
        $redemption = RewardRedemption::findOrFail($redemptionId);

        if ($redemption->status !== LoyaltyRedemptionStatus::REDEEMED) {
            throw new \Exception('Only redeemed rewards can be refunded.', 422);
        }

        $redemption->update([
            'status' => LoyaltyRedemptionStatus::REFUNDED,
            'metadata' => array_merge($redemption->metadata ?? [], [
                'refunded_by' => $staffId,
                'refunded_at' => now()->toIso8601String(),
            ]),
        ]);

        return $redemption;
    }

    /**
     * Get loyalty program for a branch.
     */
    public function getProgramForBranch(int $branchId): ?LoyaltyProgram
    {
        return LoyaltyProgram::where('branch_id', $branchId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Create or update loyalty program for a branch.
     */
    public function upsertProgram(int $branchId, array $data): LoyaltyProgram
    {
        return LoyaltyProgram::updateOrCreate(
            ['branch_id' => $branchId],
            array_merge($data, ['branch_id' => $branchId])
        );
    }

    /**
     * Validate source exists and belongs to branch.
     */
    protected function validateSource(string $sourceType, int $sourceId, int $branchId): void
    {
        switch ($sourceType) {
            case 'appointment':
                $source = Appointment::where('id', $sourceId)
                    ->where('branch_id', $branchId)
                    ->whereIn('status', ['completed'])
                    ->first();
                if (! $source) {
                    throw new \Exception('Qualifying appointment not found or not completed.', 422);
                }
                break;
            case 'walk_in':
                $source = Appointment::where('id', $sourceId)
                    ->where('branch_id', $branchId)
                    ->where('status', 'completed')
                    ->where('metadata->walk_in', true)
                    ->first();
                if (! $source) {
                    throw new \Exception('Qualifying walk-in not found or not completed.', 422);
                }
                break;
            default:
                throw new \Exception('Invalid source type.', 422);
        }
    }
}
