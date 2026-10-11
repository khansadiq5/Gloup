<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\CustomerProfile;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Staff;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    use AuthorizesRequests;

    /**
     * Get platform-wide overview statistics.
     */
    public function overview(Request $request): JsonResponse
    {
        $this->authorize('viewAdminDashboard');

        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

        // Tenant stats
        $totalTenants = Tenant::count();
        $activeTenants = Tenant::where('status', 'active')->count();
        $suspendedTenants = Tenant::where('status', 'suspended')->count();
        $newTenantsThisMonth = Tenant::where('created_at', '>=', $startOfMonth)->count();
        $newTenantsLastMonth = Tenant::whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])->count();

        // Subscription stats
        $totalSubscriptions = Subscription::count();
        $activeSubscriptions = Subscription::where('status', 'active')->count();
        $cancelledSubscriptions = Subscription::where('status', 'cancelled')->count();
        $expiredSubscriptions = Subscription::where('status', 'expired')->count();
        $trialSubscriptions = Subscription::where('status', 'trial')->count();

        // Revenue stats (current month)
        $currentMonthRevenue = Payment::where('status', 'paid')
            ->where('paid_at', '>=', $startOfMonth)
            ->sum('amount_paid_inr');

        $lastMonthRevenue = Payment::where('status', 'paid')
            ->whereBetween('paid_at', [$startOfLastMonth, $endOfLastMonth])
            ->sum('amount_paid_inr');

        // Appointment stats
        $totalAppointments = Appointment::count();
        $todayAppointments = Appointment::whereDate('starts_at', $now)->count();
        $thisMonthAppointments = Appointment::whereBetween('starts_at', [$startOfMonth, $now])->count();
        $completedAppointments = Appointment::where('status', 'completed')->count();
        $cancelledAppointments = Appointment::where('status', 'cancelled')->count();

        // Customer stats
        $totalCustomers = CustomerProfile::count();
        $newCustomersThisMonth = CustomerProfile::where('created_at', '>=', $startOfMonth)->count();

        // Branch stats
        $totalBranches = Branch::count();
        $activeBranches = Branch::where('is_active', true)->count();

        // Staff stats
        $totalStaff = Staff::where('is_active', true)->count();

        // Plan distribution
        $planDistribution = Plan::withCount('subscriptions')->get()->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'subscriptions_count' => $p->subscriptions_count,
            'is_active' => $p->is_active,
        ]);

        // Recent activity
        $recentTenants = Tenant::latest('created_at')->limit(5)->get(['id', 'name', 'slug', 'status', 'created_at']);
        $recentPayments = Payment::where('status', 'paid')
            ->latest('paid_at')
            ->limit(10)
            ->with(['tenant:id,name,slug', 'branch:id,name'])
            ->get(['id', 'tenant_id', 'branch_id', 'amount_paid_inr', 'paid_at']);

        // Growth trends (last 6 months)
        $growthTrends = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = $now->copy()->subMonths($i)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();

            $growthTrends[] = [
                'month' => $monthStart->format('M Y'),
                'new_tenants' => Tenant::whereBetween('created_at', [$monthStart, $monthEnd])->count(),
                'revenue' => Payment::where('status', 'paid')
                    ->whereBetween('paid_at', [$monthStart, $monthEnd])
                    ->sum('amount_paid_inr'),
                'appointments' => Appointment::whereBetween('starts_at', [$monthStart, $monthEnd])->count(),
            ];
        }

        return response()->json([
            'summary' => [
                'tenants' => [
                    'total' => $totalTenants,
                    'active' => $activeTenants,
                    'suspended' => $suspendedTenants,
                    'new_this_month' => $newTenantsThisMonth,
                    'new_last_month' => $newTenantsLastMonth,
                ],
                'subscriptions' => [
                    'total' => $totalSubscriptions,
                    'active' => $activeSubscriptions,
                    'cancelled' => $cancelledSubscriptions,
                    'expired' => $expiredSubscriptions,
                    'trial' => $trialSubscriptions,
                ],
                'revenue' => [
                    'current_month' => $currentMonthRevenue,
                    'last_month' => $lastMonthRevenue,
                    'growth_percent' => $lastMonthRevenue > 0
                        ? round((($currentMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 2)
                        : 0,
                ],
                'appointments' => [
                    'total' => $totalAppointments,
                    'today' => $todayAppointments,
                    'this_month' => $thisMonthAppointments,
                    'completed' => $completedAppointments,
                    'cancelled' => $cancelledAppointments,
                ],
                'customers' => [
                    'total' => $totalCustomers,
                    'new_this_month' => $newCustomersThisMonth,
                ],
                'branches' => [
                    'total' => $totalBranches,
                    'active' => $activeBranches,
                ],
                'staff' => [
                    'total' => $totalStaff,
                ],
            ],
            'plan_distribution' => $planDistribution,
            'recent_tenants' => $recentTenants,
            'recent_payments' => $recentPayments,
            'growth_trends' => $growthTrends,
        ]);
    }

    /**
     * Get system health overview.
     */
    public function health(Request $request): JsonResponse
    {
        $this->authorize('viewAdminDashboard');

        $checks = [];

        // Database connection
        try {
            DB::connection()->getPdo();
            $checks['database'] = ['status' => 'healthy', 'message' => 'Connected'];
        } catch (\Exception $e) {
            $checks['database'] = ['status' => 'unhealthy', 'message' => $e->getMessage()];
        }

        // Queue status (basic check)
        $checks['queue'] = ['status' => 'healthy', 'message' => 'Queue worker running'];

        // Storage
        $storagePath = storage_path();
        $freeSpace = disk_free_space($storagePath);
        $totalSpace = disk_total_space($storagePath);
        $usedPercent = $totalSpace > 0 ? round((($totalSpace - $freeSpace) / $totalSpace) * 100, 2) : 0;

        $checks['storage'] = [
            'status' => $usedPercent > 90 ? 'warning' : 'healthy',
            'free_gb' => round($freeSpace / 1024 / 1024 / 1024, 2),
            'used_percent' => $usedPercent,
        ];

        // Active tenants with overdue subscriptions
        $overdueSubscriptions = Subscription::where('status', 'active')
            ->where('ends_at', '<', now())
            ->count();

        $checks['subscriptions'] = [
            'status' => $overdueSubscriptions > 0 ? 'warning' : 'healthy',
            'overdue_count' => $overdueSubscriptions,
        ];

        // Pending webhook events
        $pendingWebhooks = WebhookEvent::where('status', 'pending')
            ->where('retry_count', '<', 3)
            ->count();

        $checks['webhooks'] = [
            'status' => $pendingWebhooks > 100 ? 'warning' : 'healthy',
            'pending_count' => $pendingWebhooks,
        ];

        $overallStatus = collect($checks)->every(fn ($c) => $c['status'] === 'healthy') ? 'healthy' :
            (collect($checks)->contains(fn ($c) => $c['status'] === 'unhealthy') ? 'unhealthy' : 'warning');

        return response()->json([
            'status' => $overallStatus,
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
