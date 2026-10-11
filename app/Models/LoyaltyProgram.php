<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyProgram extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'description',
        'visit_threshold',
        'is_active',
        'reward_expiry_days',
        'reward_config',
    ];

    protected function casts(): array
    {
        return [
            'visit_threshold' => 'integer',
            'is_active' => 'boolean',
            'reward_expiry_days' => 'integer',
            'reward_config' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function rewards(): HasMany
    {
        return $this->hasMany(Reward::class);
    }

    public function activeRewards(): HasMany
    {
        return $this->rewards()->where('is_active', true);
    }
}
