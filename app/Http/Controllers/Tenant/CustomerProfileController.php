<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\CustomerProfile;
use App\Models\Tenant;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerProfileController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('view', $tenant);

        $profiles = $tenant->customerProfiles()
            ->with('user:id,name,email,phone')
            ->latest()
            ->paginate(20);

        return response()->json($profiles);
    }

    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('view', $tenant);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $profile = $tenant->customerProfiles()->create($validated);

        return response()->json([
            'message' => 'Customer profile created successfully.',
            'profile' => $profile,
        ], Response::HTTP_CREATED);
    }

    public function show(Tenant $tenant, CustomerProfile $profile): JsonResponse
    {
        // Enforce that profile actually belongs to this tenant!
        if ($profile->tenant_id !== $tenant->id) {
            abort(Response::HTTP_NOT_FOUND, 'Customer profile not found for this salon.');
        }

        $this->authorize('view', $profile);

        return response()->json([
            'profile' => $profile->load('user:id,name,email,phone'),
        ]);
    }

    public function update(Request $request, Tenant $tenant, CustomerProfile $profile): JsonResponse
    {
        if ($profile->tenant_id !== $tenant->id) {
            abort(Response::HTTP_NOT_FOUND, 'Customer profile not found for this salon.');
        }

        $this->authorize('update', $profile);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_blocked' => ['nullable', 'boolean'],
        ]);

        $profile->update($validated);

        return response()->json([
            'message' => 'Customer profile updated successfully.',
            'profile' => $profile->fresh(),
        ]);
    }

    public function destroy(Tenant $tenant, CustomerProfile $profile): JsonResponse
    {
        if ($profile->tenant_id !== $tenant->id) {
            abort(Response::HTTP_NOT_FOUND, 'Customer profile not found for this salon.');
        }

        $this->authorize('delete', $profile);

        $profile->delete();

        return response()->json([
            'message' => 'Customer profile deleted successfully.',
        ]);
    }
}
