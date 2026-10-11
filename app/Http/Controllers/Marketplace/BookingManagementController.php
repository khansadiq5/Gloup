<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Staff;
use App\Models\StaffShift;
use App\Models\Tenant;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class BookingManagementController extends Controller
{
    public function __construct(
        protected BookingService $bookingService
    ) {}

    /**
     * Show booking confirmation page.
     */
    public function confirmation(Tenant $tenant, Appointment $appointment)
    {
        // Verify appointment belongs to tenant
        if ($appointment->tenant_id !== $tenant->id) {
            abort(404);
        }

        // Check if user has access (owner of booking or authenticated customer)
        $hasAccess = $this->checkAccess($appointment);

        if (! $hasAccess) {
            return redirect()->route('marketplace.booking.verify', [
                'tenant' => $tenant->slug,
                'appointment' => $appointment->id,
            ]);
        }

        $appointment->load(['branch', 'staff', 'services.service', 'customerProfile']);

        return view('marketplace.booking.confirmation', compact('appointment', 'tenant'));
    }

    /**
     * Show verification page for guest bookings.
     */
    public function verify(Tenant $tenant, Appointment $appointment)
    {
        if ($appointment->tenant_id !== $tenant->id) {
            abort(404);
        }

        // If already verified in session, redirect to confirmation
        if (session()->has("booking_verified_{$appointment->id}")) {
            return redirect()->route('marketplace.booking.confirmation', [
                'tenant' => $tenant->slug,
                'appointment' => $appointment->id,
            ]);
        }

        return view('marketplace.booking.verify', compact('appointment', 'tenant'));
    }

    /**
     * Verify guest access via email/phone or management token.
     */
    public function verifyAccess(Tenant $tenant, Appointment $appointment, Request $request)
    {
        if ($appointment->tenant_id !== $tenant->id) {
            abort(404);
        }

        $key = "verify:{$appointment->id}:{$request->ip()}";
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'verification' => 'Too many verification attempts. Please try again later.',
            ]);
        }

        $request->validate([
            'verification_method' => 'required|in:email,phone,token',
            'email' => 'nullable|required_if:verification_method,email|email',
            'phone' => 'nullable|required_if:verification_method,phone|string',
            'token' => 'nullable|required_if:verification_method,token|string',
        ]);

        $verified = false;

        switch ($request->verification_method) {
            case 'email':
                $verified = $appointment->customerProfile->email === $request->email;
                break;
            case 'phone':
                $verified = $appointment->customerProfile->phone === $request->phone;
                break;
            case 'token':
                $verified = $appointment->management_token === $request->token;
                break;
        }

        if ($verified) {
            session()->put("booking_verified_{$appointment->id}", true);
            RateLimiter::clear($key);

            return redirect()->route('marketplace.booking.confirmation', [
                'tenant' => $tenant->slug,
                'appointment' => $appointment->id,
            ]);
        }

        RateLimiter::hit($key, 300);

        return back()->withErrors([
            'verification' => 'Invalid verification details. Please try again.',
        ]);
    }

    /**
     * Show booking management page (cancel, reschedule).
     */
    public function manage(Tenant $tenant, Appointment $appointment)
    {
        if ($appointment->tenant_id !== $tenant->id) {
            abort(404);
        }

        $hasAccess = $this->checkAccess($appointment);

        if (! $hasAccess) {
            return redirect()->route('marketplace.booking.verify', [
                'tenant' => $tenant->slug,
                'appointment' => $appointment->id,
            ]);
        }

        $appointment->load(['branch', 'staff', 'services.service', 'customerProfile', 'tenant']);

        // Get available dates for rescheduling
        $availableDates = $this->getAvailableDates($appointment->branch, 30);

        // Get available staff for rescheduling
        $staff = Staff::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereHas('branches', function ($q) use ($appointment) {
                $q->where('branch_id', $appointment->branch_id);
            })
            ->whereHas('services', function ($q) use ($appointment) {
                $serviceIds = $appointment->services->pluck('service_id');
                $q->whereIn('services.id', $serviceIds);
            })
            ->with('services')
            ->get();

        return view('marketplace.booking.manage', compact(
            'appointment',
            'tenant',
            'availableDates',
            'staff'
        ));
    }

    /**
     * Cancel appointment.
     */
    public function cancel(Tenant $tenant, Appointment $appointment, Request $request)
    {
        if ($appointment->tenant_id !== $tenant->id) {
            abort(404);
        }

        $hasAccess = $this->checkAccess($appointment);

        if (! $hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $userId = Auth::id() ?? $appointment->customerProfile->user_id;

            $this->bookingService->cancelAppointment(
                $appointment,
                $userId ?? 1, // Fallback for system cancellation
                $request->reason
            );

            return response()->json([
                'success' => true,
                'message' => 'Appointment cancelled successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 422);
        }
    }

    /**
     * Reschedule appointment.
     */
    public function reschedule(Tenant $tenant, Appointment $appointment, Request $request)
    {
        if ($appointment->tenant_id !== $tenant->id) {
            abort(404);
        }

        $hasAccess = $this->checkAccess($appointment);

        if (! $hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'session_token' => 'required|string',
        ]);

        try {
            $userId = Auth::id() ?? $appointment->customerProfile->user_id;

            $newStart = Carbon::parse($request->starts_at);
            $newEnd = Carbon::parse($request->ends_at);

            $rescheduled = $this->bookingService->rescheduleAppointment(
                $appointment,
                $request->staff_id,
                $newStart,
                $newEnd,
                $request->session_token,
                $userId ?? 1
            );

            return response()->json([
                'success' => true,
                'message' => 'Appointment rescheduled successfully.',
                'new_start' => $rescheduled->starts_at->format('Y-m-d H:i'),
                'new_end' => $rescheduled->ends_at->format('Y-m-d H:i'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 422);
        }
    }

    /**
     * Get available slots for rescheduling.
     */
    public function getRescheduleSlots(Tenant $tenant, Appointment $appointment, Request $request)
    {
        if ($appointment->tenant_id !== $tenant->id) {
            abort(404);
        }

        $hasAccess = $this->checkAccess($appointment);

        if (! $hasAccess) {
            return response()->json(['slots' => []], 403);
        }

        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'staff_id' => 'required|exists:staff,id',
            'date' => 'required|date|after_or_equal:today',
        ]);

        $branch = Branch::findOrFail($request->branch_id);
        $staff = Staff::findOrFail($request->staff_id);
        $date = Carbon::parse($request->date);

        $serviceIds = $appointment->services->pluck('service_id')->toArray();

        $availabilityService = app(AvailabilityService::class);
        $slots = $availabilityService->getAvailableSlots(
            $branch->id,
            $staff->id,
            $date,
            $serviceIds
        );

        $availableSlots = $slots->where('available', true)->map(function ($slot) {
            return [
                'start' => $slot['start']->format('H:i'),
                'end' => $slot['end']->format('H:i'),
                'start_iso' => $slot['start']->toIso8601String(),
                'end_iso' => $slot['end']->toIso8601String(),
            ];
        })->values();

        return response()->json(['slots' => $availableSlots]);
    }

    /**
     * Create reservation for rescheduling.
     */
    public function createRescheduleReservation(Tenant $tenant, Appointment $appointment, Request $request)
    {
        if ($appointment->tenant_id !== $tenant->id) {
            abort(404);
        }

        $hasAccess = $this->checkAccess($appointment);

        if (! $hasAccess) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'staff_id' => 'required|exists:staff,id',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'service_ids' => 'required|array|min:1',
        ]);

        $branch = Branch::findOrFail($request->branch_id);
        $staff = Staff::findOrFail($request->staff_id);
        $startTime = Carbon::parse($request->start_time);
        $endTime = Carbon::parse($request->end_time);
        $serviceIds = $request->service_ids;

        // Verify availability
        $availabilityService = app(AvailabilityService::class);
        if (! $availabilityService->isSlotAvailable(
            $branch->id,
            $staff->id,
            $startTime,
            $endTime,
            $serviceIds
        )) {
            return response()->json([
                'success' => false,
                'message' => 'Slot no longer available.',
            ], 409);
        }

        $sessionToken = $availabilityService::generateSessionToken();
        $reservation = $availabilityService->createReservation(
            $branch->tenant_id,
            $branch->id,
            $staff->id,
            $startTime,
            $endTime,
            $sessionToken
        );

        return response()->json([
            'success' => true,
            'session_token' => $sessionToken,
            'expires_at' => $reservation->expires_at->toIso8601String(),
            'hold_minutes' => AvailabilityService::RESERVATION_HOLD_MINUTES,
        ]);
    }

    /**
     * Check if current user/session has access to this appointment.
     */
    protected function checkAccess(Appointment $appointment): bool
    {
        // Authenticated user who owns the booking
        if (Auth::check()) {
            $user = Auth::user();
            if ($appointment->customerProfile->user_id === $user->id) {
                return true;
            }
            // Platform admin or tenant staff
            if ($user->isPlatformAdmin()) {
                return true;
            }
            if ($user->belongsToTenant($appointment->tenant_id)) {
                return true;
            }
        }

        // Guest verified via session
        if (session()->has("booking_verified_{$appointment->id}")) {
            return true;
        }

        return false;
    }

    /**
     * Get available dates for rescheduling.
     */
    protected function getAvailableDates(Branch $branch, int $days = 30): array
    {
        $dates = [];
        $today = Carbon::today();

        for ($i = 0; $i < $days; $i++) {
            $date = $today->copy()->addDays($i);
            $dayOfWeek = (int) $date->format('w');

            $hasHours = $branch->branchHours->contains(function ($hours) use ($dayOfWeek) {
                return $hours->day_of_week === $dayOfWeek && ! $hours->is_closed;
            });

            $hasStaffShift = StaffShift::where('branch_id', $branch->id)
                ->where('day_of_week', $dayOfWeek)
                ->where('is_working', true)
                ->exists();

            if ($hasHours && $hasStaffShift) {
                $dates[] = [
                    'date' => $date->format('Y-m-d'),
                    'label' => $date->format('D, M j'),
                    'day_name' => $date->format('l'),
                ];
            }
        }

        return $dates;
    }
}
