<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchHour;
use App\Models\Service;
use App\Models\SlotReservation;
use App\Models\Staff;
use App\Models\StaffShift;
use App\Models\StaffTimeOff;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AvailabilityService
{
    public const SLOT_INTERVAL_MINUTES = 15;

    public const RESERVATION_HOLD_MINUTES = 10;

    /**
     * Get available time slots for a staff member at a branch for a given date and services.
     *
     * @return Collection<int, array{start: Carbon, end: Carbon, available: bool}>
     */
    public function getAvailableSlots(int $branchId, int $staffId, Carbon $date, array $serviceIds): Collection
    {
        $branch = Branch::findOrFail($branchId);
        $staff = Staff::findOrFail($staffId);

        // Validate staff belongs to branch
        if (! $staff->isAssignedToBranch($branch)) {
            return collect();
        }

        // Validate all services belong to the tenant and are available at this branch
        $services = Service::whereIn('id', $serviceIds)
            ->where('tenant_id', $branch->tenant_id)
            ->where('is_active', true)
            ->get();

        if ($services->count() !== count($serviceIds)) {
            return collect();
        }

        foreach ($services as $service) {
            if (! $service->isAvailableAtBranch($branch)) {
                return collect();
            }
        }

        // Validate staff can perform all requested services
        foreach ($services as $service) {
            if (! $staff->canPerformService($service)) {
                return collect();
            }
        }

        // Calculate total duration needed (sum of all services with their buffers)
        $totalDuration = $services->sum(fn (Service $s) => $s->getTotalDurationMinutes());

        // Get branch hours for the day of week
        $dayOfWeek = (int) $date->format('w'); // 0 = Sunday
        $branchHours = BranchHour::where('branch_id', $branchId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_closed', false)
            ->first();

        if (! $branchHours) {
            return collect(); // Branch closed this day
        }

        // Get staff shift for the day
        $staffShift = StaffShift::where('staff_id', $staffId)
            ->where('branch_id', $branchId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_working', true)
            ->first();

        if (! $staffShift) {
            return collect(); // Staff not working this day
        }

        // Check staff time off
        $timeOff = StaffTimeOff::where('staff_id', $staffId)
            ->where('starts_at', '<=', $date->copy()->endOfDay())
            ->where('ends_at', '>=', $date->copy()->startOfDay())
            ->exists();

        if ($timeOff) {
            return collect(); // Staff on time off
        }

        // Determine working window (intersection of branch hours and staff shift)
        $branchOpen = Carbon::parse($branchHours->open_time)->setDate($date->year, $date->month, $date->day);
        $branchClose = Carbon::parse($branchHours->close_time)->setDate($date->year, $date->month, $date->day);
        $staffStart = Carbon::parse($staffShift->start_time)->setDate($date->year, $date->month, $date->day);
        $staffEnd = Carbon::parse($staffShift->end_time)->setDate($date->year, $date->month, $date->day);

        $windowStart = $branchOpen->max($staffStart);
        $windowEnd = $branchClose->min($staffEnd);

        if ($windowStart->diffInMinutes($windowEnd) < $totalDuration) {
            return collect(); // Not enough time in window
        }

        // Get confirmed appointments for this staff/branch on this date
        $appointments = Appointment::where('branch_id', $branchId)
            ->where('staff_id', $staffId)
            ->whereIn('status', [AppointmentStatus::PENDING->value, AppointmentStatus::CONFIRMED->value])
            ->whereDate('starts_at', $date)
            ->get(['starts_at', 'ends_at']);

        // Get active slot reservations for this staff/branch
        $reservations = SlotReservation::where('branch_id', $branchId)
            ->where('staff_id', $staffId)
            ->where('expires_at', '>', now())
            ->get(['start_time', 'end_time']);

        // Build blocked time ranges
        $blockedRanges = collect();

        foreach ($appointments as $appt) {
            $blockedRanges->push([
                'start' => Carbon::parse($appt->starts_at),
                'end' => Carbon::parse($appt->ends_at),
            ]);
        }

        foreach ($reservations as $res) {
            $blockedRanges->push([
                'start' => Carbon::parse($res->start_time),
                'end' => Carbon::parse($res->end_time),
            ]);
        }

        // Generate available slots
        $slots = collect();
        $current = $windowStart->copy();

        while ($current->copy()->addMinutes($totalDuration)->lte($windowEnd)) {
            $slotEnd = $current->copy()->addMinutes($totalDuration);

            // Check if slot overlaps with any blocked range
            $isAvailable = ! $blockedRanges->contains(function ($range) use ($current, $slotEnd) {
                return $current->lt($range['end']) && $slotEnd->gt($range['start']);
            });

            $slots->push([
                'start' => $current->copy(),
                'end' => $slotEnd->copy(),
                'available' => $isAvailable,
            ]);

            $current->addMinutes(self::SLOT_INTERVAL_MINUTES);
        }

        return $slots;
    }

    /**
     * Check if a specific time slot is available for booking.
     */
    public function isSlotAvailable(
        int $branchId,
        int $staffId,
        Carbon $startTime,
        Carbon $endTime,
        array $serviceIds
    ): bool {
        $slots = $this->getAvailableSlots($branchId, $staffId, $startTime->copy(), $serviceIds);

        return $slots->contains(function ($slot) use ($startTime, $endTime) {
            return $slot['start']->eq($startTime) && $slot['end']->eq($endTime) && $slot['available'];
        });
    }

    /**
     * Create a temporary slot reservation (10-minute hold).
     */
    public function createReservation(
        int $tenantId,
        int $branchId,
        int $staffId,
        Carbon $startTime,
        Carbon $endTime,
        string $sessionToken
    ): SlotReservation {
        $sessionTokenHash = hash('sha256', $sessionToken);

        return SlotReservation::create([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'staff_id' => $staffId,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'session_token_hash' => $sessionTokenHash,
            'expires_at' => now()->addMinutes(self::RESERVATION_HOLD_MINUTES),
        ]);
    }

    /**
     * Find and consume a reservation by session token.
     */
    public function consumeReservation(string $sessionToken): ?SlotReservation
    {
        $sessionTokenHash = hash('sha256', $sessionToken);

        $reservation = SlotReservation::where('session_token_hash', $sessionTokenHash)
            ->where('expires_at', '>', now())
            ->first();

        if ($reservation) {
            $reservation->delete();
        }

        return $reservation;
    }

    /**
     * Clean up expired reservations.
     */
    public function pruneExpiredReservations(): int
    {
        return SlotReservation::where('expires_at', '<=', now())->delete();
    }
}
