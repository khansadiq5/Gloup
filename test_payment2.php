<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchHour;
use App\Models\CustomerProfile;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\StaffShift;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;

// Create test data
$tenant = Tenant::factory()->create(['slug' => 'test-salon']);
$owner = User::factory()->create();
TenantUser::factory()->create([
    'tenant_id' => $tenant->id,
    'user_id' => $owner->id,
    'role' => 'owner',
]);

$branch = Branch::factory()->primary()->create(['tenant_id' => $tenant->id, 'slug' => 'main']);
$category = ServiceCategory::factory()->create(['tenant_id' => $tenant->id]);
$service = Service::factory()->create([
    'tenant_id' => $tenant->id,
    'category_id' => $category->id,
    'duration_minutes' => 30,
    'buffer_minutes' => 5,
    'price_inr' => 1000,
]);
$staff = Staff::factory()->create(['tenant_id' => $tenant->id]);
$staff->branches()->attach($branch->id);
$staff->services()->attach($service->id);

$date = Carbon::tomorrow();
$dayOfWeek = (int) $date->format('w');
BranchHour::factory()->create([
    'branch_id' => $branch->id,
    'day_of_week' => $dayOfWeek,
    'open_time' => '09:00:00',
    'close_time' => '20:00:00',
    'is_closed' => false,
]);
StaffShift::factory()->create([
    'staff_id' => $staff->id,
    'branch_id' => $branch->id,
    'day_of_week' => $dayOfWeek,
    'start_time' => '09:00:00',
    'end_time' => '18:00:00',
    'is_working' => true,
]);

$customer = CustomerProfile::factory()->create([
    'tenant_id' => $tenant->id,
    'user_id' => $owner->id,
]);

// Create appointment
$start = $date->copy()->setTime(10, 0);
$end = $start->copy()->addMinutes(35);
$token = 'test-token';

$availabilityService = app(AvailabilityService::class);
$availabilityService->createReservation(
    $tenant->id, $branch->id, $staff->id, $start, $end, $token
);

$bookingService = app(BookingService::class);
$result = $bookingService->createBooking([
    'tenant_id' => $tenant->id,
    'branch_id' => $branch->id,
    'customer_profile_id' => $customer->id,
    'staff_id' => $staff->id,
    'service_ids' => [$service->id],
    'starts_at' => $start,
    'ends_at' => $end,
    'session_token' => $token,
]);

$appointment = $result['appointment'];

// Now create payment with paid factory
$payment = Payment::factory()->paid()->create([
    'tenant_id' => $tenant->id,
    'branch_id' => $branch->id,
    'appointment_id' => $appointment->id,
    'customer_profile_id' => $customer->id,
    'gateway' => 'razorpay',
    'method' => 'card',
    'amount_inr' => 1000,
    'amount_paid_inr' => 1000,
]);

echo 'Payment Status: '.$payment->status->value.PHP_EOL;
echo 'Amount Paid: '.$payment->amount_paid_inr.PHP_EOL;
echo 'Is Paid: '.($payment->isPaid() ? 'true' : 'false').PHP_EOL;
echo 'Status Enum: '.$payment->status->value.PHP_EOL;
