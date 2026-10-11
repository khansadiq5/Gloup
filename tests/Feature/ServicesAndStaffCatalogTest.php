<?php

use App\Enums\TenantRole;
use App\Models\Branch;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('owner can create service category and service with duration and buffer', function () {
    $tenant = Tenant::factory()->create(['slug' => 'salon-catalog']);
    $owner = User::factory()->create();

    TenantUser::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'role' => TenantRole::OWNER,
    ]);

    $category = ServiceCategory::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Hair Treatments',
    ]);

    $response = $this->actingAs($owner)->postJson(route('tenant.services.store', ['tenant' => $tenant->slug]), [
        'category_id' => $category->id,
        'name' => 'Keratin Smooth Treatment',
        'duration_minutes' => 90,
        'buffer_minutes' => 15,
        'price_inr' => 3500,
        'description' => 'Professional keratin deep conditioning treatment',
    ]);

    $response->assertCreated();
    $service = Service::where('slug', 'keratin-smooth-treatment')->first();
    expect($service)->not->toBeNull();
    expect($service->price_inr)->toBe(3500);
    expect($service->duration_minutes)->toBe(90);
    expect($service->buffer_minutes)->toBe(15);
    expect($service->getTotalDurationMinutes())->toBe(105);
});

test('service creation validates positive duration and non-negative price', function () {
    $tenant = Tenant::factory()->create(['slug' => 'salon-catalog']);
    $owner = User::factory()->create();

    TenantUser::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'role' => TenantRole::OWNER,
    ]);

    $response = $this->actingAs($owner)->postJson(route('tenant.services.store', ['tenant' => $tenant->slug]), [
        'name' => 'Invalid Service',
        'duration_minutes' => -10, // Invalid
        'price_inr' => -500, // Invalid
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['duration_minutes', 'price_inr']);
});

test('branch can override service pricing and duration with clean fallback', function () {
    $tenant = Tenant::factory()->create(['slug' => 'salon-override']);
    $branch1 = Branch::factory()->primary()->create(['tenant_id' => $tenant->id, 'name' => 'City Center']);
    $branch2 = Branch::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Airport Express']);

    $service = Service::factory()->create([
        'tenant_id' => $tenant->id,
        'price_inr' => 1000,
        'duration_minutes' => 45,
    ]);

    $owner = User::factory()->create();
    TenantUser::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'role' => TenantRole::OWNER,
    ]);

    // Branch 2 sets price override = 1400 INR, duration = 30 min (Express service)
    $overrideResponse = $this->actingAs($owner)->postJson(
        route('tenant.branches.services.override', [
            'tenant' => $tenant->slug,
            'branch' => $branch2->slug,
            'service' => $service->slug,
        ]),
        [
            'price_inr' => 1400,
            'duration_minutes' => 30,
            'is_available' => true,
        ]
    );

    $overrideResponse->assertOk();

    // Branch 1 (no override) falls back to default base price (1000) and duration (45)
    expect($service->getPriceForBranch($branch1))->toBe(1000);
    expect($service->getDurationForBranch($branch1))->toBe(45);

    // Branch 2 gets overridden price (1400) and duration (30)
    expect($service->getPriceForBranch($branch2))->toBe(1400);
    expect($service->getDurationForBranch($branch2))->toBe(30);
});

test('owner can create staff and assign to branches and services of own salon', function () {
    $tenant = Tenant::factory()->create(['slug' => 'salon-staff']);
    $branch = Branch::factory()->primary()->create(['tenant_id' => $tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $tenant->id]);

    $owner = User::factory()->create();
    TenantUser::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'role' => TenantRole::OWNER,
    ]);

    $response = $this->actingAs($owner)->postJson(route('tenant.staff.store', ['tenant' => $tenant->slug]), [
        'name' => 'Maya Sharma',
        'email' => 'maya@salon-staff.com',
        'phone' => '+919876543210',
        'bio' => 'Senior hair stylist with 7 years experience',
        'branch_ids' => [$branch->id],
        'service_ids' => [$service->id],
    ]);

    $response->assertCreated();

    $staff = Staff::where('email', 'maya@salon-staff.com')->first();
    expect($staff)->not->toBeNull();
    expect($staff->isAssignedToBranch($branch))->toBeTrue();
    expect($staff->canPerformService($service))->toBeTrue();
});

test('cross-tenant check: cannot assign staff to branch or services of another tenant', function () {
    $tenantA = Tenant::factory()->create(['slug' => 'salon-a']);
    $tenantB = Tenant::factory()->create(['slug' => 'salon-b']);

    $branchA = Branch::factory()->create(['tenant_id' => $tenantA->id]);
    $branchB = Branch::factory()->create(['tenant_id' => $tenantB->id]); // Belongs to Tenant B!

    $serviceA = Service::factory()->create(['tenant_id' => $tenantA->id]);
    $serviceB = Service::factory()->create(['tenant_id' => $tenantB->id]); // Belongs to Tenant B!

    $ownerA = User::factory()->create();
    TenantUser::factory()->create([
        'tenant_id' => $tenantA->id,
        'user_id' => $ownerA->id,
        'role' => TenantRole::OWNER,
    ]);

    // Attempt to assign staff to Branch B belonging to Tenant B
    $responseBranch = $this->actingAs($ownerA)->postJson(route('tenant.staff.store', ['tenant' => $tenantA->slug]), [
        'name' => 'Hacker Staff',
        'branch_ids' => [$branchB->id],
    ]);
    $responseBranch->assertStatus(422);
    $responseBranch->assertJsonFragment(['message' => 'Cannot assign staff to branches that do not belong to this salon.']);

    // Attempt to assign staff to Service B belonging to Tenant B
    $responseService = $this->actingAs($ownerA)->postJson(route('tenant.staff.store', ['tenant' => $tenantA->slug]), [
        'name' => 'Hacker Staff',
        'branch_ids' => [$branchA->id],
        'service_ids' => [$serviceB->id],
    ]);
    $responseService->assertStatus(422);
    $responseService->assertJsonFragment(['message' => 'Cannot assign staff to services that do not belong to this salon.']);
});

test('owner can configure staff weekly shifts and record time off', function () {
    $tenant = Tenant::factory()->create(['slug' => 'salon-shifts']);
    $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
    $staff = Staff::factory()->create(['tenant_id' => $tenant->id]);

    $owner = User::factory()->create();
    TenantUser::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'role' => TenantRole::OWNER,
    ]);

    // Set shift: Monday (1) 09:00 - 18:00
    $shiftResponse = $this->actingAs($owner)->postJson(
        route('tenant.branches.staff.shifts', [
            'tenant' => $tenant->slug,
            'branch' => $branch->slug,
            'staff' => $staff->id,
        ]),
        [
            'shifts' => [
                [
                    'day_of_week' => 1,
                    'start_time' => '09:00',
                    'end_time' => '18:00',
                    'is_working' => true,
                ],
            ],
        ]
    );
    $shiftResponse->assertOk();
    expect($staff->shifts()->where('day_of_week', 1)->first()->start_time)->toBe('09:00');

    // Add time off
    $timeOffResponse = $this->actingAs($owner)->postJson(
        route('tenant.staff.time_off', [
            'tenant' => $tenant->slug,
            'staff' => $staff->id,
        ]),
        [
            'starts_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDays(5)->format('Y-m-d H:i:s'),
            'reason' => 'Annual leave',
        ]
    );
    $timeOffResponse->assertCreated();
    expect($staff->timeOff()->count())->toBe(1);
});

test('cross-tenant IDOR: Tenant A cannot access Tenant B services or staff', function () {
    $tenantA = Tenant::factory()->create(['slug' => 'salon-alpha']);
    $tenantB = Tenant::factory()->create(['slug' => 'salon-beta']);

    $serviceB = Service::factory()->create(['tenant_id' => $tenantB->id]);
    $staffB = Staff::factory()->create(['tenant_id' => $tenantB->id]);

    $ownerA = User::factory()->create();
    TenantUser::factory()->create([
        'tenant_id' => $tenantA->id,
        'user_id' => $ownerA->id,
        'role' => TenantRole::OWNER,
    ]);

    // Attempt to access Service B under Tenant A URL
    $responseService = $this->actingAs($ownerA)->getJson(
        route('tenant.services.show', ['tenant' => $tenantA->slug, 'service' => $serviceB->slug])
    );
    $responseService->assertNotFound();

    // Attempt to access Staff B under Tenant A URL
    $responseStaff = $this->actingAs($ownerA)->getJson(
        route('tenant.staff.show', ['tenant' => $tenantA->slug, 'staff' => $staffB->id])
    );
    $responseStaff->assertNotFound();
});
