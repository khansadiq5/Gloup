<?php

namespace App\Enums;

enum TenantRole: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case MANAGER = 'manager';
    case STAFF = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Business Owner',
            self::ADMIN => 'Business Admin',
            self::MANAGER => 'Branch Manager',
            self::STAFF => 'Staff Member',
        };
    }

    public function canManageBusiness(): bool
    {
        return in_array($this, [self::OWNER, self::ADMIN], true);
    }
}
