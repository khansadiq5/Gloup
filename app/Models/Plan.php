<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'max_branches',
        'price_inr',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'max_branches' => 'integer',
            'price_inr' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isFree(): bool
    {
        return $this->slug === 'free';
    }

    public function isPremium(): bool
    {
        return $this->slug === 'premium';
    }
}
