<?php

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchHour;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SlotReservation;
use App\Models\Staff;
use App\Models\StaffShift;
use App\Models\StaffTimeOff;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create();
    TenantUser::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'role' => 'owner',
    ]);

    $this->branch = Branch::factory()->primary()->create(['tenant_id' => $this->tenant->id]);
    $this->serviceCategory = ServiceCategory::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->service = Service::factory()->create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->serviceCategory->id,
        'duration_minutes' => 30,
        'buffer_minutes' => 5,
        'price_inr' => 1000,
    ]);
    $this->staff = Staff::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->staff->branches()->attach($this->branch->id);
    $this->staff->services()->attach($this->service->id);

    $this->date = Carbon::now()->startOfDay()->addWeek(); // Next week same day
    $this->dayOfWeek = (int) $this->date->format('w');

    // Setup branch hours (9 AM - 8 PM)
    BranchHour::factory()->create([
        'branch_id' => $this->branch->id,
        'day_of_week' => $this->dayOfWeek,
        'open_time' => '09:00:00',
        'close_time' => '20:00:00',
        'is_closed' => false,
    ]);

    // Setup staff shift (9 AM - 6 PM)
    StaffShift::factory()->create([
        'staff_id' => $this->staff->id,
        'branch_id' => $this->branch->id,
        'day_of_week' => $this->dayOfWeek,
        'start_time' => '09:00:00',
        'end_time' => '18:00:00',
        'is_working' => true,
    ]);

    $this->availabilityService = app(AvailabilityService::class);
});

test('returns empty collection when branch is closed on that day', function () {
    BranchHour::where('branch_id', $this->branch->id)->delete();
    BranchHour::factory()->create([
        'branch_id' => $this->branch->id,
        'day_of_week' => $this->dayOfWeek,
        'is_closed' => true,
    ]);

    $slots = $this->availabilityService->getAvailableSlots(
        $this->branch->id,
        $this->staff->id,
        $this->date,
        [$this->service->id]
    );

    expect($slots)->toBeEmpty();
});

test('returns empty collection when staff is not working on that day', function () {
    StaffShift::where('staff_id', $this->staff->id)->delete();
    StaffShift::factory()->create([
        'staff_id' => $this->staff->id,
        'branch_id' => $this->branch->id,
        'day_of_week' => $this->dayOfWeek,
        'is_working' => false,
    ]);

    $slots = $this->availabilityService->getAvailableSlots(
        $this->branch->id,
        $this->staff->id,
        $this->date,
        [$this->service->id]
    );

    expect($slots)->toBeEmpty();
});

test('returns empty collection when staff is on time off', function () {
    StaffTimeOff::factory()->create([
        'staff_id' => $this->staff->id,
        'starts_at' => $this->date->copy()->subDay(),
        'ends_at' => $this->date->copy()->addDay(),
    ]);

    $slots = $this->availabilityService->getAvailableSlots(
        $this->branch->id,
        $this->staff->id,
        $this->date,
        [$this->service->id]
    );

    expect($slots)->toBeEmpty();
});

test('returns available slots within working hours', function () {
    $slots = $this->availabilityService->getAvailableSlots(
        $this->branch->id,
        $this->staff->id,
        $this->date,
        [$this->service->id]
    );

    expect($slots)->not->toBeEmpty();

    // Service is 30 min + 5 buffer = 35 min total
    // Slots every 15 min from 09:00 to 17:25 (since 18:00 is shift end)
    $availableSlots = $slots->where('available', true);
    expect($availableSlots->count())->toBeGreaterThan(0);

    // First slot should be at 09:00
    expect($availableSlots->first()['start']->format('H:i'))->toBe('09:00');
    // Last available slot should end by 18:00
    $lastSlot = $availableSlots->last();
    expect($lastSlot['end']->lte(Carbon::parse($this->date->format('Y-m-d').' 18:00')))->toBeTrue();
});

test('marks slots as unavailable when overlapping with confirmed appointment', function () {
    // Create a confirmed appointment at 10:00 - 10:35
    $apptStart = $this->date->copy()->setTime(10, 0);
    $apptEnd = $apptStart->copy()->addMinutes(35);

    Appointment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'branch_id' => $this->branch->id,
        'staff_id' => $this->staff->id,
        'status' => AppointmentStatus::CONFIRMED,
        'starts_at' => $apptStart,
        'ends_at' => $apptEnd,
    ]);

    $slots = $this->availabilityService->getAvailableSlots(
        $this->branch->id,
        $this->staff->id,
        $this->date,
        [$this->service->id]
    );

    // Slots overlapping with 10:00-10:35 should be unavailable
    $overlappingSlots = $slots->filter(function ($slot) use ($apptStart, $apptEnd) {
        return $slot['start']->lt($apptEnd) && $slot['end']->gt($apptStart);
    });

    foreach ($overlappingSlots as $slot) {
        expect($slot['available'])->toBeFalse();
    }
});

test('marks slots as unavailable when overlapping with active reservation', function () {
    $resStart = $this->date->copy()->setTime(11, 0);
    $resEnd = $resStart->copy()->addMinutes(35);

    SlotReservation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'branch_id' => $this->branch->id,
        'staff_id' => $this->staff->id,
        'start_time' => $resStart,
        'end_time' => $resEnd,
        'session_token_hash' => 'test',
        'expires_at' => now()->addMinutes(10),
    ]);

    $slots = $this->availabilityService->getAvailableSlots(
        $this->branch->id,
        $this->staff->id,
        $this->date,
        [$this->service->id]
    );

    $overlappingSlots = $slots->filter(function ($slot) use ($resStart, $resEnd) {
        return $slot['start']->lt($resEnd) && $slot['end']->gt($resStart);
    });

    foreach ($overlappingSlots as $slot) {
        expect($slot['available'])->toBeFalse();
    }
});

test('ignores expired reservations', function () {
    $resStart = $this->date->copy()->setTime(11, 0);
    $resEnd = $resStart->copy()->addMinutes(35);

    SlotReservation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'branch_id' => $this->branch->id,
        'staff_id' => $this->staff->id,
        'start_time' => $resStart,
        'end_time' => $resEnd,
        'session_token_hash' => 'test',
        'expires_at' => now()->subMinutes(1), // Expired
    ]);

    $slots = $this->availabilityService->getAvailableSlots(
        $this->branch->id,
        $this->staff->id,
        $this->date,
        [$this->service->id]
    );

    $overlappingSlots = $slots->filter(function ($slot) use ($resStart, $resEnd) {
        return $slot['start']->lt($resEnd) && $slot['end']->gt($resStart);
    });

    foreach ($overlappingSlots as $slot) {
        expect($slot['available'])->toBeTrue();
    }
});

test('calculates total duration correctly for multiple services', function () {
    $service2 = Service::factory()->create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->serviceCategory->id,
        'duration_minutes' => 45,
        'buffer_minutes' => 10,
        'price_inr' => 2000,
    ]);
    $this->staff->services()->attach($service2->id);

    $slots = $this->availabilityService->getAvailableSlots(
        $this->branch->id,
        $this->staff->id,
        $this->date,
        [$this->service->id, $service2->id]
    );

    // Total duration = 35 + 55 = 90 minutes
    $availableSlots = $slots->where('available', true);
    expect($availableSlots->count())->toBeGreaterThan(0);

    // Each slot should be 90 minutes
    foreach ($availableSlots as $slot) {
        expect((int) $slot['start']->diffInMinutes($slot['end']))->toBe(90);
    }
});

test('returns empty when service not available at branch', function () {
    // Create branch service override with is_available = false
    $this->service->branchOverrides()->create([
        'branch_id' => $this->branch->id,
        'price_inr' => null,
        'duration_minutes' => null,
        'buffer_minutes' => null,
        'is_available' => false,
    ]);

    $slots = $this->availabilityService->getAvailableSlots(
        $this->branch->id,
        $this->staff->id,
        $this->date,
        [$this->service->id]
    );

    expect($slots)->toBeEmpty();
});

test('createReservation generates reservation with correct expiry', function () {
    $start = $this->date->copy()->setTime(10, 0);
    $end = $start->copy()->addMinutes(35);
    $token = 'test-session-token';

    $reservation = $this->availabilityService->createReservation(
        $this->tenant->id,
        $this->branch->id,
        $this->staff->id,
        $start,
        $end,
        $token
    );

    expect($reservation)->not->toBeNull();
    expect($reservation->tenant_id)->toBe($this->tenant->id);
    expect($reservation->branch_id)->toBe($this->branch->id);
    expect($reservation->staff_id)->toBe($this->staff->id);
    expect($reservation->start_time->eq($start))->toBeTrue();
    expect($reservation->end_time->eq($end))->toBeTrue();
    // expires_at should be in the future (approximately 10 minutes from now)
    expect($reservation->expires_at->gt(now()))->toBeTrue();
});

test('consumeReservation returns and deletes valid reservation', function () {
    $start = $this->date->copy()->setTime(10, 0);
    $end = $start->copy()->addMinutes(35);
    $token = 'test-session-token';

    $this->availabilityService->createReservation(
        $this->tenant->id,
        $this->branch->id,
        $this->staff->id,
        $start,
        $end,
        $token
    );

    $consumed = $this->availabilityService->consumeReservation($token);

    expect($consumed)->not->toBeNull();
    expect($consumed->start_time->eq($start))->toBeTrue();

    // Reservation should be deleted
    expect(SlotReservation::where('session_token_hash', hash('sha256', $token))->exists())->toBeFalse();
});

test('consumeReservation returns null for expired reservation', function () {
    $start = $this->date->copy()->setTime(10, 0);
    $end = $start->copy()->addMinutes(35);
    $token = 'test-session-token';

    SlotReservation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'branch_id' => $this->branch->id,
        'staff_id' => $this->staff->id,
        'start_time' => $start,
        'end_time' => $end,
        'session_token_hash' => hash('sha256', $token),
        'expires_at' => now()->subMinutes(1),
    ]);

    $consumed = $this->availabilityService->consumeReservation($token);

    expect($consumed)->toBeNull();
});

test('pruneExpiredReservations deletes only expired reservations', function () {
    $start = $this->date->copy()->setTime(10, 0);
    $end = $start->copy()->addMinutes(35);

    // Create expired reservation
    SlotReservation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'branch_id' => $this->branch->id,
        'staff_id' => $this->staff->id,
        'start_time' => $start,
        'end_time' => $end,
        'session_token_hash' => 'expired',
        'expires_at' => now()->subMinutes(5),
    ]);

    // Create active reservation
    SlotReservation::factory()->create([
        'tenant_id' => $this->tenant->id,
        'branch_id' => $this->branch->id,
        'staff_id' => $this->staff->id,
        'start_time' => $start->copy()->addHour(),
        'end_time' => $end->copy()->addHour(),
        'session_token_hash' => 'active',
        'expires_at' => now()->addMinutes(10),
    ]);

    $deleted = $this->availabilityService->pruneExpiredReservations();

    expect($deleted)->toBe(1);
    expect(SlotReservation::count())->toBe(1);
    expect(SlotReservation::first()->session_token_hash)->toBe('active');
});
