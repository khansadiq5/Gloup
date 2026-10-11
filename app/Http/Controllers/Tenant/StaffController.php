<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Staff;
use App\Models\StaffShift;
use App\Models\Tenant;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StaffController extends Controller
{
    use AuthorizesRequests;

    public function index(Tenant $tenant): JsonResponse
    {
        $this->authorize('view', $tenant);

        $staff = $tenant->staff()
            ->with(['branches:id,name', 'services:id,name'])
            ->get();

        return response()->json([
            'staff' => $staff,
        ]);
    }

    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('create', [Staff::class, $tenant]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
        ]);

        // Cross-tenant security check: Ensure all branch_ids belong to this tenant
        if (! empty($validated['branch_ids'])) {
            $validBranchCount = $tenant->branches()->whereIn('id', $validated['branch_ids'])->count();
            if ($validBranchCount !== count($validated['branch_ids'])) {
                return response()->json([
                    'message' => 'Cannot assign staff to branches that do not belong to this salon.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        // Cross-tenant security check: Ensure all service_ids belong to this tenant
        if (! empty($validated['service_ids'])) {
            $validServiceCount = $tenant->services()->whereIn('id', $validated['service_ids'])->count();
            if ($validServiceCount !== count($validated['service_ids'])) {
                return response()->json([
                    'message' => 'Cannot assign staff to services that do not belong to this salon.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $staff = $tenant->staff()->create([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'bio' => $validated['bio'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (! empty($validated['branch_ids'])) {
            $staff->branches()->sync($validated['branch_ids']);
        }

        if (! empty($validated['service_ids'])) {
            $staff->services()->sync($validated['service_ids']);
        }

        return response()->json([
            'message' => 'Staff profile created successfully.',
            'staff' => $staff->load(['branches', 'services']),
        ], Response::HTTP_CREATED);
    }

    public function show(Tenant $tenant, Staff $staff): JsonResponse
    {
        if ($staff->tenant_id !== $tenant->id) {
            abort(Response::HTTP_NOT_FOUND, 'Staff profile not found for this salon.');
        }

        $this->authorize('view', $staff);

        return response()->json([
            'staff' => $staff->load(['branches', 'services', 'shifts.branch', 'timeOff']),
        ]);
    }

    public function update(Request $request, Tenant $tenant, Staff $staff): JsonResponse
    {
        if ($staff->tenant_id !== $tenant->id) {
            abort(Response::HTTP_NOT_FOUND, 'Staff profile not found for this salon.');
        }

        $this->authorize('update', $staff);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
        ]);

        if (isset($validated['branch_ids'])) {
            $validBranchCount = $tenant->branches()->whereIn('id', $validated['branch_ids'])->count();
            if ($validBranchCount !== count($validated['branch_ids'])) {
                return response()->json([
                    'message' => 'Cannot assign staff to branches that do not belong to this salon.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $staff->branches()->sync($validated['branch_ids']);
        }

        if (isset($validated['service_ids'])) {
            $validServiceCount = $tenant->services()->whereIn('id', $validated['service_ids'])->count();
            if ($validServiceCount !== count($validated['service_ids'])) {
                return response()->json([
                    'message' => 'Cannot assign staff to services that do not belong to this salon.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $staff->services()->sync($validated['service_ids']);
        }

        $staff->update([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? $staff->email,
            'phone' => $validated['phone'] ?? $staff->phone,
            'bio' => $validated['bio'] ?? $staff->bio,
            'is_active' => $validated['is_active'] ?? $staff->is_active,
        ]);

        return response()->json([
            'message' => 'Staff profile updated successfully.',
            'staff' => $staff->fresh(['branches', 'services']),
        ]);
    }

    public function destroy(Tenant $tenant, Staff $staff): JsonResponse
    {
        if ($staff->tenant_id !== $tenant->id) {
            abort(Response::HTTP_NOT_FOUND, 'Staff profile not found for this salon.');
        }

        $this->authorize('delete', $staff);

        $staff->delete();

        return response()->json([
            'message' => 'Staff profile deleted successfully.',
        ]);
    }

    public function setShifts(Request $request, Tenant $tenant, Branch $branch, Staff $staff): JsonResponse
    {
        if ($branch->tenant_id !== $tenant->id || $staff->tenant_id !== $tenant->id) {
            abort(Response::HTTP_NOT_FOUND, 'Resource not found for this salon.');
        }

        $this->authorize('update', $staff);

        $validated = $request->validate([
            'shifts' => ['required', 'array'],
            'shifts.*.day_of_week' => ['required', 'integer', 'min:0', 'max:6'],
            'shifts.*.start_time' => ['required', 'date_format:H:i'],
            'shifts.*.end_time' => ['required', 'date_format:H:i', 'after:shifts.*.start_time'],
            'shifts.*.is_working' => ['required', 'boolean'],
        ]);

        foreach ($validated['shifts'] as $shiftData) {
            StaffShift::updateOrCreate(
                [
                    'staff_id' => $staff->id,
                    'branch_id' => $branch->id,
                    'day_of_week' => $shiftData['day_of_week'],
                ],
                [
                    'start_time' => $shiftData['start_time'],
                    'end_time' => $shiftData['end_time'],
                    'is_working' => $shiftData['is_working'],
                ]
            );
        }

        return response()->json([
            'message' => 'Staff shifts configured successfully.',
            'shifts' => $staff->shifts()->where('branch_id', $branch->id)->get(),
        ]);
    }

    public function addTimeOff(Request $request, Tenant $tenant, Staff $staff): JsonResponse
    {
        if ($staff->tenant_id !== $tenant->id) {
            abort(Response::HTTP_NOT_FOUND, 'Staff profile not found for this salon.');
        }

        $this->authorize('update', $staff);

        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $timeOff = $staff->timeOff()->create($validated);

        return response()->json([
            'message' => 'Staff time off recorded successfully.',
            'time_off' => $timeOff,
        ], Response::HTTP_CREATED);
    }
}
