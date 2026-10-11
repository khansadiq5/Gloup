<?php

namespace App\Models;

use App\Enums\TenantRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantUser extends Model
{
    use HasFactory;

    protected $table = 'tenant_users';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'role',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'role' => TenantRole::class,
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

    public function isOwner(): bool
    {
        return $this->role === TenantRole::OWNER;
    }

    public function isAdmin(): bool
    {
        return $this->role === TenantRole::ADMIN;
    }

    public function isManager(): bool
    {
        return $this->role === TenantRole::MANAGER;
    }

    public function isStaff(): bool
    {
        return $this->role === TenantRole::STAFF;
    }
}
