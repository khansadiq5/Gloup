<?php

use App\Http\Controllers\Admin\AbuseMonitorController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\HealthCheckController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\TenantManagementController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Marketplace\BookingController;
use App\Http\Controllers\Marketplace\BookingManagementController;
use App\Http\Controllers\Marketplace\SalonDiscoveryController;
use App\Http\Controllers\Payment\WebhookController;
use App\Http\Controllers\Tenant\BranchController;
use App\Http\Controllers\Tenant\CalendarController;
use App\Http\Controllers\Tenant\CustomerProfileController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\LoyaltyController;
use App\Http\Controllers\Tenant\ServiceController;
use App\Http\Controllers\Tenant\StaffController;
use App\Http\Controllers\Tenant\WalkInController;
use Illuminate\Support\Facades\Route;

// Public Marketplace / Landing routes
Route::get('/', function () {
    return view('welcome');
})->name('home');

// Public Salon Discovery
Route::prefix('salons')->name('marketplace.')->group(function () {
    Route::get('/', [SalonDiscoveryController::class, 'index'])->name('index');
    Route::get('/{tenant:slug}', [SalonDiscoveryController::class, 'show'])->name('salon.show');

    // API endpoints for AJAX
    Route::get('/{tenant:slug}/slots', [SalonDiscoveryController::class, 'getAvailableSlots'])->name('slots');
    Route::post('/{tenant:slug}/reserve', [SalonDiscoveryController::class, 'createReservation'])->name('reserve');
});

// Public Booking Flow
Route::prefix('book/{tenant:slug}')->name('marketplace.booking.')->group(function () {
    Route::get('/', [BookingController::class, 'show'])->name('show');
    Route::post('/select-services', [BookingController::class, 'selectServices'])->name('select-services');
    Route::post('/', [BookingController::class, 'store'])->name('store');
});

// Public Booking Management (Guest + Customer)
Route::prefix('booking/{tenant:slug}/{appointment}')->name('marketplace.booking.')->group(function () {
    Route::get('/confirmation', [BookingManagementController::class, 'confirmation'])->name('confirmation');
    Route::get('/verify', [BookingManagementController::class, 'verify'])->name('verify');
    Route::post('/verify', [BookingManagementController::class, 'verifyAccess'])->name('verify.access');
    Route::get('/manage', [BookingManagementController::class, 'manage'])->name('manage');
    Route::post('/cancel', [BookingManagementController::class, 'cancel'])->name('cancel');
    Route::post('/reschedule', [BookingManagementController::class, 'reschedule'])->name('reschedule');
    Route::get('/reschedule/slots', [BookingManagementController::class, 'getRescheduleSlots'])->name('reschedule.slots');
    Route::post('/reschedule/reserve', [BookingManagementController::class, 'createRescheduleReservation'])->name('reschedule.reserve');
});

// Guest & Auth routes
Route::middleware('guest')->group(function () {
    Route::get('/register/owner', [RegisterController::class, 'showOwnerRegisterForm'])->name('register.owner');
    Route::post('/register/owner', [RegisterController::class, 'registerOwner'])->name('register.owner.submit');

    Route::get('/register/customer', [RegisterController::class, 'showCustomerRegisterForm'])->name('register.customer');
    Route::post('/register/customer', [RegisterController::class, 'registerCustomer'])->name('register.customer.submit');

    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
});

Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// Authenticated Tenant Protected Routes
Route::middleware(['auth', 'tenant'])->prefix('app/{tenant}')->group(function () {
    // Dashboard with branch switcher
    Route::get('/', [DashboardController::class, 'show'])->name('tenant.dashboard');
    Route::put('/', [DashboardController::class, 'update'])->name('tenant.update');
    Route::post('/branch/switch', [DashboardController::class, 'switchBranch'])->name('tenant.branch.switch');

    // Calendar & Appointments
    Route::prefix('calendar')->name('tenant.calendar.')->group(function () {
        Route::get('/', [CalendarController::class, 'index'])->name('index');
        Route::get('/list', [CalendarController::class, 'list'])->name('list');
        Route::get('/appointments/{appointment}', [CalendarController::class, 'show'])->name('appointments.show');
        Route::put('/appointments/{appointment}/status', [CalendarController::class, 'updateStatus'])->name('appointments.status');
    });

    // Walk-ins
    Route::prefix('walkins')->name('tenant.walkins.')->group(function () {
        Route::get('/create', [WalkInController::class, 'create'])->name('create');
        Route::post('/', [WalkInController::class, 'store'])->name('store');
        Route::post('/quick-complete', [WalkInController::class, 'quickComplete'])->name('quick-complete');
    });

    // Tenant-isolated Customer Profiles (CRUD)
    Route::get('/customers', [CustomerProfileController::class, 'index'])->name('tenant.customers.index');
    Route::post('/customers', [CustomerProfileController::class, 'store'])->name('tenant.customers.store');
    Route::get('/customers/{profile}', [CustomerProfileController::class, 'show'])->name('tenant.customers.show');
    Route::put('/customers/{profile}', [CustomerProfileController::class, 'update'])->name('tenant.customers.update');
    Route::delete('/customers/{profile}', [CustomerProfileController::class, 'destroy'])->name('tenant.customers.destroy');

    // Tenant Branches (CRUD with Plan Entitlement Checks)
    Route::get('/branches', [BranchController::class, 'index'])->name('tenant.branches.index');
    Route::post('/branches', [BranchController::class, 'store'])->name('tenant.branches.store');
    Route::get('/branches/{branch}', [BranchController::class, 'show'])->name('tenant.branches.show');
    Route::put('/branches/{branch}', [BranchController::class, 'update'])->name('tenant.branches.update');
    Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->name('tenant.branches.destroy');

    // Tenant Services & Pricing Overrides
    Route::get('/services', [ServiceController::class, 'index'])->name('tenant.services.index');
    Route::post('/services', [ServiceController::class, 'store'])->name('tenant.services.store');
    Route::get('/services/{service}', [ServiceController::class, 'show'])->name('tenant.services.show');
    Route::put('/services/{service}', [ServiceController::class, 'update'])->name('tenant.services.update');
    Route::delete('/services/{service}', [ServiceController::class, 'destroy'])->name('tenant.services.destroy');
    Route::post('/branches/{branch}/services/{service}/override', [ServiceController::class, 'overrideForBranch'])->name('tenant.branches.services.override');

    // Tenant Staff Profiles, Shifts & Time Off
    Route::get('/staff', [StaffController::class, 'index'])->name('tenant.staff.index');
    Route::post('/staff', [StaffController::class, 'store'])->name('tenant.staff.store');
    Route::get('/staff/{staff}', [StaffController::class, 'show'])->name('tenant.staff.show');
    Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('tenant.staff.update');
    Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])->name('tenant.staff.destroy');
    Route::post('/branches/{branch}/staff/{staff}/shifts', [StaffController::class, 'setShifts'])->name('tenant.branches.staff.shifts');
    Route::post('/staff/{staff}/time-off', [StaffController::class, 'addTimeOff'])->name('tenant.staff.time_off');

    // Loyalty & Rewards
    Route::prefix('loyalty')->name('tenant.loyalty.')->group(function () {
        Route::get('/program', [LoyaltyController::class, 'getProgram'])->name('program');
        Route::put('/program', [LoyaltyController::class, 'upsertProgram'])->name('program.upsert');
        Route::get('/rewards', [LoyaltyController::class, 'getRewards'])->name('rewards');
        Route::post('/rewards', [LoyaltyController::class, 'createReward'])->name('rewards.create');
        Route::put('/rewards/{reward}', [LoyaltyController::class, 'updateReward'])->name('rewards.update');
        Route::delete('/rewards/{reward}', [LoyaltyController::class, 'deleteReward'])->name('rewards.delete');
        Route::get('/customer/{customerProfile}/progress', [LoyaltyController::class, 'getCustomerProgress'])->name('customer.progress');
        Route::get('/customer/{customerProfile}/redemptions', [LoyaltyController::class, 'getCustomerRedemptions'])->name('customer.redemptions');
        Route::post('/customer/{customerProfile}/redeem', [LoyaltyController::class, 'redeemReward'])->name('customer.redeem');
        Route::put('/redemptions/{redemption}/cancel', [LoyaltyController::class, 'cancelRedemption'])->name('redemptions.cancel');
        Route::put('/redemptions/{redemption}/refund', [LoyaltyController::class, 'refundRedemption'])->name('redemptions.refund');
    });
});

// Payment Webhooks (public, no auth)
Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::post('/razorpay', [WebhookController::class, 'razorpay'])->name('razorpay');
    Route::post('/cashfree', [WebhookController::class, 'cashfree'])->name('cashfree');
    Route::post('/stripe', [WebhookController::class, 'stripe'])->name('stripe');
    Route::post('/{gateway}', [WebhookController::class, 'generic'])->name('generic');
});

// Admin Routes (Platform Super Admin only)
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    // Health checks (no auth for liveness/readiness)
    Route::get('/health/live', [HealthCheckController::class, 'live'])->name('health.live');
    Route::get('/health/ready', [HealthCheckController::class, 'ready'])->name('health.ready');

    // Admin dashboard & health (auth required)
    Route::get('/health', [HealthCheckController::class, 'check'])->name('health.check');
    Route::get('/health/metrics', [HealthCheckController::class, 'metrics'])->name('health.metrics');

    Route::get('/dashboard', [AdminDashboardController::class, 'overview'])->name('dashboard.overview');
    Route::get('/dashboard/health', [AdminDashboardController::class, 'health'])->name('dashboard.health');

    // Tenant Management
    Route::prefix('tenants')->name('tenants.')->group(function () {
        Route::get('/', [TenantManagementController::class, 'index'])->name('index');
        Route::get('/{tenant}', [TenantManagementController::class, 'show'])->name('show');
        Route::put('/{tenant}/status', [TenantManagementController::class, 'updateStatus'])->name('status.update');
        Route::put('/{tenant}/plan', [TenantManagementController::class, 'changePlan'])->name('plan.change');
        Route::post('/{tenant}/cancel', [TenantManagementController::class, 'cancelSubscription'])->name('subscription.cancel');
        Route::post('/{tenant}/trial', [TenantManagementController::class, 'extendTrial'])->name('trial.extend');
        Route::get('/plans', [TenantManagementController::class, 'plans'])->name('plans');
    });

    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/revenue', [ReportController::class, 'revenue'])->name('revenue');
        Route::get('/appointments', [ReportController::class, 'appointments'])->name('appointments');
        Route::get('/tenant-performance', [ReportController::class, 'tenantPerformance'])->name('tenant-performance');
        Route::get('/service-utilization', [ReportController::class, 'serviceUtilization'])->name('service-utilization');
        Route::get('/staff-performance', [ReportController::class, 'staffPerformance'])->name('staff-performance');
        Route::post('/export', [ReportController::class, 'export'])->name('export');
    });

    // Audit Logs
    Route::prefix('audit')->name('audit.')->group(function () {
        Route::get('/', [AuditLogController::class, 'index'])->name('index');
        Route::get('/stats', [AuditLogController::class, 'stats'])->name('stats');
        Route::get('/appointment/{appointment}', [AuditLogController::class, 'appointment'])->name('appointment');
        Route::post('/export', [AuditLogController::class, 'export'])->name('export');
    });

    // Abuse Monitoring
    Route::prefix('abuse')->name('abuse.')->group(function () {
        Route::get('/', [AbuseMonitorController::class, 'index'])->name('index');
        Route::get('/rate-limits', [AbuseMonitorController::class, 'rateLimitConfig'])->name('rate-limits');
    });

    // Health Checks
    Route::prefix('health')->name('health.')->group(function () {
        Route::get('/live', [HealthCheckController::class, 'live'])->name('live');
        Route::get('/ready', [HealthCheckController::class, 'ready'])->name('ready');
        Route::get('/check', [HealthCheckController::class, 'check'])->name('check');
        Route::get('/metrics', [HealthCheckController::class, 'metrics'])->name('metrics');
    });
});
