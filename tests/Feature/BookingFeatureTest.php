<?php

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Enums\TenantRole;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchHour;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SlotReservation;
use App\Models\Staff;
use App\Models\StaffShift;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Tenant A
    $this->tenantA = Tenant::factory()->create(['slug' => 'salon-a']);
    $this->ownerA = User::factory()->create();
    TenantUser::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'user_id' => $this->ownerA->id,
        'role' => TenantRole::OWNER,
    ]);

    $this->branchA = Branch::factory()->primary()->create(['tenant_id' => $this->tenantA->id, 'slug' => 'main']);
    $this->categoryA = ServiceCategory::factory()->create(['tenant_id' => $this->tenantA->id]);
    $this->serviceA = Service::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'category_id' => $this->categoryA->id,
        'duration_minutes' => 30,
        'buffer_minutes' => 5,
        'price_inr' => 1000,
    ]);
    $this->staffA = Staff::factory()->create(['tenant_id' => $this->tenantA->id]);
    $this->staffA->branches()->attach($this->branchA->id);
    $this->staffA->services()->attach($this->serviceA->id);

    // Setup branch hours for tomorrow
    $this->date = Carbon::tomorrow();
    $dayOfWeek = (int) $this->date->format('w');
    BranchHour::factory()->create([
        'branch_id' => $this->branchA->id,
        'day_of_week' => $dayOfWeek,
        'open_time' => '09:00:00',
        'close_time' => '20:00:00',
        'is_closed' => false,
    ]);
    StaffShift::factory()->create([
        'staff_id' => $this->staffA->id,
        'branch_id' => $this->branchA->id,
        'day_of_week' => $dayOfWeek,
        'start_time' => '09:00:00',
        'end_time' => '18:00:00',
        'is_working' => true,
    ]);

    // Customer profile for tenant A
    $this->customerA = CustomerProfile::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'user_id' => $this->ownerA->id,
    ]);

    // Tenant B (for cross-tenant tests)
    $this->tenantB = Tenant::factory()->create(['slug' => 'salon-b']);
    $this->ownerB = User::factory()->create();
    TenantUser::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'user_id' => $this->ownerB->id,
        'role' => TenantRole::OWNER,
    ]);
    $this->branchB = Branch::factory()->primary()->create(['tenant_id' => $this->tenantB->id]);

    $this->availabilityService = app(AvailabilityService::class);
    $this->bookingService = app(BookingService::class);
});

test('guest can create slot reservation', function () {
    $start = $this->date->copy()->setTime(10, 0);
    $end = $start->copy()->addMinutes(35);
    $token = 'guest-session-token';

    $reservation = $this->availabilityService->createReservation(
        $this->tenantA->id,
        $this->branchA->id,
        $this->staffA->id,
        $start,
        $end,
        $token
    );

    expect($reservation)->not->toBeNull();
    // expires_at should be in the future (approximately 10 minutes from now)
    expect($reservation->expires_at->gt(now()))->toBeTrue();
});

test('guest can book appointment with valid reservation', function () {
    $start = $this->date->copy()->setTime(10, 0);
    $end = $start->copy()->addMinutes(35);
    $token = 'guest-session-token';

    // Create reservation
    $this->availabilityService->createReservation(
        $this->tenantA->id,
        $this->branchA->id,
        $this->staffA->id,
        $start,
        $end,
        $token
    );

    // Book appointment
    $result = $this->bookingService->createBooking([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'customer_profile_id' => $this->customerA->id,
        'staff_id' => $this->staffA->id,
        'service_ids' => [$this->serviceA->id],
        'starts_at' => $start,
        'ends_at' => $end,
        'session_token' => $token,
        'notes' => 'Test booking',
    ]);

    $appointment = $result['appointment'];
    expect($appointment->status)->toBe(AppointmentStatus::CONFIRMED);
    expect($appointment->payment_status)->toBe(PaymentStatus::PAY_AT_SALON);
    expect($appointment->total_price_inr)->toBe(1000);
    expect($appointment->total_duration_minutes)->toBe(35);
    expect($appointment->services->count())->toBe(1);
    expect($appointment->services->first()->price_inr)->toBe(1000);

    // Reservation should be consumed
    expect(SlotReservation::where('session_token_hash', hash('sha256', $token))->exists())->toBeFalse();

    // Audit event should be recorded
    expect($appointment->events()->count())->toBe(1);
    expect($appointment->events->first()->event_type)->toBe('created');
});

test('booking fails with invalid reservation token', function () {
    $start = $this->date->copy()->setTime(10, 0);
    $end = $start->copy()->addMinutes(35);

    // Try to book without creating reservation first
    try {
        $this->bookingService->createBooking([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'customer_profile_id' => $this->customerA->id,
            'staff_id' => $this->staffA->id,
            'service_ids' => [$this->serviceA->id],
            'starts_at' => $start,
            'ends_at' => $end,
            'session_token' => 'invalid-token',
        ]);
    } catch (Exception $e) {
        expect($e->getMessage())->toBe('Invalid or expired reservation token.');
        expect($e->getCode())->toBe(409);
    }
});

test('booking fails with expired reservation', function () {
    $start = $this->date->copy()->setTime(10, 0);
    $end = $start->copy()->addMinutes(35);
    $token = 'expired-token';

    SlotReservation::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'staff_id' => $this->staffA->id,
        'start_time' => $start,
        'end_time' => $end,
        'session_token_hash' => hash('sha256', $token),
        'expires_at' => now()->subMinutes(1),
    ]);

    try {
        $this->bookingService->createBooking([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'customer_profile_id' => $this->customerA->id,
            'staff_id' => $this->staffA->id,
            'service_ids' => [$this->serviceA->id],
            'starts_at' => $start,
            'ends_at' => $end,
            'session_token' => $token,
        ]);
    } catch (Exception $e) {
        expect($e->getMessage())->toBe('Invalid or expired reservation token.');
        expect($e->getCode())->toBe(409);
    }
});

test('booking fails when slot no longer available', function () {
    $start = $this->date->copy()->setTime(10, 0);
    $end = $start->copy()->addMinutes(35);
    $token = 'test-token';

    // Create reservation
    $this->availabilityService->createReservation(
        $this->tenantA->id,
        $this->branchA->id,
        $this->staffA->id,
        $start,
        $end,
        $token
    );

    // Create a conflicting appointment directly (simulating another booking)
    Appointment::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'staff_id' => $this->staffA->id,
        'status' => AppointmentStatus::CONFIRMED,
        'starts_at' => $start,
        'ends_at' => $end,
    ]);

    try {
        $this->bookingService->createBooking([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'customer_profile_id' => $this->customerA->id,
            'staff_id' => $this->staffA->id,
            'service_ids' => [$this->serviceA->id],
            'starts_at' => $start,
            'ends_at' => $end,
            'session_token' => $token,
        ]);
    } catch (Exception $e) {
        expect($e->getMessage())->toBe('Slot no longer available.');
        expect($e->getCode())->toBe(409);
    }
});

test('booking uses branch-specific pricing', function () {
    // Create branch override with different price
    $this->serviceA->branchOverrides()->create([
        'branch_id' => $this->branchA->id,
        'price_inr' => 1500,
        'duration_minutes' => null,
        'buffer_minutes' => null,
        'is_available' => true,
    ]);

    $start = $this->date->copy()->setTime(10, 0);
    $end = $start->copy()->addMinutes(35);
    $token = 'pricing-token';

    $this->availabilityService->createReservation(
        $this->tenantA->id,
        $this->branchA->id,
        $this->staffA->id,
        $start,
        $end,
        $token
    );

    $result = $this->bookingService->createBooking([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'customer_profile_id' => $this->customerA->id,
        'staff_id' => $this->staffA->id,
        'service_ids' => [$this->serviceA->id],
        'starts_at' => $start,
        'ends_at' => $end,
        'session_token' => $token,
    ]);

    expect($result['appointment']->total_price_inr)->toBe(1500);
    expect($result['services']->first()->price_inr)->toBe(1500);
});

test('appointment can be cancelled', function () {
    $start = $this->date->copy()->setTime(10, 0);
    $end = $start->copy()->addMinutes(35);
    $token = 'cancel-token';

    $this->availabilityService->createReservation(
        $this->tenantA->id,
        $this->branchA->id,
        $this->staffA->id,
        $start,
        $end,
        $token
    );

    $result = $this->bookingService->createBooking([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'customer_profile_id' => $this->customerA->id,
        'staff_id' => $this->staffA->id,
        'service_ids' => [$this->serviceA->id],
        'starts_at' => $start,
        'ends_at' => $end,
        'session_token' => $token,
    ]);

    $appointment = $result['appointment'];

    $cancelled = $this->bookingService->cancelAppointment($appointment, $this->ownerA->id, 'Customer cancelled');

    expect($cancelled->status)->toBe(AppointmentStatus::CANCELLED);
    expect($cancelled->events()->count())->toBe(2); // created + cancelled
    expect($cancelled->events()->where('event_type', 'cancelled')->first()->description)->toBe('Customer cancelled');
});

test('appointment can be rescheduled', function () {
    $start = $this->date->copy()->setTime(10, 0);
    $end = $start->copy()->addMinutes(35);
    $token = 'reschedule-token';

    $this->availabilityService->createReservation(
        $this->tenantA->id,
        $this->branchA->id,
        $this->staffA->id,
        $start,
        $end,
        $token
    );

    $result = $this->bookingService->createBooking([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'customer_profile_id' => $this->customerA->id,
        'staff_id' => $this->staffA->id,
        'service_ids' => [$this->serviceA->id],
        'starts_at' => $start,
        'ends_at' => $end,
        'session_token' => $token,
    ]);

    $appointment = $result['appointment'];

    // Create new reservation for new time
    $newStart = $this->date->copy()->setTime(11, 0);
    $newEnd = $newStart->copy()->addMinutes(35);
    $newToken = 'new-reservation-token';

    $this->availabilityService->createReservation(
        $this->tenantA->id,
        $this->branchA->id,
        $this->staffA->id,
        $newStart,
        $newEnd,
        $newToken
    );

    $rescheduled = $this->bookingService->rescheduleAppointment(
        $appointment,
        $this->staffA->id,
        $newStart,
        $newEnd,
        $newToken,
        $this->ownerA->id
    );

    expect($rescheduled->starts_at->format('H:i'))->toBe('11:00');
    expect($rescheduled->ends_at->format('H:i'))->toBe('11:35');
    expect($rescheduled->events()->where('event_type', 'rescheduled')->count())->toBe(1);
});

test('appointment can be marked as completed', function () {
    $start = $this->date->copy()->setTime(10, 0);
    $end = $start->copy()->addMinutes(35);
    $token = 'complete-token';

    $this->availabilityService->createReservation(
        $this->tenantA->id,
        $this->branchA->id,
        $this->staffA->id,
        $start,
        $end,
        $token
    );

    $result = $this->bookingService->createBooking([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'customer_profile_id' => $this->customerA->id,
        'staff_id' => $this->staffA->id,
        'service_ids' => [$this->serviceA->id],
        'starts_at' => $start,
        'ends_at' => $end,
        'session_token' => $token,
    ]);

    $appointment = $result['appointment'];

    $completed = $this->bookingService->completeAppointment($appointment, $this->ownerA->id);

    expect($completed->status)->toBe(AppointmentStatus::COMPLETED);
    expect($completed->events()->where('event_type', 'completed')->count())->toBe(1);
});

test('appointment can be marked as no-show', function () {
    $start = $this->date->copy()->setTime(10, 0);
    $end = $start->copy()->addMinutes(35);
    $token = 'noshow-token';

    $this->availabilityService->createReservation(
        $this->tenantA->id,
        $this->branchA->id,
        $this->staffA->id,
        $start,
        $end,
        $token
    );

    $result = $this->bookingService->createBooking([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'customer_profile_id' => $this->customerA->id,
        'staff_id' => $this->staffA->id,
        'service_ids' => [$this->serviceA->id],
        'starts_at' => $start,
        'ends_at' => $end,
        'session_token' => $token,
    ]);

    $appointment = $result['appointment'];

    $noShow = $this->bookingService->markNoShow($appointment, $this->ownerA->id);

    expect($noShow->status)->toBe(AppointmentStatus::NO_SHOW);
    expect($noShow->events()->where('event_type', 'no_show')->count())->toBe(1);
});

test('payment status can be updated', function () {
    $start = $this->date->copy()->setTime(10, 0);
    $end = $start->copy()->addMinutes(35);
    $token = 'payment-token';

    $this->availabilityService->createReservation(
        $this->tenantA->id,
        $this->branchA->id,
        $this->staffA->id,
        $start,
        $end,
        $token
    );

    $result = $this->bookingService->createBooking([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'customer_profile_id' => $this->customerA->id,
        'staff_id' => $this->staffA->id,
        'service_ids' => [$this->serviceA->id],
        'starts_at' => $start,
        'ends_at' => $end,
        'session_token' => $token,
    ]);

    $appointment = $result['appointment'];

    // Update to paid
    $updated = $this->bookingService->updatePaymentStatus($appointment, PaymentStatus::PAID, $this->ownerA->id);

    expect($updated->payment_status)->toBe(PaymentStatus::PAID);
    expect($updated->events()->where('event_type', 'payment_status_changed')->count())->toBe(1);
});

test('walk-in creates completed appointment immediately', function () {
    // Use today's date with a time within working hours (9 AM - 6 PM)
    $today = Carbon::today();
    $dayOfWeek = (int) $today->format('w');

    // Ensure branch hours exist for today
    BranchHour::factory()->create([
        'branch_id' => $this->branchA->id,
        'day_of_week' => $dayOfWeek,
        'open_time' => '09:00:00',
        'close_time' => '20:00:00',
        'is_closed' => false,
    ]);

    // Ensure staff shift exists for today
    StaffShift::factory()->create([
        'staff_id' => $this->staffA->id,
        'branch_id' => $this->branchA->id,
        'day_of_week' => $dayOfWeek,
        'start_time' => '09:00:00',
        'end_time' => '18:00:00',
        'is_working' => true,
    ]);

    // Use a time within working hours (e.g., 10:00 AM today)
    $start = $today->copy()->setTime(10, 0);
    // If 10 AM has passed, use 2 PM
    if ($start->isPast()) {
        $start = $today->copy()->setTime(14, 0);
    }
    $end = $start->copy()->addMinutes(35);

    $result = $this->bookingService->createWalkIn([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'customer_profile_id' => $this->customerA->id,
        'staff_id' => $this->staffA->id,
        'service_ids' => [$this->serviceA->id],
        'starts_at' => $start,
        'ends_at' => $end,
        'notes' => 'Walk-in customer',
        'user_id' => $this->ownerA->id,
    ]);

    $appointment = $result['appointment'];
    expect($appointment->status)->toBe(AppointmentStatus::COMPLETED);
    expect($appointment->metadata['walk_in'])->toBeTrue();
    expect($appointment->events()->where('event_type', 'walk_in_created')->count())->toBe(1);
});

test('concurrent booking attempts: only one succeeds', function () {
    $start = $this->date->copy()->setTime(10, 0);
    $end = $start->copy()->addMinutes(35);

    // Create one reservation
    $token = 'concurrent-token';

    $this->availabilityService->createReservation(
        $this->tenantA->id, $this->branchA->id, $this->staffA->id, $start, $end, $token
    );

    // First booking succeeds
    $result1 = $this->bookingService->createBooking([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'customer_profile_id' => $this->customerA->id,
        'staff_id' => $this->staffA->id,
        'service_ids' => [$this->serviceA->id],
        'starts_at' => $start,
        'ends_at' => $end,
        'session_token' => $token,
    ]);

    expect($result1['appointment']->status)->toBe(AppointmentStatus::CONFIRMED);

    // Create a second reservation for the same slot (simulating a race condition)
    $token2 = 'concurrent-token-2';
    $this->availabilityService->createReservation(
        $this->tenantA->id, $this->branchA->id, $this->staffA->id, $start, $end, $token2
    );

    // Second booking should fail because slot is now taken by the first appointment
    try {
        $this->bookingService->createBooking([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'customer_profile_id' => $this->customerA->id,
            'staff_id' => $this->staffA->id,
            'service_ids' => [$this->serviceA->id],
            'starts_at' => $start,
            'ends_at' => $end,
            'session_token' => $token2,
        ]);
    } catch (Exception $e) {
        expect($e->getMessage())->toBe('Slot no longer available.');
        expect($e->getCode())->toBe(409);
    }

    // Verify only one appointment exists
    expect(Appointment::where('staff_id', $this->staffA->id)
        ->where('starts_at', $start)
        ->where('ends_at', $end)
        ->count())->toBe(1);
});

test('reservation expires and slot becomes available again', function () {
    $start = $this->date->copy()->setTime(10, 0);
    $end = $start->copy()->addMinutes(35);
    $token = 'expiry-token';

    // Create reservation
    $this->availabilityService->createReservation(
        $this->tenantA->id,
        $this->branchA->id,
        $this->staffA->id,
        $start,
        $end,
        $token
    );

    // Slot should be unavailable
    $slotsBefore = $this->availabilityService->getAvailableSlots(
        $this->branchA->id,
        $this->staffA->id,
        $this->date,
        [$this->serviceA->id]
    );
    $targetSlot = $slotsBefore->firstWhere('start', $start);
    expect($targetSlot['available'])->toBeFalse();

    // Manually expire the reservation
    SlotReservation::where('session_token_hash', hash('sha256', $token))
        ->update(['expires_at' => now()->subMinutes(1)]);

    // Slot should be available again
    $slotsAfter = $this->availabilityService->getAvailableSlots(
        $this->branchA->id,
        $this->staffA->id,
        $this->date,
        [$this->serviceA->id]
    );
    $targetSlot = $slotsAfter->firstWhere('start', $start);
    expect($targetSlot['available'])->toBeTrue();
});

test('cross-tenant booking access is blocked', function () {
    $start = $this->date->copy()->setTime(10, 0);
    $end = $start->copy()->addMinutes(35);
    $token = 'cross-tenant-token';

    // Create customer profile for tenant B
    $customerB = CustomerProfile::factory()->create(['tenant_id' => $this->tenantB->id]);

    // Create reservation for tenant A's branch
    $this->availabilityService->createReservation(
        $this->tenantA->id,
        $this->branchA->id,
        $this->staffA->id,
        $start,
        $end,
        $token
    );

    // Try to book using tenant B's customer profile but tenant A's branch
    try {
        $this->bookingService->createBooking([
            'tenant_id' => $this->tenantB->id, // Wrong tenant
            'branch_id' => $this->branchA->id,
            'customer_profile_id' => $customerB->id,
            'staff_id' => $this->staffA->id,
            'service_ids' => [$this->serviceA->id],
            'starts_at' => $start,
            'ends_at' => $end,
            'session_token' => $token,
        ]);
    } catch (Exception $e) {
        // Should fail because customer profile doesn't belong to the tenant
        expect($e->getMessage())->toContain('not found or inactive');
    }
});

test('cannot book with service from another tenant', function () {
    // Create service for tenant B
    $categoryB = ServiceCategory::factory()->create(['tenant_id' => $this->tenantB->id]);
    $serviceB = Service::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'category_id' => $categoryB->id,
    ]);

    $start = $this->date->copy()->setTime(10, 0);
    $end = $start->copy()->addMinutes(35);
    $token = 'cross-service-token';

    $this->availabilityService->createReservation(
        $this->tenantA->id,
        $this->branchA->id,
        $this->staffA->id,
        $start,
        $end,
        $token
    );

    // Try to book with tenant B's service
    try {
        $this->bookingService->createBooking([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'customer_profile_id' => $this->customerA->id,
            'staff_id' => $this->staffA->id,
            'service_ids' => [$serviceB->id], // Service from tenant B
            'starts_at' => $start,
            'ends_at' => $end,
            'session_token' => $token,
        ]);
    } catch (Exception $e) {
        // Availability check fails first because service doesn't belong to tenant
        expect($e->getMessage())->toBe('Slot no longer available.');
        expect($e->getCode())->toBe(409);
    }
});

test('cannot book with staff from another tenant', function () {
    $staffB = Staff::factory()->create(['tenant_id' => $this->tenantB->id]);
    $staffB->branches()->attach($this->branchA->id); // Try to attach to tenant A's branch
    $staffB->services()->attach($this->serviceA->id);

    $start = $this->date->copy()->setTime(10, 0);
    $end = $start->copy()->addMinutes(35);
    $token = 'cross-staff-token';

    $this->availabilityService->createReservation(
        $this->tenantA->id,
        $this->branchA->id,
        $staffB->id, // Staff from tenant B
        $start,
        $end,
        $token
    );

    try {
        $this->bookingService->createBooking([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'customer_profile_id' => $this->customerA->id,
            'staff_id' => $staffB->id,
            'service_ids' => [$this->serviceA->id],
            'starts_at' => $start,
            'ends_at' => $end,
            'session_token' => $token,
        ]);
    } catch (Exception $e) {
        expect($e->getMessage())->toBe('Slot no longer available.');
    }
});

test('client cannot tamper with price by sending different total', function () {
    $start = $this->date->copy()->setTime(10, 0);
    $end = $start->copy()->addMinutes(35);
    $token = 'tamper-token';

    $this->availabilityService->createReservation(
        $this->tenantA->id,
        $this->branchA->id,
        $this->staffA->id,
        $start,
        $end,
        $token
    );

    $result = $this->bookingService->createBooking([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'customer_profile_id' => $this->customerA->id,
        'staff_id' => $this->staffA->id,
        'service_ids' => [$this->serviceA->id],
        'starts_at' => $start,
        'ends_at' => $end,
        'session_token' => $token,
        // Client tries to send different metadata but price is calculated server-side
        'metadata' => ['client_price' => 99999],
    ]);

    // Price should be server-calculated, not client-provided
    expect($result['appointment']->total_price_inr)->toBe(1000);
});

test('cancellation of completed appointment is rejected', function () {
    $start = $this->date->copy()->setTime(10, 0);
    $end = $start->copy()->addMinutes(35);
    $token = 'cant-cancel-token';

    $this->availabilityService->createReservation(
        $this->tenantA->id,
        $this->branchA->id,
        $this->staffA->id,
        $start,
        $end,
        $token
    );

    $result = $this->bookingService->createBooking([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'customer_profile_id' => $this->customerA->id,
        'staff_id' => $this->staffA->id,
        'service_ids' => [$this->serviceA->id],
        'starts_at' => $start,
        'ends_at' => $end,
        'session_token' => $token,
    ]);

    $appointment = $result['appointment'];
    $this->bookingService->completeAppointment($appointment, $this->ownerA->id);

    try {
        $this->bookingService->cancelAppointment($appointment, $this->ownerA->id);
    } catch (Exception $e) {
        expect($e->getMessage())->toBe('Appointment cannot be cancelled in its current state.');
        expect($e->getCode())->toBe(422);
    }
});

test('reschedule requires valid reservation for new slot', function () {
    $start = $this->date->copy()->setTime(10, 0);
    $end = $start->copy()->addMinutes(35);
    $token = 'reschedule-old-token';

    $this->availabilityService->createReservation(
        $this->tenantA->id,
        $this->branchA->id,
        $this->staffA->id,
        $start,
        $end,
        $token
    );

    $result = $this->bookingService->createBooking([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'customer_profile_id' => $this->customerA->id,
        'staff_id' => $this->staffA->id,
        'service_ids' => [$this->serviceA->id],
        'starts_at' => $start,
        'ends_at' => $end,
        'session_token' => $token,
    ]);

    $appointment = $result['appointment'];

    // Try to reschedule without valid reservation for new slot
    $newStart = $this->date->copy()->setTime(11, 0);
    $newEnd = $newStart->copy()->addMinutes(35);

    try {
        $this->bookingService->rescheduleAppointment(
            $appointment,
            $this->staffA->id,
            $newStart,
            $newEnd,
            'invalid-new-token',
            $this->ownerA->id
        );
    } catch (Exception $e) {
        expect($e->getMessage())->toBe('Invalid or expired reservation for new time slot.');
        expect($e->getCode())->toBe(409);
    }
});

test('prune expired reservations command works', function () {
    $start = $this->date->copy()->setTime(10, 0);
    $end = $start->copy()->addMinutes(35);

    // Create expired reservation
    SlotReservation::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'staff_id' => $this->staffA->id,
        'start_time' => $start,
        'end_time' => $end,
        'session_token_hash' => 'expired',
        'expires_at' => now()->subMinutes(5),
    ]);

    // Create active reservation
    SlotReservation::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'staff_id' => $this->staffA->id,
        'start_time' => $start->copy()->addHour(),
        'end_time' => $end->copy()->addHour(),
        'session_token_hash' => 'active',
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->artisan('reservations:prune-expired')
        ->expectsOutput('Deleted 1 expired reservation(s).')
        ->assertExitCode(0);

    expect(SlotReservation::count())->toBe(1);
    expect(SlotReservation::first()->session_token_hash)->toBe('active');
});

test('appointment events record full audit trail', function () {
    $start = $this->date->copy()->setTime(10, 0);
    $end = $start->copy()->addMinutes(35);
    $token = 'audit-token';

    $this->availabilityService->createReservation(
        $this->tenantA->id,
        $this->branchA->id,
        $this->staffA->id,
        $start,
        $end,
        $token
    );

    $result = $this->bookingService->createBooking([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'customer_profile_id' => $this->customerA->id,
        'staff_id' => $this->staffA->id,
        'service_ids' => [$this->serviceA->id],
        'starts_at' => $start,
        'ends_at' => $end,
        'session_token' => $token,
    ]);

    $appointment = $result['appointment'];

    // Cancel
    $this->bookingService->cancelAppointment($appointment, $this->ownerA->id, 'Customer request');

    $events = $appointment->fresh()->events;
    expect($events->count())->toBe(2);

    $createdEvent = $events->where('event_type', 'created')->first();
    expect($createdEvent->from_status)->toBeNull();
    expect($createdEvent->to_status)->toBe('confirmed');
    expect($createdEvent->from_payment_status)->toBeNull();
    expect($createdEvent->to_payment_status)->toBe('pay_at_salon');

    $cancelledEvent = $events->where('event_type', 'cancelled')->first();
    expect($cancelledEvent->from_status)->toBe('confirmed');
    expect($cancelledEvent->to_status)->toBe('cancelled');
    expect($cancelledEvent->description)->toBe('Customer request');
});
