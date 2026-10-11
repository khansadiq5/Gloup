<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Staff;
use App\Models\Tenant;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    use AuthorizesRequests;

    /**
     * Show calendar view with appointments.
     */
    public function index(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('viewDashboard', $tenant);

        $branch = $this->getCurrentBranch($request, $tenant);

        if (! $branch) {
            return response()->json([
                'message' => 'No active branch found.',
            ], 404);
        }

        // Date range from query params
        $startDate = $request->query('start_date')
            ? Carbon::parse($request->query('start_date'))->startOfDay()
            : Carbon::today()->startOfWeek();

        $endDate = $request->query('end_date')
            ? Carbon::parse($request->query('end_date'))->endOfDay()
            : Carbon::today()->endOfWeek()->addWeeks(1);

        // Filters
        $staffId = $request->query('staff_id');
        $status = $request->query('status');
        $view = $request->query('view', 'week'); // week, day, month, list

        $query = Appointment::where('branch_id', $branch->id)
            ->whereBetween('starts_at', [$startDate, $endDate])
            ->with(['customerProfile', 'staff', 'services.service']);

        if ($staffId) {
            $query->where('staff_id', $staffId);
        }

        if ($status && in_array($status, array_column(AppointmentStatus::cases(), 'value'))) {
            $query->where('status', $status);
        }

        $appointments = $query->orderBy('starts_at')->get();

        // Get available staff for filter
        $staff = Staff::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereHas('branches', function ($q) use ($branch) {
                $q->where('branch_id', $branch->id);
            })
            ->get(['id', 'name']);

        return response()->json([
            'appointments' => $appointments->map(function ($appt) {
                return [
                    'id' => $appt->id,
                    'title' => $appt->customerProfile->getFullNameAttribute(),
                    'start' => $appt->starts_at->toIso8601String(),
                    'end' => $appt->ends_at->toIso8601String(),
                    'staff_id' => $appt->staff_id,
                    'staff_name' => $appt->staff?->name,
                    'status' => $appt->status->value,
                    'payment_status' => $appt->payment_status->value,
                    'color' => $this->getStatusColor($appt->status),
                    'services' => $appt->services->pluck('service.name')->toArray(),
                    'total_price' => $appt->total_price_inr,
                    'notes' => $appt->notes,
                ];
            }),
            'staff' => $staff,
            'current_branch' => $branch,
            'date_range' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'view' => $view,
        ]);
    }

    /**
     * Get list view of appointments with pagination.
     */
    public function list(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('viewDashboard', $tenant);

        $branch = $this->getCurrentBranch($request, $tenant);

        if (! $branch) {
            return response()->json([
                'message' => 'No active branch found.',
            ], 404);
        }

        $query = Appointment::where('branch_id', $branch->id)
            ->with(['customerProfile', 'staff', 'services.service']);

        // Date filter
        if ($request->filled('date_from')) {
            $query->whereDate('starts_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('starts_at', '<=', $request->date_to);
        }

        // Staff filter
        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->staff_id);
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search by customer name/phone
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('customerProfile', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $appointments = $query->latest('starts_at')
            ->paginate($request->query('per_page', 20))
            ->withQueryString();

        return response()->json([
            'appointments' => $appointments->items(),
            'pagination' => [
                'current_page' => $appointments->currentPage(),
                'last_page' => $appointments->lastPage(),
                'per_page' => $appointments->perPage(),
                'total' => $appointments->total(),
            ],
            'filters' => [
                'staff' => Staff::where('tenant_id', $tenant->id)
                    ->where('is_active', true)
                    ->whereHas('branches', function ($q) use ($branch) {
                        $q->where('branch_id', $branch->id);
                    })
                    ->get(['id', 'name']),
                'statuses' => array_column(AppointmentStatus::cases(), 'value'),
            ],
        ]);
    }

    /**
     * Get appointment details for modal.
     */
    public function show(Tenant $tenant, Appointment $appointment): JsonResponse
    {
        $this->authorize('viewDashboard', $tenant);

        if ($appointment->branch_id !== $tenant->id) {
            abort(404);
        }

        $appointment->load(['customerProfile', 'staff', 'services.service', 'branch', 'events.user']);

        return response()->json([
            'appointment' => [
                'id' => $appointment->id,
                'customer' => [
                    'id' => $appointment->customerProfile->id,
                    'name' => $appointment->customerProfile->getFullNameAttribute(),
                    'phone' => $appointment->customerProfile->phone,
                    'email' => $appointment->customerProfile->email,
                ],
                'staff' => $appointment->staff ? [
                    'id' => $appointment->staff->id,
                    'name' => $appointment->staff->name,
                ] : null,
                'branch' => [
                    'id' => $appointment->branch->id,
                    'name' => $appointment->branch->name,
                ],
                'services' => $appointment->services->map(function ($s) {
                    return [
                        'name' => $s->service->name,
                        'price' => $s->price_inr,
                        'duration' => $s->duration_minutes,
                        'buffer' => $s->buffer_minutes,
                    ];
                }),
                'starts_at' => $appointment->starts_at->toIso8601String(),
                'ends_at' => $appointment->ends_at->toIso8601String(),
                'status' => $appointment->status->value,
                'payment_status' => $appointment->payment_status->value,
                'total_price' => $appointment->total_price_inr,
                'total_duration' => $appointment->total_duration_minutes,
                'notes' => $appointment->notes,
                'events' => $appointment->events->map(function ($e) {
                    return [
                        'event_type' => $e->event_type,
                        'from_status' => $e->from_status,
                        'to_status' => $e->to_status,
                        'description' => $e->description,
                        'created_at' => $e->created_at->toIso8601String(),
                        'user' => $e->user ? [
                            'id' => $e->user->id,
                            'name' => $e->user->name,
                        ] : null,
                    ];
                }),
            ],
        ]);
    }

    /**
     * Update appointment status (complete, no-show, cancel).
     */
    public function updateStatus(Request $request, Tenant $tenant, Appointment $appointment): JsonResponse
    {
        $this->authorize('viewDashboard', $tenant);

        if ($appointment->branch_id !== $tenant->id) {
            abort(404);
        }

        $request->validate([
            'status' => ['required', 'string', 'in:confirmed,completed,cancelled,no_show'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $bookingService = app(BookingService::class);
        $userId = $request->user()->id;

        try {
            switch ($request->status) {
                case 'completed':
                    $appointment = $bookingService->completeAppointment($appointment, $userId);
                    break;
                case 'cancelled':
                    $appointment = $bookingService->cancelAppointment($appointment, $userId, $request->reason ?? 'Cancelled by staff');
                    break;
                case 'no_show':
                    $appointment = $bookingService->markNoShow($appointment, $userId);
                    break;
                case 'confirmed':
                    if (! $appointment->canTransitionTo(AppointmentStatus::CONFIRMED)) {
                        throw new \Exception('Appointment cannot be confirmed in its current state.', 422);
                    }
                    $appointment->update(['status' => AppointmentStatus::CONFIRMED]);
                    $appointment->recordEvent('confirmed', null, AppointmentStatus::CONFIRMED->value, null, null, 'Appointment confirmed by staff', null, $userId);
                    break;
            }

            return response()->json([
                'message' => 'Appointment status updated successfully.',
                'appointment' => $appointment->fresh()->load('events'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 422);
        }
    }

    /**
     * Get current branch from session or default.
     */
    protected function getCurrentBranch(Request $request, Tenant $tenant): ?Branch
    {
        $branchId = $request->session()->get("tenant_{$tenant->id}_branch_id");

        if ($branchId) {
            return $tenant->branches()->where('id', $branchId)->where('is_active', true)->first();
        }

        return $tenant->primaryBranch;
    }

    /**
     * Get color for appointment status.
     */
    protected function getStatusColor(AppointmentStatus $status): string
    {
        return match ($status) {
            AppointmentStatus::PENDING => '#f59e0b',     // amber
            AppointmentStatus::CONFIRMED => '#3b82f6',   // blue
            AppointmentStatus::COMPLETED => '#22c55e',   // green
            AppointmentStatus::CANCELLED => '#ef4444',   // red
            AppointmentStatus::NO_SHOW => '#6b7280',     // gray
        };
    }
}
