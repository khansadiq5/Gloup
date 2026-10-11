<?php

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('salon owner can register successfully and is assigned owner membership', function () {
    $response = $this->post(route('register.owner.submit'), [
        'salon_name' => 'Luxe Hair Studio',
        'salon_slug' => 'luxe-hair',
        'name' => 'Alice Johnson',
        'email' => 'alice@example.com',
        'phone' => '+919876543210',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
    ]);

    $response->assertRedirect(route('tenant.dashboard', ['tenant' => 'luxe-hair']));

    $this->assertAuthenticated();

    $user = User::where('email', 'alice@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Alice Johnson');

    $tenant = Tenant::where('slug', 'luxe-hair')->first();
    expect($tenant)->not->toBeNull();
    expect($tenant->name)->toBe('Luxe Hair Studio');

    expect($user->belongsToTenant($tenant))->toBeTrue();
    expect($user->hasTenantRole($tenant, TenantRole::OWNER))->toBeTrue();
});

test('salon owner registration validates unique email and required fields', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->from(route('register.owner'))->post(route('register.owner.submit'), [
        'salon_name' => '',
        'name' => '',
        'email' => 'taken@example.com',
        'password' => 'short',
        'password_confirmation' => 'mismatch',
    ]);

    $response->assertSessionHasErrors(['salon_name', 'name', 'email', 'password']);
    $this->assertGuest();
});

test('customer can register without tenant membership', function () {
    $response = $this->post(route('register.customer.submit'), [
        'name' => 'Bob Customer',
        'email' => 'bob@example.com',
        'phone' => '+919988776655',
        'password' => 'customerpass123',
        'password_confirmation' => 'customerpass123',
    ]);

    $response->assertRedirect(route('home'));
    $this->assertAuthenticated();

    $user = User::where('email', 'bob@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->tenantMemberships()->count())->toBe(0);
});

test('user can login with valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'john@example.com',
        'password' => 'password123',
    ]);

    $response = $this->post(route('login.submit'), [
        'email' => 'john@example.com',
        'password' => 'password123',
    ]);

    $response->assertRedirect();
    $this->assertAuthenticatedAs($user);
});

test('user cannot login with invalid password and gets rate limited', function () {
    User::factory()->create([
        'email' => 'victim@example.com',
        'password' => 'correct-pass',
    ]);

    // 5 failed attempts
    for ($i = 0; $i < 5; $i++) {
        $response = $this->post(route('login.submit'), [
            'email' => 'victim@example.com',
            'password' => 'wrong-pass',
        ]);
        $response->assertSessionHasErrors('email');
    }

    // 6th attempt triggers rate limit throttle
    $throttleResponse = $this->post(route('login.submit'), [
        'email' => 'victim@example.com',
        'password' => 'wrong-pass',
    ]);

    $throttleResponse->assertSessionHasErrors('email');
    $errors = session('errors')->get('email');
    expect($errors[0])->toContain('Too many login attempts');
});

test('authenticated user can logout and session is invalidated', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));
    $this->assertGuest();
});
