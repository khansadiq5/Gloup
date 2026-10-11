<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\CustomerProfile;
use App\Models\Staff;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use AuthorizesRequests;

    /**
     * Show the main salon dashboard with branch switcher and stats.
     */
    public function show(Request $request, Tenant $tenant): View|JsonResponse
    {
        $this->authorize('viewDashboard', $tenant);

        // Get current branch from session or default to primary
        $branchId = $request->session()->get("tenant_{$tenant->id}_branch_id");
        $branch = $branchId
            ? $tenant->branches()->where('id', $branchId)->where('is_active', true)->first()
            : $tenant->primaryBranch;

        // If no branch in session, store primary
        if (! $branchId && $branch) {
            $request->session()->put("tenant_{$tenant->id}_branch_id", $branch->id);
        }

        // Get all active branches for switcher
        $branches = $tenant->activeBranches()->orderBy('is_primary', 'desc')->get();

        // Calculate stats for the selected branch
        $stats = $this->getBranchStats($branch);

        // Get today's appointments
        $todayAppointments = $this->getTodayAppointments($branch);

        // Get upcoming appointments (next 7 days)
        $upcomingAppointments = $this->getUpcomingAppointments($branch);

        // Get staff on duty today
        $staffOnDuty = $this->getStaffOnDuty($branch);

        if ($request->wantsJson()) {
            return response()->json([
                'tenant' => $tenant->only(['id', 'uuid', 'name', 'slug', 'email', 'phone', 'status']),
                'current_branch' => $branch,
                'branches' => $branches,
                'stats' => $stats,
                'today_appointments' => $todayAppointments,
                'upcoming_appointments' => $upcomingAppointments,
                'staff_on_duty' => $staffOnDuty,
            ]);
        }

        return view('tenant.dashboard', [
            'tenant' => $tenant,
            'membership' => $request->user()->getTenantMembership($tenant),
            'currentBranch' => $branch,
            'branches' => $branches,
            'stats' => $stats,
            'todayAppointments' => $todayAppointments,
            'upcomingAppointments' => $upcomingAppointments,
            'staffOnDuty' => $staffOnDuty,
        ]);
    }

    /**
     * Switch the current branch context.
     */
    public function switchBranch(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('viewDashboard', $tenant);

        $request->validate([
            'branch_id' => 'required|exists:branches,id',
        ]);

        $branch = $tenant->branches()->where('id', $request->branch_id)->where('is_active', true)->first();

        if (! $branch) {
            return response()->json([
                'message' => 'Branch not found or inactive.',
            ], 404);
        }

        // Check if user has access to this branch
        $user = $request->user();
        if (! $user->isPlatformAdmin() && ! $user->hasTenantRole($tenant, ['owner', 'admin'])) {
            $staff = Staff::where('user_id', $user->id)->where('tenant_id', $tenant->id)->first();
            if (! $staff || ! $staff->isAssignedToBranch($branch)) {
                return response()->json([
                    'message' => 'You do not have access to this branch.',
                ], 403);
            }
        }

        $request->session()->put("tenant_{$tenant->id}_branch_id", $branch->id);

        return response()->json([
            'message' => 'Branch switched successfully.',
            'branch' => $branch,
        ]);
    }

    /**
     * Get stats for a branch.
     */
    protected function getBranchStats(?Branch $branch): array
    {
        if (! $branch) {
            return [
                'today_appointments' => 0,
                'pending_appointments' => 0,
                'total_customers' => 0,
                'active_staff' => 0,
                'today_revenue' => 0,
            ];
        }

        $today = Carbon::today();
        $tomorrow = $today->copy()->addDay();

        return [
            'today_appointments' => Appointment::where('branch_id', $branch->id)
                ->whereDate('starts_at', $today)
                ->whereIn('status', [AppointmentStatus::PENDING->value, AppointmentStatus::CONFIRMED->value])
                ->count(),

            'pending_appointments' => Appointment::where('branch_id', $branch->id)
                ->where('status', AppointmentStatus::PENDING->value)
                ->count(),

            'total_customers' => CustomerProfile::where('tenant_id', $branch->tenant_id)
                ->where('is_blocked', false)
                ->count(),

            'active_staff' => Staff::where('tenant_id', $branch->tenant_id)
                ->where('is_active', true)
                ->whereHas('branches', function ($q) use ($branch) {
                    $q->where('branch_id', $branch->id);
                })
                ->count(),

            'today_revenue' => Appointment::where('branch_id', $branch->id)
                ->whereDate('starts_at', $today)
                ->where('status', AppointmentStatus::COMPLETED->value)
                ->sum('total_price_inr'),
        ];
    }

    /**
     * Get today's appointments for a branch.
     */
    protected function getTodayAppointments(?Branch $branch): array
    {
        if (! $branch) {
            return [];
        }

        $today = Carbon::today();

        return Appointment::where('branch_id', $branch->id)
            ->whereDate('starts_at', $today)
            ->whereIn('status', [AppointmentStatus::PENDING->value, AppointmentStatus::CONFIRMED->value, AppointmentStatus::COMPLETED->value])
            ->with(['customerProfile', 'staff', 'services.service'])
            ->orderBy('starts_at')
            ->get()
            ->map(function ($appt) {
                return [
                    'id' => $appt->id,
                    'time' => $appt->starts_at->format('H:i'),
                    'end_time' => $appt->ends_at->format('H:i'),
                    'customer' => $appt->customerProfile->getFullNameAttribute(),
                    'customer_phone' => $appt->customerProfile->phone,
                    'staff' => $appt->staff?->name,
                    'services' => $appt->services->pluck('service.name')->implode(', '),
                    'status' => $appt->status->value,
                    'payment_status' => $appt->payment_status->value,
                    'total_price' => $appt->total_price_inr,
                ];
            })->toArray();
    }

    /**
     * Get upcoming appointments (next 7 days) for a branch.
     */
    protected function getUpcomingAppointments(?Branch $branch): array
    {
        if (! $branch) {
            return [];
        }

        $today = Carbon::today();
        $nextWeek = $today->copy()->addDays(7);

        return Appointment::where('branch_id', $branch->id)
            ->whereDate('starts_at', '>', $today)
            ->whereDate('starts_at', '<=', $nextWeek)
            ->whereIn('status', [AppointmentStatus::PENDING->value, AppointmentStatus::CONFIRMED->value])
            ->with(['customerProfile', 'staff', 'services.service'])
            ->orderBy('starts_at')
            ->limit(20)
            ->get()
            ->map(function ($appt) {
                return [
                    'id' => $appt->id,
                    'date' => $appt->starts_at->format('Y-m-d'),
                    'time' => $appt->starts_at->format('H:i'),
                    'customer' => $appt->customerProfile->getFullNameAttribute(),
                    'staff' => $appt->staff?->name,
                    'services' => $appt->services->pluck('service.name')->implode(', '),
                    'status' => $appt->status->value,
                ];
            })->toArray();
    }

    /**
     * Get staff on duty today for a branch.
     */
    protected function getStaffOnDuty(?Branch $branch): array
    {
        if (! $branch) {
            return [];
        }

        $dayOfWeek = (int) Carbon::today()->format('w');

        return Staff::where('tenant_id', $branch->tenant_id)
            ->where('is_active', true)
            ->whereHas('branches', function ($q) use ($branch) {
                $q->where('branch_id', $branch->id);
            })
            ->whereHas('shifts', function ($q) use ($branch, $dayOfWeek) {
                $q->where('branch_id', $branch->id)
                    ->where('day_of_week', $dayOfWeek)
                    ->where('is_working', true);
            })
            ->whereDoesntHave('timeOff', function ($q) {
                $q->where('starts_at', '<=', Carbon::today()->endOfDay())
                    ->where('ends_at', '>=', Carbon::today()->startOfDay());
            })
            ->with(['shifts' => function ($q) use ($branch, $dayOfWeek) {
                $q->where('branch_id', $branch->id)
                    ->where('day_of_week', $dayOfWeek);
            }])
            ->get()
            ->map(function ($staff) {
                $shift = $staff->shifts->first();

                return [
                    'id' => $staff->id,
                    'name' => $staff->name,
                    'shift_start' => $shift?->start_time?->format('H:i'),
                    'shift_end' => $shift?->end_time?->format('H:i'),
                ];
            })->toArray();
    }

    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('update', $tenant);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $tenant->update($validated);

        return response()->json([
            'message' => 'Salon details updated successfully.',
            'tenant' => $tenant->fresh(),
        ]);
    }
}
