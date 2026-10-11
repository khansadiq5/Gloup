<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Staff extends Model
{
    use HasFactory;

    protected $table = 'staff';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'email',
        'phone',
        'bio',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'staff_branches')
            ->withTimestamps();
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'staff_services')
            ->withTimestamps();
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(StaffShift::class);
    }

    public function timeOff(): HasMany
    {
        return $this->hasMany(StaffTimeOff::class);
    }

    public function isAssignedToBranch(Branch|int $branch): bool
    {
        $branchId = $branch instanceof Branch ? $branch->id : $branch;

        return $this->branches()->where('branches.id', $branchId)->exists();
    }

    public function canPerformService(Service|int $service): bool
    {
        $serviceId = $service instanceof Service ? $service->id : $service;

        return $this->services()->where('services.id', $serviceId)->exists();
    }
}
