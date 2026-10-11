<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'uuid',
        'name',
        'slug',
        'phone',
        'email',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'timezone',
        'is_active',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_primary' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Branch $branch) {
            if (empty($branch->uuid)) {
                $branch->uuid = (string) Str::uuid();
            }
            if (empty($branch->slug)) {
                $branch->slug = Str::slug($branch->name);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branchHours(): HasMany
    {
        return $this->hasMany(BranchHour::class);
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'staff_branches')
            ->withTimestamps();
    }

    public function serviceOverrides(): HasMany
    {
        return $this->hasMany(BranchService::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'branch_services')
            ->withPivot('price_inr', 'duration_minutes', 'is_available')
            ->withTimestamps();
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(StaffShift::class);
    }

    public function isPrimary(): bool
    {
        return (bool) $this->is_primary;
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
