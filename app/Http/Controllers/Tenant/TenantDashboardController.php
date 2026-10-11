<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantDashboardController extends Controller
{
    use AuthorizesRequests;

    public function show(Request $request, Tenant $tenant): View|JsonResponse
    {
        $this->authorize('viewDashboard', $tenant);

        if ($request->wantsJson()) {
            return response()->json([
                'tenant' => $tenant->only(['id', 'uuid', 'name', 'slug', 'email', 'phone', 'status']),
                'current_user_role' => $request->user()->getTenantMembership($tenant)?->role->value,
            ]);
        }

        return view('tenant.dashboard', [
            'tenant' => $tenant,
            'membership' => $request->user()->getTenantMembership($tenant),
        ]);
    }

    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('update', $tenant);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $tenant->update($validated);

        return response()->json([
            'message' => 'Salon details updated successfully.',
            'tenant' => $tenant->fresh(),
        ]);
    }
}
