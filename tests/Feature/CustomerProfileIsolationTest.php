<?php

use App\Enums\TenantRole;
use App\Models\CustomerProfile;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Policies\CustomerProfilePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('staff can create and list customer profiles for their own tenant', function () {
    $tenant = Tenant::factory()->create(['slug' => 'salon-alpha']);
    $staff = User::factory()->create();

    TenantUser::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $staff->id,
        'role' => TenantRole::STAFF,
    ]);

    $createResponse = $this->actingAs($staff)->postJson(route('tenant.customers.store', ['tenant' => $tenant->slug]), [
        'first_name' => 'Sara',
        'last_name' => 'Connor',
        'email' => 'sara@example.com',
        'phone' => '+919876543210',
        'notes' => 'Prefers herbal hair color',
    ]);

    $createResponse->assertCreated();
    expect($tenant->customerProfiles()->count())->toBe(1);

    $listResponse = $this->actingAs($staff)->getJson(route('tenant.customers.index', ['tenant' => $tenant->slug]));
    $listResponse->assertOk();
    $listResponse->assertJsonFragment(['first_name' => 'Sara', 'email' => 'sara@example.com']);
});

test('staff of Tenant B cannot view or list Tenant A customer profiles', function () {
    $tenantA = Tenant::factory()->create(['slug' => 'salon-alpha']);
    $tenantB = Tenant::factory()->create(['slug' => 'salon-beta']);

    CustomerProfile::factory()->create([
        'tenant_id' => $tenantA->id,
        'first_name' => 'SecretCustomer',
    ]);

    $staffB = User::factory()->create();
    TenantUser::factory()->create([
        'tenant_id' => $tenantB->id,
        'user_id' => $staffB->id,
        'role' => TenantRole::STAFF,
    ]);

    // Staff B attempts to list Tenant A's customer profiles
    $response = $this->actingAs($staffB)->getJson(route('tenant.customers.index', ['tenant' => $tenantA->slug]));
    $response->assertForbidden();
});

test('IDOR attempt: requesting Tenant B customer profile via Tenant A route returns 404', function () {
    $tenantA = Tenant::factory()->create(['slug' => 'salon-alpha']);
    $tenantB = Tenant::factory()->create(['slug' => 'salon-beta']);

    $profileB = CustomerProfile::factory()->create([
        'tenant_id' => $tenantB->id,
        'first_name' => 'BetaCustomer',
    ]);

    $ownerA = User::factory()->create();
    TenantUser::factory()->create([
        'tenant_id' => $tenantA->id,
        'user_id' => $ownerA->id,
        'role' => TenantRole::OWNER,
    ]);

    // Owner A attempts to access Profile B by switching the ID under Tenant A's URL
    $response = $this->actingAs($ownerA)->getJson(
        route('tenant.customers.show', ['tenant' => $tenantA->slug, 'profile' => $profileB->id])
    );

    $response->assertNotFound();
});

test('staff of Tenant B cannot update or delete customer profile of Tenant A', function () {
    $tenantA = Tenant::factory()->create(['slug' => 'salon-alpha']);
    $tenantB = Tenant::factory()->create(['slug' => 'salon-beta']);

    $profileA = CustomerProfile::factory()->create([
        'tenant_id' => $tenantA->id,
        'first_name' => 'OriginalName',
    ]);

    $ownerB = User::factory()->create();
    TenantUser::factory()->create([
        'tenant_id' => $tenantB->id,
        'user_id' => $ownerB->id,
        'role' => TenantRole::OWNER,
    ]);

    // Update attempt
    $updateResponse = $this->actingAs($ownerB)->putJson(
        route('tenant.customers.update', ['tenant' => $tenantB->slug, 'profile' => $profileA->id]),
        ['first_name' => 'TamperedName']
    );
    $updateResponse->assertNotFound();

    // Delete attempt
    $deleteResponse = $this->actingAs($ownerB)->deleteJson(
        route('tenant.customers.destroy', ['tenant' => $tenantB->slug, 'profile' => $profileA->id])
    );
    $deleteResponse->assertNotFound();

    expect($profileA->fresh()->first_name)->toBe('OriginalName');
});

test('customer can view own profile but cannot view another customer profile', function () {
    $tenant = Tenant::factory()->create(['slug' => 'salon-alpha']);
    $customerUser1 = User::factory()->create();
    $customerUser2 = User::factory()->create();

    $profile1 = CustomerProfile::factory()->forUser($customerUser1)->create([
        'tenant_id' => $tenant->id,
    ]);

    $profile2 = CustomerProfile::factory()->forUser($customerUser2)->create([
        'tenant_id' => $tenant->id,
    ]);

    $policy = new CustomerProfilePolicy;

    // Customer 1 viewing own profile is allowed
    expect($policy->view($customerUser1, $profile1))->toBeTrue();

    // Customer 1 viewing Customer 2 profile is denied
    expect($policy->view($customerUser1, $profile2))->toBeFalse();
});
