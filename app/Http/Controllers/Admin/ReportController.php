<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    use AuthorizesRequests;

    /**
     * Get revenue report with filters.
     */
    public function revenue(Request $request): JsonResponse
    {
        $this->authorize('viewAdminDashboard');

        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'tenant_id' => 'nullable|exists:tenants,id',
            'branch_id' => 'nullable|exists:branches,id',
            'group_by' => 'nullable|in:day,week,month',
        ]);

        $dateFrom = $validated['date_from'] ?? Carbon::now()->subMonths(6)->startOfMonth();
        $dateTo = $validated['date_to'] ?? Carbon::now()->endOfMonth();
        $groupBy = $validated['group_by'] ?? 'month';

        $query = Payment::where('status', PaymentStatus::PAID)
            ->whereBetween('paid_at', [$dateFrom, $dateTo]);

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $groupFormat = match ($groupBy) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            default => '%Y-%m',
        };

        $revenue = $query->selectRaw("DATE_FORMAT(paid_at, '{$groupFormat}') as period, SUM(amount_paid_inr) as revenue, COUNT(*) as transactions")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($r) => [
                'period' => $r->period,
                'revenue' => $r->revenue,
                'transactions' => $r->transactions,
            ]);

        $totalRevenue = $revenue->sum('revenue');
        $totalTransactions = $revenue->sum('transactions');

        return response()->json([
            'summary' => [
                'total_revenue' => $revenue->sum('revenue'),
                'total_transactions' => $revenue->sum('transactions'),
                'average_transaction' => $totalTransactions > 0 ? round($totalRevenue / $totalTransactions, 2) : 0,
            ],
            'data' => $revenue,
            'filters' => [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'group_by' => $groupBy,
            ],
        ]);
    }

    /**
     * Get appointment report with filters.
     */
    public function appointments(Request $request): JsonResponse
    {
        $this->authorize('viewAdminDashboard');

        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'tenant_id' => 'nullable|exists:tenants,id',
            'branch_id' => 'nullable|exists:branches,id',
            'status' => 'nullable|in:pending,confirmed,completed,cancelled,no_show',
            'group_by' => 'nullable|in:day,week,month',
        ]);

        $dateFrom = $validated['date_from'] ?? Carbon::now()->subMonths(3)->startOfMonth();
        $dateTo = $validated['date_to'] ?? Carbon::now()->endOfMonth();
        $groupBy = $validated['group_by'] ?? 'month';

        $query = Appointment::whereBetween('starts_at', [$dateFrom, $dateTo]);

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $groupFormat = match ($groupBy) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            default => '%Y-%m',
        };

        $appointments = $query->selectRaw("DATE_FORMAT(starts_at, '{$groupFormat}') as period,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END) as no_show,
            SUM(CASE WHEN status IN ('pending','confirmed') THEN 1 ELSE 0 END) as upcoming")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn ($r) => [
                'period' => $r->period,
                'total' => $r->total,
                'completed' => $r->completed,
                'cancelled' => $r->cancelled,
                'no_show' => $r->no_show,
                'upcoming' => $r->upcoming,
                'completion_rate' => $r->total > 0 ? round(($r->completed / $r->total) * 100, 2) : 0,
            ]);

        $totals = [
            'total' => $appointments->sum('total'),
            'completed' => $appointments->sum('completed'),
            'cancelled' => $appointments->sum('cancelled'),
            'no_show' => $appointments->sum('no_show'),
        ];

        return response()->json([
            'summary' => $totals + [
                'completion_rate' => $totals['total'] > 0 ? round(($totals['completed'] / $totals['total']) * 100, 2) : 0,
                'cancellation_rate' => $totals['total'] > 0 ? round(($totals['cancelled'] / $totals['total']) * 100, 2) : 0,
                'no_show_rate' => $totals['total'] > 0 ? round(($totals['no_show'] / $totals['total']) * 100, 2) : 0,
            ],
            'data' => $appointments,
        ]);
    }

    /**
     * Get tenant performance report.
     */
    public function tenantPerformance(Request $request): JsonResponse
    {
        $this->authorize('viewAdminDashboard');

        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $dateFrom = $validated['date_from'] ?? Carbon::now()->subMonths(3)->startOfMonth();
        $dateTo = $validated['date_to'] ?? Carbon::now()->endOfMonth();
        $limit = $validated['limit'] ?? 20;

        $tenants = Tenant::with('activeSubscription.plan')
            ->whereHas('activeSubscription', fn ($q) => $q->where('status', 'active'))
            ->limit($limit)
            ->get();

        $performance = $tenants->map(function ($tenant) use ($dateFrom, $dateTo) {
            $appointments = Appointment::where('tenant_id', $tenant->id)
                ->whereBetween('starts_at', [$dateFrom, $dateTo])
                ->get();

            $payments = Payment::where('tenant_id', $tenant->id)
                ->where('status', PaymentStatus::PAID)
                ->whereBetween('paid_at', [$dateFrom, $dateTo])
                ->get();

            $completedAppointments = $appointments->where('status', AppointmentStatus::COMPLETED)->count();
            $totalAppointments = $appointments->count();
            $totalRevenue = $payments->sum('amount_paid_inr');
            $avgTicket = $payments->count() > 0 ? $payments->avg('amount_paid_inr') : 0;

            $branchPerformance = $tenant->branches->map(function ($branch) use ($dateFrom, $dateTo) {
                $bAppointments = Appointment::where('branch_id', $branch->id)
                    ->whereBetween('starts_at', [$dateFrom, $dateTo])
                    ->get();

                $bPayments = Payment::where('branch_id', $branch->id)
                    ->where('status', PaymentStatus::PAID)
                    ->whereBetween('paid_at', [$dateFrom, $dateTo])
                    ->get();

                return [
                    'branch_id' => $branch->id,
                    'branch_name' => $branch->name,
                    'city' => $branch->city,
                    'appointments' => $bAppointments->count(),
                    'completed' => $bAppointments->where('status', AppointmentStatus::COMPLETED)->count(),
                    'revenue' => $bPayments->sum('amount_paid_inr'),
                ];
            });

            return [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'tenant_slug' => $tenant->slug,
                'plan' => $tenant->activeSubscription?->plan?->name ?? 'Free',
                'total_appointments' => $totalAppointments,
                'completed_appointments' => $completedAppointments,
                'completion_rate' => $totalAppointments > 0 ? round(($completedAppointments / $totalAppointments) * 100, 2) : 0,
                'total_revenue' => $totalRevenue,
                'average_ticket' => round($avgTicket, 2),
                'branches' => $branchPerformance,
            ];
        });

        return response()->json([
            'period' => [
                'from' => $dateFrom->toDateString(),
                'to' => $dateTo->toDateString(),
            ],
            'tenants' => $performance,
        ]);
    }

    /**
     * Get service utilization report.
     */
    public function serviceUtilization(Request $request): JsonResponse
    {
        $this->authorize('viewAdminDashboard');

        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'tenant_id' => 'nullable|exists:tenants,id',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $dateFrom = $validated['date_from'] ?? Carbon::now()->subMonths(3)->startOfMonth();
        $dateTo = $validated['date_to'] ?? Carbon::now()->endOfMonth();

        $query = Appointment::whereIn('status', [AppointmentStatus::COMPLETED->value, AppointmentStatus::CONFIRMED->value])
            ->whereBetween('starts_at', [$dateFrom, $dateTo])
            ->with('services.service');

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $appointments = $query->get();

        $serviceStats = [];

        foreach ($appointments as $appointment) {
            foreach ($appointment->services as $apptService) {
                $serviceId = $apptService->service_id;
                $serviceName = $apptService->service->name ?? 'Unknown';

                if (! isset($serviceStats[$serviceId])) {
                    $serviceStats[$serviceId] = [
                        'service_id' => $serviceId,
                        'service_name' => $serviceName,
                        'count' => 0,
                        'total_revenue' => 0,
                        'total_duration' => 0,
                    ];
                }

                $serviceStats[$serviceId]['count']++;
                $serviceStats[$serviceId]['total_revenue'] += $apptService->price_inr;
                $serviceStats[$serviceId]['total_duration'] += $apptService->duration_minutes + $apptService->buffer_minutes;
            }
        }

        // Sort by count descending
        $sorted = collect($serviceStats)->sortByDesc('count')->values();

        return response()->json([
            'period' => [
                'from' => $dateFrom->toDateString(),
                'to' => $dateTo->toDateString(),
            ],
            'services' => $sorted,
        ]);
    }

    /**
     * Get staff performance report.
     */
    public function staffPerformance(Request $request): JsonResponse
    {
        $this->authorize('viewAdminDashboard');

        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'tenant_id' => 'nullable|exists:tenants,id',
            'branch_id' => 'nullable|exists:branches,id',
        ]);

        $dateFrom = $validated['date_from'] ?? Carbon::now()->subMonths(3)->startOfMonth();
        $dateTo = $validated['date_to'] ?? Carbon::now()->endOfMonth();

        $query = Appointment::whereIn('status', [AppointmentStatus::COMPLETED->value, AppointmentStatus::CONFIRMED->value])
            ->whereBetween('starts_at', [$dateFrom, $dateTo])
            ->with('staff');

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->tenant_id);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $appointments = $query->get();

        $staffStats = [];

        foreach ($appointments as $appointment) {
            if (! $appointment->staff) {
                continue;
            }

            $staffId = $appointment->staff_id;
            $staffName = $appointment->staff->name;

            if (! isset($staffStats[$staffId])) {
                $staffStats[$staffId] = [
                    'staff_id' => $staffId,
                    'staff_name' => $staffName,
                    'appointments' => 0,
                    'completed' => 0,
                    'cancelled' => 0,
                    'no_show' => 0,
                    'total_revenue' => 0,
                    'total_duration' => 0,
                ];
            }

            $staffStats[$staffId]['appointments']++;

            if ($appointment->status === AppointmentStatus::COMPLETED) {
                $staffStats[$staffId]['completed']++;
            } elseif ($appointment->status === AppointmentStatus::CANCELLED) {
                $staffStats[$staffId]['cancelled']++;
            } elseif ($appointment->status === AppointmentStatus::NO_SHOW) {
                $staffStats[$staffId]['no_show']++;
            }

            $staffStats[$staffId]['total_revenue'] += $appointment->total_price_inr;
            $staffStats[$staffId]['total_duration'] += $appointment->total_duration_minutes;
        }

        // Calculate rates
        $sorted = collect($staffStats)->map(function ($stats) {
            $stats['completion_rate'] = $stats['appointments'] > 0
                ? round(($stats['completed'] / $stats['appointments']) * 100, 2)
                : 0;
            $stats['cancellation_rate'] = $stats['appointments'] > 0
                ? round(($stats['cancelled'] / $stats['appointments']) * 100, 2)
                : 0;
            $stats['no_show_rate'] = $stats['appointments'] > 0
                ? round(($stats['no_show'] / $stats['appointments']) * 100, 2)
                : 0;
            $stats['avg_ticket'] = $stats['completed'] > 0
                ? round($stats['total_revenue'] / $stats['completed'], 2)
                : 0;

            return $stats;
        })->sortByDesc('appointments')->values();

        return response()->json([
            'period' => [
                'from' => $dateFrom->toDateString(),
                'to' => $dateTo->toDateString(),
            ],
            'staff' => $sorted,
        ]);
    }

    /**
     * Export report as CSV.
     */
    public function export(Request $request): JsonResponse
    {
        $this->authorize('viewAdminDashboard');

        $type = $request->get('type', 'revenue');
        $dateFrom = $request->get('date_from', Carbon::now()->subMonth()->startOfMonth()->toDateString());
        $dateTo = $request->get('date_to', Carbon::now()->endOfMonth()->toDateString());

        // In a real implementation, this would queue a job to generate CSV
        // and return a download link. For now, return the data.

        return response()->json([
            'message' => 'Export queued. Download link will be sent via email.',
            'type' => $type,
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
        ]);
    }
}
