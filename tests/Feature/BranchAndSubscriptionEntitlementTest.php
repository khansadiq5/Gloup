<?php

use App\Enums\SubscriptionStatus;
use App\Enums\TenantRole;
use App\Models\Branch;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\SubscriptionEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('free plan salon owner cannot create a second branch', function () {
    $tenant = Tenant::factory()->create(['slug' => 'salon-free']);
    $owner = User::factory()->create();

    TenantUser::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'role' => TenantRole::OWNER,
    ]);

    // Free plan with 1 branch limit
    $entitlementService = app(SubscriptionEntitlementService::class);
    $freePlan = $entitlementService->getDefaultFreePlan();

    Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $freePlan->id,
        'status' => SubscriptionStatus::ACTIVE,
    ]);

    // First branch already exists
    Branch::factory()->primary()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Main Flagship',
    ]);

    expect($tenant->branches()->count())->toBe(1);

    // Attempt to create second branch
    $response = $this->actingAs($owner)->postJson(route('tenant.branches.store', ['tenant' => $tenant->slug]), [
        'name' => 'Second Branch',
        'city' => 'Mumbai',
    ]);

    $response->assertForbidden();
    $response->assertJsonFragment(['error' => 'plan_limit_exceeded']);

    expect($tenant->branches()->count())->toBe(1);
});

test('premium plan salon owner can create multiple branches up to limit', function () {
    $tenant = Tenant::factory()->create(['slug' => 'salon-premium']);
    $owner = User::factory()->create();

    TenantUser::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'role' => TenantRole::OWNER,
    ]);

    $premiumPlan = Plan::factory()->premium(3)->create();

    Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $premiumPlan->id,
        'status' => SubscriptionStatus::ACTIVE,
    ]);

    // Create 1st branch
    $branch1 = $this->actingAs($owner)->postJson(route('tenant.branches.store', ['tenant' => $tenant->slug]), [
        'name' => 'Branch 1',
        'city' => 'Pune',
    ]);
    $branch1->assertCreated();

    // Create 2nd branch
    $branch2 = $this->actingAs($owner)->postJson(route('tenant.branches.store', ['tenant' => $tenant->slug]), [
        'name' => 'Branch 2',
        'city' => 'Mumbai',
    ]);
    $branch2->assertCreated();

    // Create 3rd branch
    $branch3 = $this->actingAs($owner)->postJson(route('tenant.branches.store', ['tenant' => $tenant->slug]), [
        'name' => 'Branch 3',
        'city' => 'Nagpur',
    ]);
    $branch3->assertCreated();

    expect($tenant->branches()->count())->toBe(3);

    // Attempt 4th branch (exceeds limit 3)
    $branch4 = $this->actingAs($owner)->postJson(route('tenant.branches.store', ['tenant' => $tenant->slug]), [
        'name' => 'Branch 4 Exceeded',
        'city' => 'Nashik',
    ]);
    $branch4->assertForbidden();
    $branch4->assertJsonFragment(['error' => 'plan_limit_exceeded']);

    expect($tenant->branches()->count())->toBe(3);
});

test('cross-tenant branch access and URL tampering is blocked', function () {
    $tenantA = Tenant::factory()->create(['slug' => 'salon-alpha']);
    $tenantB = Tenant::factory()->create(['slug' => 'salon-beta']);

    $branchA = Branch::factory()->create(['tenant_id' => $tenantA->id]);
    $branchB = Branch::factory()->create(['tenant_id' => $tenantB->id]);

    $ownerA = User::factory()->create();
    TenantUser::factory()->create([
        'tenant_id' => $tenantA->id,
        'user_id' => $ownerA->id,
        'role' => TenantRole::OWNER,
    ]);

    // Owner A attempts to view Tenant B branch via Tenant B URL
    $response1 = $this->actingAs($ownerA)->getJson(
        route('tenant.branches.show', ['tenant' => $tenantB->slug, 'branch' => $branchB->slug])
    );
    $response1->assertForbidden();

    // Owner A attempts URL tampering: asking Tenant A route for Tenant B's branch slug
    $response2 = $this->actingAs($ownerA)->getJson(
        route('tenant.branches.show', ['tenant' => $tenantA->slug, 'branch' => $branchB->slug])
    );
    $response2->assertNotFound();

    // Owner A attempts to update Tenant B branch
    $response3 = $this->actingAs($ownerA)->putJson(
        route('tenant.branches.update', ['tenant' => $tenantB->slug, 'branch' => $branchB->slug]),
        ['name' => 'Hacked Branch Name']
    );
    $response3->assertForbidden();
});

test('subscription downgrade deactivates excess branches without deleting data', function () {
    $tenant = Tenant::factory()->create(['slug' => 'salon-downgrade']);
    $entitlementService = app(SubscriptionEntitlementService::class);

    $premiumPlan = Plan::factory()->premium(5)->create();
    $freePlan = $entitlementService->getDefaultFreePlan(); // max_branches = 1

    // Subscribed to Premium
    $entitlementService->upgradeToPlan($tenant, $premiumPlan);

    // Create 3 branches (1 primary + 2 additional)
    $branch1 = Branch::factory()->primary()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Primary Main',
        'is_active' => true,
    ]);

    $branch2 = Branch::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Branch Two',
        'is_active' => true,
    ]);

    $branch3 = Branch::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Branch Three',
        'is_active' => true,
    ]);

    expect($tenant->branches()->count())->toBe(3);
    expect($tenant->activeBranches()->count())->toBe(3);

    // Downgrade to Free
    $entitlementService->downgradeToPlan($tenant, $freePlan);

    // CRITICAL ASSERTION: Zero branches deleted!
    expect($tenant->branches()->count())->toBe(3);

    // Primary branch remains active
    expect($branch1->fresh()->is_active)->toBeTrue();

    // Active branches is now capped at 1
    expect($tenant->activeBranches()->count())->toBe(1);

    // Excess branches are set to inactive (sunset/read-only)
    expect($branch2->fresh()->is_active)->toBeFalse();
    expect($branch3->fresh()->is_active)->toBeFalse();

    // Now test re-upgrade to Premium reactivates branches
    $entitlementService->upgradeToPlan($tenant, $premiumPlan);
    expect($tenant->activeBranches()->count())->toBe(3);
    expect($branch2->fresh()->is_active)->toBeTrue();
    expect($branch3->fresh()->is_active)->toBeTrue();
});

test('payload tampering cannot bypass server-side plan branch entitlement', function () {
    $tenant = Tenant::factory()->create(['slug' => 'salon-tamper']);
    $owner = User::factory()->create();

    TenantUser::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'role' => TenantRole::OWNER,
    ]);

    $freePlan = app(SubscriptionEntitlementService::class)->getDefaultFreePlan();
    Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $freePlan->id,
        'status' => SubscriptionStatus::ACTIVE,
    ]);

    Branch::factory()->primary()->create(['tenant_id' => $tenant->id]);

    // Attacker sends extra payload attributes attempting to bypass entitlement
    $response = $this->actingAs($owner)->postJson(route('tenant.branches.store', ['tenant' => $tenant->slug]), [
        'name' => 'Sneaky Branch',
        'max_branches' => 999,
        'plan_override' => 'unlimited',
        'bypass_entitlement' => true,
        'is_premium' => true,
    ]);

    $response->assertForbidden();
    $response->assertJsonFragment(['error' => 'plan_limit_exceeded']);

    expect($tenant->branches()->count())->toBe(1);
});
