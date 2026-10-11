<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditLogController extends Controller
{
    use AuthorizesRequests;

    /**
     * List audit logs with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAdminDashboard');

        $validated = $request->validate([
            'event_type' => 'nullable|string',
            'tenant_id' => 'nullable|exists:tenants,id',
            'user_id' => 'nullable|exists:users,id',
            'appointment_id' => 'nullable|exists:appointments,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = AppointmentEvent::with(['appointment.tenant', 'user'])
            ->orderByDesc('created_at');

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->event_type);
        }

        if ($request->filled('tenant_id')) {
            $query->whereHas('appointment', fn ($q) => $q->where('tenant_id', $request->tenant_id));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('appointment_id')) {
            $query->where('appointment_id', $request->appointment_id);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        $events = $query->paginate($request->get('per_page', 50))->withQueryString();

        $events->getCollection()->transform(function ($event) {
            return [
                'id' => $event->id,
                'appointment_id' => $event->appointment_id,
                'event_type' => $event->event_type,
                'from_status' => $event->from_status,
                'to_status' => $event->to_status,
                'from_payment_status' => $event->from_payment_status,
                'to_payment_status' => $event->to_payment_status,
                'description' => $event->description,
                'metadata' => $event->metadata,
                'created_at' => $event->created_at->toIso8601String(),
                'user' => $event->user ? [
                    'id' => $event->user->id,
                    'name' => $event->user->name,
                    'email' => $event->user->email,
                ] : null,
                'appointment' => $event->appointment ? [
                    'id' => $event->appointment->id,
                    'tenant' => $event->appointment->tenant ? [
                        'id' => $event->appointment->tenant->id,
                        'name' => $event->appointment->tenant->name,
                        'slug' => $event->appointment->tenant->slug,
                    ] : null,
                ] : null,
            ];
        });

        return response()->json([
            'data' => $events->items(),
            'pagination' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
            ],
        ]);
    }

    /**
     * Get audit log statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $this->authorize('viewAdminDashboard');

        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $dateFrom = $validated['date_from'] ?? Carbon::now()->subDays(30)->startOfDay();
        $dateTo = $validated['date_to'] ?? Carbon::now()->endOfDay();

        $totalEvents = AppointmentEvent::whereBetween('created_at', [$dateFrom, $dateTo])->count();

        $eventsByType = AppointmentEvent::whereBetween('created_at', [$dateFrom, $dateTo])
            ->select('event_type', DB::raw('count(*) as count'))
            ->groupBy('event_type')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($e) => ['event_type' => $e->event_type, 'count' => $e->count]);

        $eventsByDay = AppointmentEvent::whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('DATE(created_at) as date, count(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($e) => ['date' => $e->date, 'count' => $e->count]);

        $topUsers = AppointmentEvent::whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereNotNull('user_id')
            ->select('user_id', DB::raw('count(*) as count'))
            ->groupBy('user_id')
            ->orderByDesc('count')
            ->limit(10)
            ->with('user:id,name,email')
            ->get()
            ->map(fn ($e) => [
                'user' => $e->user ? [
                    'id' => $e->user->id,
                    'name' => $e->user->name,
                    'email' => $e->user->email,
                ] : null,
                'count' => $e->count,
            ]);

        $topTenants = AppointmentEvent::whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereHas('appointment.tenant')
            ->selectRaw('tenants.id, tenants.name, count(*) as count')
            ->join('appointments', 'appointment_events.appointment_id', '=', 'appointments.id')
            ->join('tenants', 'appointments.tenant_id', '=', 'tenants.id')
            ->groupBy('tenants.id', 'tenants.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($e) => ['tenant_name' => $e->name, 'count' => $e->count]);

        return response()->json([
            'period' => [
                'from' => $dateFrom->toDateString(),
                'to' => $dateTo->toDateString(),
            ],
            'total_events' => $totalEvents,
            'by_type' => $eventsByType,
            'by_day' => $eventsByDay,
            'top_users' => $topUsers,
            'top_tenants' => $topTenants,
        ]);
    }

    /**
     * Get audit trail for a specific appointment.
     */
    public function appointment(Appointment $appointment): JsonResponse
    {
        $this->authorize('viewAdminDashboard');

        $events = $appointment->events()
            ->with('user:id,name,email')
            ->orderBy('created_at')
            ->get()
            ->map(function ($event) {
                return [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'from_status' => $event->from_status,
                    'to_status' => $event->to_status,
                    'from_payment_status' => $event->from_payment_status,
                    'to_payment_status' => $event->to_payment_status,
                    'description' => $event->description,
                    'metadata' => $event->metadata,
                    'created_at' => $event->created_at->toIso8601String(),
                    'user' => $event->user ? [
                        'id' => $event->user->id,
                        'name' => $event->user->name,
                        'email' => $event->user->email,
                    ] : null,
                ];
            });

        return response()->json([
            'appointment' => [
                'id' => $appointment->id,
                'tenant' => $appointment->tenant ? [
                    'id' => $appointment->tenant->id,
                    'name' => $appointment->tenant->name,
                    'slug' => $appointment->tenant->slug,
                ] : null,
                'customer' => $appointment->customerProfile ? [
                    'id' => $appointment->customerProfile->id,
                    'name' => $appointment->customerProfile->first_name.' '.$appointment->customerProfile->last_name,
                ] : null,
                'staff' => $appointment->staff ? [
                    'id' => $appointment->staff->id,
                    'name' => $appointment->staff->name,
                ] : null,
            ],
            'events' => $events,
        ]);
    }

    /**
     * Export audit logs as CSV.
     */
    public function export(Request $request): JsonResponse
    {
        $this->authorize('viewAdminDashboard');

        // In production, queue a job to generate CSV
        return response()->json([
            'message' => 'Export queued. Download link will be sent via email.',
        ]);
    }
}
