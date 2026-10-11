<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Tenant\BranchController;
use App\Http\Controllers\Tenant\CustomerProfileController;
use App\Http\Controllers\Tenant\TenantDashboardController;
use Illuminate\Support\Facades\Route;

// Public Marketplace / Landing routes
Route::get('/', function () {
    return view('welcome');
})->name('home');

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
});
