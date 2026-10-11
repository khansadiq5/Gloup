<?php

namespace App\Models;

use App\Enums\TenantRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'is_platform_admin',
        'email_verified_at',
        'phone_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
        ];
    }

    public function tenantMemberships(): HasMany
    {
        return $this->hasMany(TenantUser::class);
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_users')
            ->withPivot('role', 'is_active')
            ->withTimestamps();
    }

    public function customerProfiles(): HasMany
    {
        return $this->hasMany(CustomerProfile::class);
    }

    public function belongsToTenant(Tenant|int $tenant): bool
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $this->tenantMemberships()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->exists();
    }

    public function getTenantMembership(Tenant|int $tenant): ?TenantUser
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return $this->tenantMemberships()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->first();
    }

    public function hasTenantRole(Tenant|int $tenant, array|TenantRole|string $roles): bool
    {
        $membership = $this->getTenantMembership($tenant);

        if (! $membership) {
            return false;
        }

        $rolesToCheck = is_array($roles) ? $roles : [$roles];
        $roleStrings = array_map(
            fn ($r) => $r instanceof TenantRole ? $r->value : (string) $r,
            $rolesToCheck
        );

        return in_array($membership->role->value, $roleStrings, true);
    }

    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }
}
