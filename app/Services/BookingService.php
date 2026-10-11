<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Branch;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class BookingService
{
    public function __construct(
        protected AvailabilityService $availabilityService
    ) {}

    /**
     * Create a booking with concurrency protection.
     *
     * @param  array{
     *     tenant_id: int,
     *     branch_id: int,
     *     customer_profile_id: int,
     *     staff_id: int,
     *     service_ids: int[],
     *     starts_at: Carbon,
     *     ends_at: Carbon,
     *     session_token: string,
     *     notes?: string,
     *     metadata?: array
     * }  $data
     * @return array{appointment: Appointment, services: Collection<int, AppointmentService>}
     *
     * @throws \Exception
     */
    public function createBooking(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // Lock the tenant row to prevent race conditions on branch limits etc.
            Tenant::where('id', $data['tenant_id'])->lockForUpdate()->first();

            // Verify and consume the slot reservation
            $reservation = $this->availabilityService->consumeReservation($data['session_token']);

            if (! $reservation) {
                throw new \Exception('Invalid or expired reservation token.', 409);
            }

            // Verify reservation matches the booking request
            if ($reservation->branch_id !== $data['branch_id']
                || $reservation->staff_id !== $data['staff_id']
                || ! $reservation->start_time->eq($data['starts_at'])
                || ! $reservation->end_time->eq($data['ends_at'])) {
                throw new \Exception('Reservation does not match booking details.', 409);
            }

            // Lock staff appointments for the time range to prevent double booking
            $this->lockStaffSlot($data['staff_id'], $data['starts_at'], $data['ends_at']);

            // Re-verify availability at confirmation time
            if (! $this->availabilityService->isSlotAvailable(
                $data['branch_id'],
                $data['staff_id'],
                $data['starts_at'],
                $data['ends_at'],
                $data['service_ids']
            )) {
                throw new \Exception('Slot no longer available.', 409);
            }

            // Get services and validate they belong to the tenant
            $services = Service::whereIn('id', $data['service_ids'])
                ->where('tenant_id', $data['tenant_id'])
                ->where('is_active', true)
                ->get();

            if ($services->count() !== count($data['service_ids'])) {
                throw new \Exception('One or more services not found or inactive.', 422);
            }

            // Verify all services are available at this branch and staff can perform them
            $staff = Staff::findOrFail($data['staff_id']);
            foreach ($services as $service) {
                if (! $service->isAvailableAtBranch($data['branch_id'])) {
                    throw new \Exception("Service {$service->name} not available at this branch.", 422);
                }
                if (! $staff->canPerformService($service)) {
                    throw new \Exception("Staff member cannot perform service {$service->name}.", 422);
                }
            }

            // Calculate total price and duration (using branch-specific pricing)
            $totalPrice = $services->sum(fn (Service $s) => $s->getPriceForBranch($data['branch_id']));
            $totalDuration = $services->sum(fn (Service $s) => $s->getDurationForBranch($data['branch_id']))
                + $services->sum(fn (Service $s) => $s->getBufferForBranch($data['branch_id']));

            // Create appointment
            $appointment = Appointment::create([
                'tenant_id' => $data['tenant_id'],
                'branch_id' => $data['branch_id'],
                'customer_profile_id' => $data['customer_profile_id'],
                'staff_id' => $data['staff_id'],
                'status' => AppointmentStatus::CONFIRMED,
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'total_price_inr' => $totalPrice,
                'total_duration_minutes' => $totalDuration,
                'notes' => $data['notes'] ?? null,
                'payment_status' => PaymentStatus::PAY_AT_SALON,
                'metadata' => $data['metadata'] ?? null,
            ]);

            // Create appointment services with frozen pricing
            $appointmentServices = collect();
            foreach ($services as $service) {
                $apptService = AppointmentService::create([
                    'appointment_id' => $appointment->id,
                    'service_id' => $service->id,
                    'price_inr' => $service->getPriceForBranch($data['branch_id']),
                    'duration_minutes' => $service->getDurationForBranch($data['branch_id']),
                    'buffer_minutes' => $service->getBufferForBranch($data['branch_id']),
                ]);
                $appointmentServices->push($apptService);
            }

            // Record audit event
            $appointment->recordEvent(
                'created',
                null,
                AppointmentStatus::CONFIRMED->value,
                null,
                PaymentStatus::PAY_AT_SALON->value,
                'Appointment booked and confirmed.',
                ['service_ids' => $data['service_ids']]
            );

            return [
                'appointment' => $appointment,
                'services' => $appointmentServices,
            ];
        });
    }

    /**
     * Cancel an appointment.
     */
    public function cancelAppointment(Appointment $appointment, int $userId, string $reason = 'Cancelled by user'): Appointment
    {
        return DB::transaction(function () use ($appointment, $userId, $reason) {
            $appointment->load('services');

            if (! $appointment->canTransitionTo(AppointmentStatus::CANCELLED)) {
                throw new \Exception('Appointment cannot be cancelled in its current state.', 422);
            }

            $oldStatus = $appointment->status;

            $appointment->update([
                'status' => AppointmentStatus::CANCELLED,
            ]);

            $appointment->recordEvent(
                'cancelled',
                $oldStatus->value,
                AppointmentStatus::CANCELLED->value,
                null,
                null,
                $reason,
                ['cancelled_by_user_id' => $userId],
                $userId
            );

            return $appointment->fresh();
        });
    }

    /**
     * Reschedule an appointment to a new time slot.
     */
    public function rescheduleAppointment(
        Appointment $appointment,
        int $staffId,
        Carbon $newStartTime,
        Carbon $newEndTime,
        string $sessionToken,
        int $userId
    ): Appointment {
        return DB::transaction(function () use ($appointment, $staffId, $newStartTime, $newEndTime, $sessionToken, $userId) {
            // Only confirmed or pending appointments can be rescheduled
            if (! in_array($appointment->status, [AppointmentStatus::CONFIRMED, AppointmentStatus::PENDING], true)) {
                throw new \Exception('Only confirmed or pending appointments can be rescheduled.', 422);
            }

            // Lock the old slot
            $this->lockStaffSlot($appointment->staff_id, $appointment->starts_at, $appointment->ends_at);

            // Verify and consume new reservation
            $reservation = $this->availabilityService->consumeReservation($sessionToken);

            if (! $reservation
                || $reservation->staff_id !== $staffId
                || ! $reservation->start_time->eq($newStartTime)
                || ! $reservation->end_time->eq($newEndTime)) {
                throw new \Exception('Invalid or expired reservation for new time slot.', 409);
            }

            // Lock new slot
            $this->lockStaffSlot($staffId, $newStartTime, $newEndTime);

            // Re-verify availability
            $serviceIds = $appointment->services->pluck('service_id')->toArray();
            if (! $this->availabilityService->isSlotAvailable(
                $appointment->branch_id,
                $staffId,
                $newStartTime,
                $newEndTime,
                $serviceIds
            )) {
                throw new \Exception('New time slot no longer available.', 409);
            }

            $oldStart = $appointment->starts_at;
            $oldEnd = $appointment->ends_at;
            $oldStaffId = $appointment->staff_id;

            $appointment->update([
                'staff_id' => $staffId,
                'starts_at' => $newStartTime,
                'ends_at' => $newEndTime,
            ]);

            $appointment->recordEvent(
                'rescheduled',
                AppointmentStatus::CONFIRMED->value,
                AppointmentStatus::CONFIRMED->value,
                null,
                null,
                "Rescheduled from {$oldStart->format('Y-m-d H:i')} to {$newStartTime->format('Y-m-d H:i')}",
                [
                    'old_starts_at' => $oldStart->toIso8601String(),
                    'old_ends_at' => $oldEnd->toIso8601String(),
                    'old_staff_id' => $oldStaffId,
                    'new_staff_id' => $staffId,
                    'rescheduled_by_user_id' => $userId,
                ],
                $userId
            );

            return $appointment->fresh();
        });
    }

    /**
     * Mark appointment as completed.
     */
    public function completeAppointment(Appointment $appointment, int $userId): Appointment
    {
        return DB::transaction(function () use ($appointment, $userId) {
            if (! $appointment->canTransitionTo(AppointmentStatus::COMPLETED)) {
                throw new \Exception('Appointment cannot be completed in its current state.', 422);
            }

            $oldStatus = $appointment->status;

            $appointment->update([
                'status' => AppointmentStatus::COMPLETED,
            ]);

            $appointment->recordEvent(
                'completed',
                $oldStatus->value,
                AppointmentStatus::COMPLETED->value,
                null,
                null,
                'Appointment marked as completed.',
                ['completed_by_user_id' => $userId],
                $userId
            );

            return $appointment->fresh();
        });
    }

    /**
     * Mark appointment as no-show.
     */
    public function markNoShow(Appointment $appointment, int $userId): Appointment
    {
        return DB::transaction(function () use ($appointment, $userId) {
            if (! $appointment->canTransitionTo(AppointmentStatus::NO_SHOW)) {
                throw new \Exception('Appointment cannot be marked as no-show in its current state.', 422);
            }

            $oldStatus = $appointment->status;

            $appointment->update([
                'status' => AppointmentStatus::NO_SHOW,
            ]);

            $appointment->recordEvent(
                'no_show',
                $oldStatus->value,
                AppointmentStatus::NO_SHOW->value,
                null,
                null,
                'Appointment marked as no-show.',
                ['marked_by_user_id' => $userId],
                $userId
            );

            return $appointment->fresh();
        });
    }

    /**
     * Update payment status with audit trail.
     */
    public function updatePaymentStatus(
        Appointment $appointment,
        PaymentStatus $newStatus,
        ?int $userId = null,
        ?array $metadata = null
    ): Appointment {
        return DB::transaction(function () use ($appointment, $newStatus, $userId, $metadata) {
            if (! $appointment->payment_status->canTransitionTo($newStatus)) {
                throw new \Exception("Cannot transition payment from {$appointment->payment_status->value} to {$newStatus->value}.", 422);
            }

            $oldPaymentStatus = $appointment->payment_status;

            $appointment->update([
                'payment_status' => $newStatus,
            ]);

            $appointment->recordEvent(
                'payment_status_changed',
                null,
                null,
                $oldPaymentStatus->value,
                $newStatus->value,
                "Payment status changed from {$oldPaymentStatus->value} to {$newStatus->value}.",
                $metadata,
                $userId
            );

            return $appointment->fresh();
        });
    }

    /**
     * Lock staff slot for booking using pessimistic locking.
     * This prevents concurrent bookings for the same staff/time.
     */
    protected function lockStaffSlot(int $staffId, Carbon $startTime, Carbon $endTime): void
    {
        // Lock any overlapping confirmed/pending appointments for this staff
        Appointment::where('staff_id', $staffId)
            ->whereIn('status', [AppointmentStatus::PENDING->value, AppointmentStatus::CONFIRMED->value])
            ->where(function ($query) use ($startTime, $endTime) {
                $query->where('starts_at', '<', $endTime)
                    ->where('ends_at', '>', $startTime);
            })
            ->lockForUpdate()
            ->get();
    }

    /**
     * Create a walk-in appointment (no prior reservation needed, staff must be available now).
     */
    public function createWalkIn(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // Lock tenant
            Tenant::where('id', $data['tenant_id'])->lockForUpdate()->first();

            // Lock staff slot
            $this->lockStaffSlot($data['staff_id'], $data['starts_at'], $data['ends_at']);

            // Verify availability without reservation
            if (! $this->availabilityService->isSlotAvailable(
                $data['branch_id'],
                $data['staff_id'],
                $data['starts_at'],
                $data['ends_at'],
                $data['service_ids']
            )) {
                throw new \Exception('Staff not available at requested time.', 409);
            }

            // Get services
            $services = Service::whereIn('id', $data['service_ids'])
                ->where('tenant_id', $data['tenant_id'])
                ->where('is_active', true)
                ->get();

            if ($services->count() !== count($data['service_ids'])) {
                throw new \Exception('One or more services not found or inactive.', 422);
            }

            $staff = Staff::findOrFail($data['staff_id']);
            foreach ($services as $service) {
                if (! $service->isAvailableAtBranch($data['branch_id'])) {
                    throw new \Exception("Service {$service->name} not available at this branch.", 422);
                }
                if (! $staff->canPerformService($service)) {
                    throw new \Exception("Staff member cannot perform service {$service->name}.", 422);
                }
            }

            $totalPrice = $services->sum(fn (Service $s) => $s->getPriceForBranch($data['branch_id']));
            $totalDuration = $services->sum(fn (Service $s) => $s->getDurationForBranch($data['branch_id']))
                + $services->sum(fn (Service $s) => $s->getBufferForBranch($data['branch_id']));

            $appointment = Appointment::create([
                'tenant_id' => $data['tenant_id'],
                'branch_id' => $data['branch_id'],
                'customer_profile_id' => $data['customer_profile_id'],
                'staff_id' => $data['staff_id'],
                'status' => AppointmentStatus::COMPLETED, // Walk-ins are immediately completed
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'total_price_inr' => $totalPrice,
                'total_duration_minutes' => $totalDuration,
                'notes' => $data['notes'] ?? null,
                'payment_status' => PaymentStatus::PAY_AT_SALON,
                'metadata' => array_merge($data['metadata'] ?? [], ['walk_in' => true]),
            ]);

            $appointmentServices = collect();
            foreach ($services as $service) {
                $apptService = AppointmentService::create([
                    'appointment_id' => $appointment->id,
                    'service_id' => $service->id,
                    'price_inr' => $service->getPriceForBranch($data['branch_id']),
                    'duration_minutes' => $service->getDurationForBranch($data['branch_id']),
                    'buffer_minutes' => $service->getBufferForBranch($data['branch_id']),
                ]);
                $appointmentServices->push($apptService);
            }

            $appointment->recordEvent(
                'walk_in_created',
                null,
                AppointmentStatus::COMPLETED->value,
                null,
                PaymentStatus::PAY_AT_SALON->value,
                'Walk-in appointment created and completed.',
                ['service_ids' => $data['service_ids']],
                $data['user_id'] ?? null
            );

            return [
                'appointment' => $appointment,
                'services' => $appointmentServices,
            ];
        });
    }
}
