<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RegisterCustomerAction
{
    /**
     * @param  array{name: string, email: string, password: string, phone?: ?string}  $data
     */
    public function execute(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => strtolower(trim($data['email'])),
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
        ]);
    }
}
