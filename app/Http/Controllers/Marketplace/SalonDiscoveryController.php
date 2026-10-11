<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\Tenant;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SalonDiscoveryController extends Controller
{
    /**
     * Show marketplace homepage with searchable salon listing.
     */
    public function index(Request $request)
    {
        $query = Tenant::where('status', 'active')
            ->whereHas('branches', function ($q) {
                $q->where('is_active', true);
            });

        // Search by name/location
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('state', 'like', "%{$search}%")
                    ->orWhereHas('branches', function ($bq) use ($search) {
                        $bq->where('name', 'like', "%{$search}%")
                            ->orWhere('city', 'like', "%{$search}%")
                            ->orWhere('address_line1', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by service category
        if ($request->filled('category')) {
            $query->whereHas('services', function ($q) use ($request) {
                $q->where('category_id', $request->category)
                    ->where('is_active', true);
            });
        }

        // Filter by city
        if ($request->filled('city')) {
            $query->whereHas('branches', function ($q) use ($request) {
                $q->where('city', $request->city)->where('is_active', true);
            });
        }

        $salons = $query->with(['primaryBranch', 'serviceCategories.services'])
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        // Get filter options
        $categories = ServiceCategory::whereHas('tenant', function ($q) {
            $q->where('status', 'active');
        })->with('tenant')->get();

        $cities = Branch::where('is_active', true)
            ->whereHas('tenant', function ($q) {
                $q->where('status', 'active');
            })
            ->distinct()
            ->pluck('city')
            ->filter()
            ->sort()
            ->values();

        return view('marketplace.index', compact('salons', 'categories', 'cities'));
    }

    /**
     * Show public salon page with branches, services, staff.
     */
    public function show(Tenant $tenant)
    {
        if (! $tenant->isActive()) {
            abort(404);
        }

        $tenant->load([
            'activeBranches.serviceOverrides.service.category',
            'activeBranches.staff.services',
            'activeBranches.branchHours',
            'serviceCategories.services',
        ]);

        // Get primary branch for default display
        $primaryBranch = $tenant->primaryBranch;
        $branches = $tenant->activeBranches;

        // Get available services grouped by category
        $servicesByCategory = $tenant->serviceCategories()
            ->with(['services' => function ($q) {
                $q->where('is_active', true)->orderBy('name');
            }])
            ->get();

        // Get staff for primary branch
        $staff = $primaryBranch?->staff()
            ->where('is_active', true)
            ->with('services')
            ->get() ?? collect();

        // Get next 7 days for date picker
        $availableDates = $this->getAvailableDates($branches);

        return view('marketplace.salon.show', compact(
            'tenant',
            'primaryBranch',
            'branches',
            'servicesByCategory',
            'staff',
            'availableDates'
        ));
    }

    /**
     * Get available dates for the next 30 days based on branch hours.
     */
    protected function getAvailableDates($branches): array
    {
        $dates = [];
        $today = Carbon::today();

        for ($i = 0; $i < 30; $i++) {
            $date = $today->copy()->addDays($i);
            $dayOfWeek = (int) $date->format('w');

            $hasHours = $branches->contains(function ($branch) use ($dayOfWeek) {
                return $branch->branchHours->contains(function ($hours) use ($dayOfWeek) {
                    return $hours->day_of_week === $dayOfWeek && ! $hours->is_closed;
                });
            });

            if ($hasHours) {
                $dates[] = [
                    'date' => $date->format('Y-m-d'),
                    'label' => $date->format('D, M j'),
                    'day_name' => $date->format('l'),
                ];
            }
        }

        return $dates;
    }

    /**
     * API: Get available time slots for a branch/staff/date/services.
     */
    public function getAvailableSlots(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'staff_id' => 'required|exists:staff,id',
            'date' => 'required|date|after_or_equal:today',
            'service_ids' => 'required|array|min:1',
            'service_ids.*' => 'exists:services,id',
        ]);

        $branch = Branch::findOrFail($request->branch_id);
        $staff = Staff::findOrFail($request->staff_id);
        $date = Carbon::parse($request->date);
        $serviceIds = $request->service_ids;

        // Verify staff belongs to branch
        if (! $staff->isAssignedToBranch($branch)) {
            return response()->json(['slots' => []]);
        }

        // Verify services belong to tenant and are available at branch
        $services = Service::whereIn('id', $serviceIds)
            ->where('tenant_id', $branch->tenant_id)
            ->where('is_active', true)
            ->get();

        if ($services->count() !== count($serviceIds)) {
            return response()->json(['slots' => []]);
        }

        foreach ($services as $service) {
            if (! $service->isAvailableAtBranch($branch)) {
                return response()->json(['slots' => []]);
            }
        }

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

        return response()->json([
            'slots' => $availableSlots,
        ]);
    }

    /**
     * API: Create a temporary reservation hold.
     */
    public function createReservation(Request $request)
    {
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

        // Verify availability one more time
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
}
