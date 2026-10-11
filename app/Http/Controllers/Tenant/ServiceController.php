<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchService;
use App\Models\Service;
use App\Models\Tenant;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ServiceController extends Controller
{
    use AuthorizesRequests;

    public function index(Tenant $tenant): JsonResponse
    {
        $this->authorize('view', $tenant);

        $services = $tenant->services()
            ->with('category:id,name,slug')
            ->get();

        return response()->json([
            'services' => $services,
            'categories' => $tenant->serviceCategories()->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request, Tenant $tenant): JsonResponse
    {
        $this->authorize('create', [Service::class, $tenant]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:100', 'alpha_dash'],
            'category_id' => ['nullable', 'integer', 'exists:service_categories,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'buffer_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'price_inr' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // If category is provided, verify it belongs to this tenant
        if (! empty($validated['category_id'])) {
            $categoryExists = $tenant->serviceCategories()->where('id', $validated['category_id'])->exists();
            if (! $categoryExists) {
                return response()->json(['message' => 'Category does not belong to this salon.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $slug = ! empty($validated['slug'])
            ? Str::slug($validated['slug'])
            : Str::slug($validated['name']);

        $baseSlug = $slug;
        $counter = 1;
        while ($tenant->services()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $validated['slug'] = $slug;
        $validated['buffer_minutes'] = $validated['buffer_minutes'] ?? 0;

        $service = $tenant->services()->create($validated);

        return response()->json([
            'message' => 'Service created successfully.',
            'service' => $service,
        ], Response::HTTP_CREATED);
    }

    public function show(Tenant $tenant, Service $service): JsonResponse
    {
        if ($service->tenant_id !== $tenant->id) {
            abort(Response::HTTP_NOT_FOUND, 'Service not found for this salon.');
        }

        $this->authorize('view', $service);

        return response()->json([
            'service' => $service->load(['category', 'branchOverrides.branch:id,name']),
        ]);
    }

    public function update(Request $request, Tenant $tenant, Service $service): JsonResponse
    {
        if ($service->tenant_id !== $tenant->id) {
            abort(Response::HTTP_NOT_FOUND, 'Service not found for this salon.');
        }

        $this->authorize('update', $service);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:service_categories,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'buffer_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'price_inr' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (! empty($validated['category_id'])) {
            $categoryExists = $tenant->serviceCategories()->where('id', $validated['category_id'])->exists();
            if (! $categoryExists) {
                return response()->json(['message' => 'Category does not belong to this salon.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $service->update($validated);

        return response()->json([
            'message' => 'Service updated successfully.',
            'service' => $service->fresh(),
        ]);
    }

    public function destroy(Tenant $tenant, Service $service): JsonResponse
    {
        if ($service->tenant_id !== $tenant->id) {
            abort(Response::HTTP_NOT_FOUND, 'Service not found for this salon.');
        }

        $this->authorize('delete', $service);

        $service->delete();

        return response()->json([
            'message' => 'Service deleted successfully.',
        ]);
    }

    public function overrideForBranch(Request $request, Tenant $tenant, Branch $branch, Service $service): JsonResponse
    {
        if ($branch->tenant_id !== $tenant->id || $service->tenant_id !== $tenant->id) {
            abort(Response::HTTP_NOT_FOUND, 'Resource not found for this salon.');
        }

        $this->authorize('update', $service);

        $validated = $request->validate([
            'price_inr' => ['nullable', 'integer', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'min:5'],
            'is_available' => ['required', 'boolean'],
        ]);

        $override = BranchService::updateOrCreate(
            ['branch_id' => $branch->id, 'service_id' => $service->id],
            $validated
        );

        return response()->json([
            'message' => 'Branch service override saved successfully.',
            'override' => $override,
        ]);
    }
}
