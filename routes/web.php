<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Marketplace\BookingController;
use App\Http\Controllers\Marketplace\BookingManagementController;
use App\Http\Controllers\Marketplace\SalonDiscoveryController;
use App\Http\Controllers\Tenant\BranchController;
use App\Http\Controllers\Tenant\CustomerProfileController;
use App\Http\Controllers\Tenant\ServiceController;
use App\Http\Controllers\Tenant\StaffController;
use App\Http\Controllers\Tenant\TenantDashboardController;
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
    Route::get('/', [TenantDashboardController::class, 'show'])->name('tenant.dashboard');
    Route::put('/', [TenantDashboardController::class, 'update'])->name('tenant.update');

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
});
