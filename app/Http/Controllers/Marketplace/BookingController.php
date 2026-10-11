<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\CustomerProfile;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function __construct(
        protected AvailabilityService $availabilityService,
        protected BookingService $bookingService
    ) {}

    /**
     * Show booking page for a specific salon/branch.
     */
    public function show(Tenant $tenant, Request $request)
    {
        if (! $tenant->isActive()) {
            abort(404);
        }

        $branchId = $request->query('branch_id');
        $branch = $branchId
            ? $tenant->branches()->where('id', $branchId)->where('is_active', true)->first()
            : $tenant->primaryBranch;

        if (! $branch) {
            abort(404);
        }

        // Get selected services from session or query
        $serviceIds = $request->query('services', []);
        if (empty($serviceIds) && $request->session()->has('booking_services')) {
            $serviceIds = $request->session()->get('booking_services');
        }

        $services = Service::whereIn('id', $serviceIds)
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->get();

        if ($services->isEmpty()) {
            return redirect()->route('marketplace.salon.show', $tenant->slug)
                ->with('error', 'Please select at least one service.');
        }

        // Calculate totals
        $totalPrice = $services->sum(fn ($s) => $s->getPriceForBranch($branch));
        $totalDuration = $services->sum(fn ($s) => $s->getDurationForBranch($branch))
            + $services->sum(fn ($s) => $s->getBufferForBranch($branch));

        // Get available staff for these services at this branch
        $staff = Staff::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereHas('branches', function ($q) use ($branch) {
                $q->where('branch_id', $branch->id);
            })
            ->whereHas('services', function ($q) use ($serviceIds) {
                $q->whereIn('services.id', $serviceIds);
            })
            ->with('services')
            ->get();

        // Get next 14 days for date selection
        $availableDates = $this->getAvailableDates($branch, 14);

        return view('marketplace.booking.show', compact(
            'tenant',
            'branch',
            'services',
            'staff',
            'totalPrice',
            'totalDuration',
            'availableDates'
        ));
    }

    /**
     * Store selected services in session and redirect to booking page.
     */
    public function selectServices(Tenant $tenant, Request $request)
    {
        $request->validate([
            'service_ids' => 'required|array|min:1',
            'service_ids.*' => 'exists:services,id',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        // Verify services belong to tenant
        $services = Service::whereIn('id', $request->service_ids)
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->get();

        if ($services->count() !== count($request->service_ids)) {
            return back()->with('error', 'Invalid service selection.');
        }

        $request->session()->put('booking_services', $request->service_ids);
        $request->session()->put('booking_branch_id', $request->branch_id);

        return redirect()->route('marketplace.booking.show', [
            'tenant' => $tenant->slug,
            'branch_id' => $request->branch_id,
        ]);
    }

    /**
     * Handle booking submission (guest or authenticated).
     */
    public function store(Tenant $tenant, Request $request)
    {
        // Rate limiting for booking attempts
        $key = 'booking:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages([
                'booking' => 'Too many booking attempts. Please try again later.',
            ]);
        }

        $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'staff_id' => 'required|exists:staff,id',
            'service_ids' => 'required|array|min:1',
            'service_ids.*' => 'exists:services,id',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'session_token' => 'required|string',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'required|string|max:20',
            'notes' => 'nullable|string|max:1000',
            'create_account' => 'sometimes|boolean',
            'password' => 'nullable|required_if:create_account,true|min:8|confirmed',
        ]);

        $branch = Branch::findOrFail($request->branch_id);
        $staff = Staff::findOrFail($request->staff_id);
        $startTime = Carbon::parse($request->starts_at);
        $endTime = Carbon::parse($request->ends_at);
        $serviceIds = $request->service_ids;

        // Verify branch belongs to tenant
        if ($branch->tenant_id !== $tenant->id) {
            abort(404);
        }

        // Find or create customer profile
        $customerProfile = $this->findOrCreateCustomerProfile($tenant, $request, Auth::user());

        // If creating account, register user
        $user = Auth::user();
        if (! $user && $request->boolean('create_account')) {
            $user = $this->createCustomerAccount($request);
            Auth::login($user);
        }

        // Link customer profile to user if authenticated
        if ($user && $customerProfile->user_id !== $user->id) {
            $customerProfile->update(['user_id' => $user->id]);
        }

        try {
            $result = $this->bookingService->createBooking([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'customer_profile_id' => $customerProfile->id,
                'staff_id' => $staff->id,
                'service_ids' => $serviceIds,
                'starts_at' => $startTime,
                'ends_at' => $endTime,
                'session_token' => $request->session_token,
                'notes' => $request->notes,
                'metadata' => [
                    'booked_by_user_id' => $user?->id,
                    'is_guest' => ! $user,
                    'source' => 'marketplace',
                ],
            ]);

            RateLimiter::clear($key);

            $appointment = $result['appointment'];

            // Clear booking session
            $request->session()->forget(['booking_services', 'booking_branch_id']);

            // Return success with management token
            return response()->json([
                'success' => true,
                'appointment_id' => $appointment->id,
                'management_token' => $appointment->management_token ?? $this->generateManagementToken($appointment),
                'redirect_url' => route('marketplace.booking.confirmation', [
                    'tenant' => $tenant->slug,
                    'appointment' => $appointment->id,
                ]),
            ]);

        } catch (\Exception $e) {
            RateLimiter::hit($key, 300); // 5 min decay

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 422);
        }
    }

    /**
     * Find existing customer profile or create new one.
     */
    protected function findOrCreateCustomerProfile(Tenant $tenant, Request $request, ?User $user): CustomerProfile
    {
        // Try to find by email first
        $profile = CustomerProfile::where('tenant_id', $tenant->id)
            ->where('email', $request->customer_email)
            ->first();

        if ($profile) {
            // Update contact info if changed
            $profile->update([
                'first_name' => $request->customer_name,
                'phone' => $request->customer_phone,
                'user_id' => $user?->id ?? $profile->user_id,
            ]);

            return $profile;
        }

        // Try to find by phone
        $profile = CustomerProfile::where('tenant_id', $tenant->id)
            ->where('phone', $request->customer_phone)
            ->first();

        if ($profile) {
            $profile->update([
                'first_name' => $request->customer_name,
                'email' => $request->customer_email,
                'user_id' => $user?->id ?? $profile->user_id,
            ]);

            return $profile;
        }

        // Create new guest profile
        return CustomerProfile::create([
            'tenant_id' => $tenant->id,
            'user_id' => $user?->id,
            'first_name' => $request->customer_name,
            'email' => $request->customer_email,
            'phone' => $request->customer_phone,
        ]);
    }

    /**
     * Create customer account from booking.
     */
    protected function createCustomerAccount(Request $request): User
    {
        return User::create([
            'name' => $request->customer_name,
            'email' => strtolower($request->customer_email),
            'password' => bcrypt($request->password),
            'phone' => $request->customer_phone,
            'email_verified_at' => null, // Require verification
        ]);
    }

    /**
     * Generate secure management token for appointment.
     */
    protected function generateManagementToken(Appointment $appointment): string
    {
        $token = hash('sha256', Str::random(64).$appointment->id.microtime(true));
        $appointment->update(['management_token' => $token]);

        return $token;
    }

    /**
     * Get available dates for date picker.
     */
    protected function getAvailableDates(Branch $branch, int $days = 14): array
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
