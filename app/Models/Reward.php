<?php

namespace App\Models;

use App\Enums\LoyaltyRewardType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reward extends Model
{
    use HasFactory;

    protected $fillable = [
        'loyalty_program_id',
        'name',
        'description',
        'type',
        'config',
        'required_visits',
        'is_active',
        'expiry_days',
    ];

    protected function casts(): array
    {
        return [
            'type' => LoyaltyRewardType::class,
            'config' => 'array',
            'required_visits' => 'integer',
            'is_active' => 'boolean',
            'expiry_days' => 'integer',
        ];
    }

    public function loyaltyProgram(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(RewardRedemption::class);
    }
}
