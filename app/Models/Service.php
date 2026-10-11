<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'category_id',
        'name',
        'slug',
        'description',
        'duration_minutes',
        'buffer_minutes',
        'price_inr',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'buffer_minutes' => 'integer',
            'price_inr' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Service $service) {
            if (empty($service->slug)) {
                $service->slug = Str::slug($service->name);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function branchOverrides(): HasMany
    {
        return $this->hasMany(BranchService::class);
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'staff_services')
            ->withTimestamps();
    }

    public function getTotalDurationMinutes(): int
    {
        return $this->duration_minutes + $this->buffer_minutes;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getPriceForBranch(Branch|int $branch): int
    {
        $branchId = $branch instanceof Branch ? $branch->id : $branch;

        $override = $this->branchOverrides()
            ->where('branch_id', $branchId)
            ->first();

        if ($override && $override->price_inr !== null) {
            return $override->price_inr;
        }

        return $this->price_inr;
    }

    public function getDurationForBranch(Branch|int $branch): int
    {
        $branchId = $branch instanceof Branch ? $branch->id : $branch;

        $override = $this->branchOverrides()
            ->where('branch_id', $branchId)
            ->first();

        if ($override && $override->duration_minutes !== null) {
            return $override->duration_minutes;
        }

        return $this->duration_minutes;
    }

    public function isAvailableAtBranch(Branch|int $branch): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $branchId = $branch instanceof Branch ? $branch->id : $branch;

        $override = $this->branchOverrides()
            ->where('branch_id', $branchId)
            ->first();

        if ($override) {
            return (bool) $override->is_available;
        }

        return true;
    }
}
