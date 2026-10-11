<?php

use App\Enums\TenantRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('xss payload in salon name is escaped and not executed in html response', function () {
    $xssPayload = '<script>alert("xss-salon")</script>';

    $response = $this->post(route('register.owner.submit'), [
        'salon_name' => $xssPayload,
        'name' => 'Owner Name',
        'email' => 'xss-owner@example.com',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
    ]);

    $tenant = Tenant::where('email', 'xss-owner@example.com')->first();
    expect($tenant)->not->toBeNull();

    $owner = User::where('email', 'xss-owner@example.com')->first();

    $dashboardResponse = $this->actingAs($owner)->get(route('tenant.dashboard', ['tenant' => $tenant->slug]));
    $dashboardResponse->assertOk();

    // The script tag must be HTML-escaped by Blade, never raw HTML
    $dashboardResponse->assertSee('&lt;script&gt;alert(&quot;xss-salon&quot;)&lt;/script&gt;', false);
    $dashboardResponse->assertDontSee('<script>alert("xss-salon")</script>', false);
});

test('xss payload in customer profile notes is safely sanitized/escaped in responses', function () {
    $tenant = Tenant::factory()->create(['slug' => 'salon-alpha']);
    $owner = User::factory()->create();

    TenantUser::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'role' => TenantRole::OWNER,
    ]);

    $xssNotes = '<img src=x onerror=alert("notes-xss")>';

    $createResponse = $this->actingAs($owner)->postJson(route('tenant.customers.store', ['tenant' => $tenant->slug]), [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'notes' => $xssNotes,
    ]);

    $createResponse->assertCreated();

    $profileId = $createResponse->json('profile.id');

    $showResponse = $this->actingAs($owner)->getJson(
        route('tenant.customers.show', ['tenant' => $tenant->slug, 'profile' => $profileId])
    );

    $showResponse->assertOk();
    // In JSON, it should be safely encoded string data
    expect($showResponse->json('profile.notes'))->toBe($xssNotes);
});
