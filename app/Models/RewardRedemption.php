<?php

namespace App\Models;

use App\Enums\LoyaltyRedemptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardRedemption extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'customer_profile_id',
        'reward_id',
        'redeemed_by_staff_id',
        'status',
        'metadata',
        'redeemed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => LoyaltyRedemptionStatus::class,
            'redeemed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class);
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }

    public function redeemedByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'redeemed_by_staff_id');
    }

    public function isActive(): bool
    {
        return $this->status === LoyaltyRedemptionStatus::REDEEMED;
    }
}
