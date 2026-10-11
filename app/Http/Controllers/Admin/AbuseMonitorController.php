<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AbuseMonitorController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAdminDashboard');

        $now = Carbon::now();
        $last24Hours = $now->copy()->subHours(24);
        $last7Days = $now->copy()->subDays(7);

        $rateLimitHits = $this->getRateLimitStats($last24Hours);
        $suspiciousPayments = $this->getSuspiciousPayments($last24Hours);
        $failedLogins = $this->getFailedLogins($last24Hours);
        $webhookFailures = $this->getWebhookFailures($last24Hours);
        $highVolumeTenants = $this->getHighVolumeTenants($last7Days);
        $cancellationPatterns = $this->getCancellationPatterns($last7Days);

        return response()->json([
            'period' => [
                'last_24_hours' => [
                    'from' => $last24Hours->toIso8601String(),
                    'to' => Carbon::now()->toIso8601String(),
                ],
                'last_7_days' => [
                    'from' => $last7Days->toIso8601String(),
                    'to' => Carbon::now()->toIso8601String(),
                ],
            ],
            'rate_limits' => $rateLimitHits,
            'suspicious_payments' => $suspiciousPayments,
            'failed_logins' => $failedLogins,
            'webhook_failures' => $webhookFailures,
            'high_volume_tenants' => $highVolumeTenants,
            'cancellation_patterns' => $cancellationPatterns,
        ]);
    }

    protected function getRateLimitStats(Carbon $since): array
    {
        return [
            'total_hits' => 0,
            'blocked_ips' => 0,
            'top_endpoints' => [],
            'by_ip' => [],
        ];
    }

    protected function getSuspiciousPayments(Carbon $since): array
    {
        $suspicious = Payment::where('created_at', '>=', $since)
            ->where(function ($q) {
                $q->where('status', 'failed')
                    ->orWhere('status', 'refunded');
            })
            ->with('tenant:id,name,slug', 'branch:id,name')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'tenant' => $p->tenant ? [
                    'id' => $p->tenant->id,
                    'name' => $p->tenant->name,
                    'slug' => $p->tenant->slug,
                ] : null,
                'branch' => $p->branch ? [
                    'id' => $p->branch->id,
                    'name' => $p->branch->name,
                ] : null,
                'amount_inr' => $p->amount_inr,
                'status' => $p->status->value,
                'failure_reason' => $p->failure_reason,
                'created_at' => $p->created_at->toIso8601String(),
            ]);

        $byTenant = $suspicious->groupBy('tenant.id')
            ->map(fn ($group, $tenantId) => [
                'tenant_id' => $tenantId,
                'count' => $group->count(),
                'total_amount' => $group->sum('amount_inr'),
                'events' => $group->values(),
            ])
            ->filter(fn ($group) => $group['count'] > 3)
            ->sortByDesc('count')
            ->values();

        return [
            'recent' => $suspicious->take(20),
            'by_tenant' => $byTenant,
        ];
    }

    protected function getFailedLogins(Carbon $since): array
    {
        return [
            'total_attempts' => 0,
            'unique_ips' => 0,
            'locked_accounts' => 0,
            'top_ips' => [],
        ];
    }

    protected function getWebhookFailures(Carbon $since): array
    {
        $failures = WebhookEvent::where('status', 'failed')
            ->where('created_at', '>=', $since)
            ->select('gateway', 'event_type', DB::raw('count(*) as count'), 'error_message')
            ->groupBy('gateway', 'event_type', 'error_message')
            ->orderByDesc('count')
            ->limit(20)
            ->get()
            ->map(fn ($e) => [
                'gateway' => $e->gateway,
                'event_type' => $e->event_type,
                'count' => $e->count,
                'error' => $e->error_message,
            ]);

        $retrying = WebhookEvent::where('status', 'pending')
            ->where('retry_count', '>=', 2)
            ->where('created_at', '>=', $since)
            ->count();

        return [
            'failures' => $failures,
            'retrying_count' => $retrying,
        ];
    }

    protected function getHighVolumeTenants(Carbon $since): array
    {
        $tenants = Tenant::withCount([
            'appointments as appointments_count' => fn ($q) => $q->whereBetween('starts_at', [$since, now()]),
            'payments as payments_count' => fn ($q) => $q->whereBetween('paid_at', [$since, now()]),
        ])
            ->having('appointments_count', '>', 100)
            ->orHaving('payments_count', '>', 50)
            ->get(['id', 'name', 'slug', 'appointments_count', 'payments_count'])
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'appointments' => $t->appointments_count,
                'payments' => $t->payments_count,
            ])
            ->sortByDesc('appointments')
            ->values();

        return $tenants->take(20);
    }

    protected function getCancellationPatterns(Carbon $since): array
    {
        $cancellations = Appointment::whereBetween('created_at', [$since, now()])
            ->whereIn('status', ['cancelled', 'no_show'])
            ->with('tenant:id,name,slug', 'branch:id,name', 'staff:id,name')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'tenant' => $a->tenant ? ['id' => $a->tenant->id, 'name' => $a->tenant->name, 'slug' => $a->tenant->slug] : null,
                'branch' => $a->branch ? ['id' => $a->branch->id, 'name' => $a->branch->name] : null,
                'staff' => $a->staff ? ['id' => $a->staff->id, 'name' => $a->staff->name] : null,
                'status' => $a->status->value,
                'starts_at' => $a->starts_at->toIso8601String(),
                'created_at' => $a->created_at->toIso8601String(),
            ]);

        $byStaff = $cancellations->filter(fn ($a) => $a['staff'])
            ->groupBy('staff.id')
            ->map(fn ($group, $staffId) => [
                'staff_id' => $staffId,
                'staff_name' => $group->first()['staff']['name'],
                'count' => $group->count(),
                'cancelled' => $group->where('status', 'cancelled')->count(),
                'no_show' => $group->where('status', 'no_show')->count(),
            ])
            ->filter(fn ($group) => $group['count'] > 5)
            ->sortByDesc('count')
            ->values();

        $byBranch = $cancellations->filter(fn ($a) => $a['branch'])
            ->groupBy('branch.id')
            ->map(fn ($group, $branchId) => [
                'branch_id' => $branchId,
                'branch_name' => $group->first()['branch']['name'],
                'count' => $group->count(),
                'cancelled' => $group->where('status', 'cancelled')->count(),
                'no_show' => $group->where('status', 'no_show')->count(),
            ])
            ->filter(fn ($group) => $group['count'] > 10)
            ->sortByDesc('count')
            ->values();

        return [
            'recent' => $cancellations->take(20),
            'by_staff' => $byStaff,
            'by_branch' => $byBranch,
        ];
    }

    public function rateLimitConfig(): JsonResponse
    {
        $this->authorize('viewAdminDashboard');

        return response()->json([
            'endpoints' => [
                'booking' => [
                    'limit' => 10,
                    'window_minutes' => 60,
                    'description' => 'Booking attempts per hour per IP',
                ],
                'login' => [
                    'limit' => 5,
                    'window_minutes' => 15,
                    'description' => 'Login attempts per 15 minutes per IP',
                ],
                'otp' => [
                    'limit' => 3,
                    'window_minutes' => 10,
                    'description' => 'OTP requests per 10 minutes per phone/email',
                ],
                'webhook' => [
                    'limit' => 1000,
                    'window_minutes' => 60,
                    'description' => 'Webhook calls per hour per gateway',
                ],
            ],
            'block_duration_minutes' => 60,
            'alert_threshold' => 50,
        ]);
    }
}
