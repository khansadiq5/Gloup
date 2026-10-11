<?php

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('owner can access their own salon dashboard', function () {
    $tenant = Tenant::factory()->create(['slug' => 'salon-alpha']);
    $owner = User::factory()->create();

    TenantUser::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'role' => TenantRole::OWNER,
    ]);

    $response = $this->actingAs($owner)->get(route('tenant.dashboard', ['tenant' => $tenant->slug]));

    $response->assertOk();
    $response->assertSee($tenant->name);
});

test('user from Tenant A cannot access Tenant B dashboard', function () {
    $tenantA = Tenant::factory()->create(['slug' => 'salon-alpha']);
    $tenantB = Tenant::factory()->create(['slug' => 'salon-beta']);

    $userA = User::factory()->create();
    TenantUser::factory()->create([
        'tenant_id' => $tenantA->id,
        'user_id' => $userA->id,
        'role' => TenantRole::OWNER,
    ]);

    $response = $this->actingAs($userA)->get(route('tenant.dashboard', ['tenant' => $tenantB->slug]));

    $response->assertForbidden();
});

test('unauthenticated user cannot access tenant dashboard and is redirected to login', function () {
    $tenant = Tenant::factory()->create(['slug' => 'salon-alpha']);

    $response = $this->get(route('tenant.dashboard', ['tenant' => $tenant->slug]));

    $response->assertRedirect(route('login'));
});

test('staff member cannot update salon business details', function () {
    $tenant = Tenant::factory()->create(['slug' => 'salon-alpha']);
    $staff = User::factory()->create();

    TenantUser::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $staff->id,
        'role' => TenantRole::STAFF,
    ]);

    $response = $this->actingAs($staff)->putJson(route('tenant.update', ['tenant' => $tenant->slug]), [
        'name' => 'Hacked Salon Name',
    ]);

    $response->assertForbidden();
});

test('owner can update salon business details', function () {
    $tenant = Tenant::factory()->create(['slug' => 'salon-alpha']);
    $owner = User::factory()->create();

    TenantUser::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'role' => TenantRole::OWNER,
    ]);

    $response = $this->actingAs($owner)->putJson(route('tenant.update', ['tenant' => $tenant->slug]), [
        'name' => 'Updated Salon Alpha',
        'email' => 'new-email@salon-alpha.com',
    ]);

    $response->assertOk();
    expect($tenant->fresh()->name)->toBe('Updated Salon Alpha');
    expect($tenant->fresh()->email)->toBe('new-email@salon-alpha.com');
});

test('access to suspended salon is rejected with 403', function () {
    $tenant = Tenant::factory()->suspended()->create(['slug' => 'salon-suspended']);
    $owner = User::factory()->create();

    TenantUser::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'role' => TenantRole::OWNER,
    ]);

    $response = $this->actingAs($owner)->get(route('tenant.dashboard', ['tenant' => $tenant->slug]));

    $response->assertForbidden();
});

test('platform super admin can view any tenant dashboard', function () {
    $tenant = Tenant::factory()->create(['slug' => 'salon-target']);
    $superAdmin = User::factory()->platformAdmin()->create();

    $response = $this->actingAs($superAdmin)->get(route('tenant.dashboard', ['tenant' => $tenant->slug]));

    $response->assertOk();
    $response->assertSee($tenant->name);
});
