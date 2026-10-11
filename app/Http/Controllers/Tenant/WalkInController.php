<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalkInController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected BookingService $bookingService
    ) {}

    /**
     * Show walk-in creation form data.
     */
    public function create(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('viewDashboard', $tenant);

        $branch = $this->getCurrentBranch($request, $tenant);

        if (! $branch) {
            return response()->json([
                'message' => 'No active branch found.',
            ], 404);
        }

        // Get available staff for walk-in
        $staff = Staff::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereHas('branches', function ($q) use ($branch) {
                $q->where('branch_id', $branch->id);
            })
            ->whereHas('shifts', function ($q) use ($branch) {
                $dayOfWeek = (int) Carbon::today()->format('w');
                $q->where('branch_id', $branch->id)
                    ->where('day_of_week', $dayOfWeek)
                    ->where('is_working', true);
            })
            ->whereDoesntHave('timeOff', function ($q) {
                $q->where('starts_at', '<=', Carbon::today()->endOfDay())
                    ->where('ends_at', '>=', Carbon::today()->startOfDay());
            })
            ->with(['services'])
            ->get();

        // Get all services for this branch
        $services = Service::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereHas('branchOverrides', function ($q) use ($branch) {
                $q->where('branch_id', $branch->id)
                    ->where('is_available', true);
            })
            ->orWhereDoesntHave('branchOverrides', function ($q) use ($branch) {
                $q->where('branch_id', $branch->id);
            })
            ->with(['branchOverrides' => function ($q) use ($branch) {
                $q->where('branch_id', $branch->id);
            }])
            ->get()
            ->map(function ($service) use ($branch) {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'duration' => $service->getDurationForBranch($branch),
                    'buffer' => $service->getBufferForBranch($branch),
                    'price' => $service->getPriceForBranch($branch),
                ];
            });

        return response()->json([
            'branch' => $branch,
            'staff' => $staff->map(function ($s) {
                return [
                    'id' => $s->id,
                    'name' => $s->name,
                    'services' => $s->services->pluck('id')->toArray(),
                ];
            }),
            'services' => $services,
        ]);
    }

    /**
     * Create a walk-in appointment.
     */
    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('viewDashboard', $tenant);

        $branch = $this->getCurrentBranch($request, $tenant);

        if (! $branch) {
            return response()->json([
                'message' => 'No active branch found.',
            ], 404);
        }

        $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'service_ids' => 'required|array|min:1',
            'service_ids.*' => 'exists:services,id',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'starts_at' => 'nullable|date|after_or_equal:now',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Verify staff belongs to branch
        $staff = Staff::where('id', $request->staff_id)
            ->where('tenant_id', $tenant->id)
            ->whereHas('branches', function ($q) use ($branch) {
                $q->where('branch_id', $branch->id);
            })
            ->first();

        if (! $staff) {
            return response()->json([
                'message' => 'Staff member not found or not assigned to this branch.',
            ], 422);
        }

        // Verify services are available at this branch
        $services = Service::whereIn('id', $request->service_ids)
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->get();

        if ($services->count() !== count($request->service_ids)) {
            return response()->json([
                'message' => 'One or more services not found or inactive.',
            ], 422);
        }

        foreach ($services as $service) {
            if (! $service->isAvailableAtBranch($branch)) {
                return response()->json([
                    'message' => "Service {$service->name} not available at this branch.",
                ], 422);
            }
        }

        // Find or create customer profile
        $customerProfile = $this->findOrCreateCustomerProfile($tenant, $request);

        // Set start time to now if not provided
        $startTime = $request->filled('starts_at')
            ? Carbon::parse($request->starts_at)
            : Carbon::now();

        // Calculate total duration
        $totalDuration = $services->sum(fn ($s) => $s->getDurationForBranch($branch))
            + $services->sum(fn ($s) => $s->getBufferForBranch($branch));

        $endTime = $startTime->copy()->addMinutes($totalDuration);

        // Check availability
        $bookingService = $this->bookingService;

        // Use lock to prevent race conditions
        try {
            $result = $bookingService->createWalkIn([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'customer_profile_id' => $customerProfile->id,
                'staff_id' => $staff->id,
                'service_ids' => $request->service_ids,
                'starts_at' => $startTime,
                'ends_at' => $endTime,
                'notes' => $request->notes,
                'user_id' => $request->user()->id,
            ]);

            $appointment = $result['appointment'];

            return response()->json([
                'message' => 'Walk-in appointment created and completed.',
                'appointment' => $appointment->load(['customerProfile', 'staff', 'services.service']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 409);
        }
    }

    /**
     * Quick complete a walk-in (for immediate service completion).
     */
    public function quickComplete(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('viewDashboard', $tenant);

        $branch = $this->getCurrentBranch($request, $tenant);

        if (! $branch) {
            return response()->json([
                'message' => 'No active branch found.',
            ], 404);
        }

        $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'service_ids' => 'required|array|min:1',
            'service_ids.*' => 'exists:services,id',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Verify staff
        $staff = Staff::where('id', $request->staff_id)
            ->where('tenant_id', $tenant->id)
            ->whereHas('branches', function ($q) use ($branch) {
                $q->where('branch_id', $branch->id);
            })
            ->first();

        if (! $staff) {
            return response()->json([
                'message' => 'Staff member not found or not assigned to this branch.',
            ], 422);
        }

        // Verify services
        $services = Service::whereIn('id', $request->service_ids)
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->get();

        if ($services->count() !== count($request->service_ids)) {
            return response()->json([
                'message' => 'One or more services not found or inactive.',
            ], 422);
        }

        foreach ($services as $service) {
            if (! $service->isAvailableAtBranch($branch)) {
                return response()->json([
                    'message' => "Service {$service->name} not available at this branch.",
                ], 422);
            }
        }

        $customerProfile = $this->findOrCreateCustomerProfile($tenant, $request);

        $startTime = Carbon::now();
        $totalDuration = $services->sum(fn ($s) => $s->getDurationForBranch($branch))
            + $services->sum(fn ($s) => $s->getBufferForBranch($branch));
        $endTime = $startTime->copy()->addMinutes($totalDuration);

        try {
            $result = $this->bookingService->createWalkIn([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'customer_profile_id' => $customerProfile->id,
                'staff_id' => $staff->id,
                'service_ids' => $request->service_ids,
                'starts_at' => $startTime,
                'ends_at' => $endTime,
                'notes' => $request->notes,
                'user_id' => $request->user()->id,
            ]);

            $appointment = $result['appointment'];

            return response()->json([
                'message' => 'Walk-in completed successfully.',
                'appointment' => $appointment->load(['customerProfile', 'staff', 'services.service']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 409);
        }
    }

    /**
     * Find or create customer profile.
     */
    protected function findOrCreateCustomerProfile(Tenant $tenant, Request $request): CustomerProfile
    {
        // Try to find by phone first
        $profile = CustomerProfile::where('tenant_id', $tenant->id)
            ->where('phone', $request->customer_phone)
            ->first();

        if ($profile) {
            $profile->update([
                'first_name' => $request->customer_name,
                'email' => $request->customer_email ?? $profile->email,
            ]);

            return $profile;
        }

        // Try by email
        if ($request->filled('customer_email')) {
            $profile = CustomerProfile::where('tenant_id', $tenant->id)
                ->where('email', $request->customer_email)
                ->first();

            if ($profile) {
                $profile->update([
                    'first_name' => $request->customer_name,
                    'phone' => $request->customer_phone,
                ]);

                return $profile;
            }
        }

        // Create new
        return CustomerProfile::create([
            'tenant_id' => $tenant->id,
            'first_name' => $request->customer_name,
            'phone' => $request->customer_phone,
            'email' => $request->customer_email,
        ]);
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
}
