<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenantContext
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenantParam = $request->route('tenant');

        if ($tenantParam instanceof Tenant) {
            $tenant = $tenantParam;
        } elseif (is_string($tenantParam) || is_numeric($tenantParam)) {
            $tenant = Tenant::where('slug', $tenantParam)
                ->orWhere('id', $tenantParam)
                ->first();
        } else {
            $tenant = null;
        }

        if (! $tenant) {
            abort(Response::HTTP_NOT_FOUND, 'Tenant not found.');
        }

        if (! $tenant->isActive()) {
            abort(Response::HTTP_FORBIDDEN, 'This salon is currently inactive.');
        }

        // Set tenant context for this request
        $this->tenantContext->setTenant($tenant);

        // If the route is an authenticated tenant operation, verify user belongs to this tenant
        if ($request->user()) {
            /** @var User $user */
            $user = $request->user();

            if (! $user->isPlatformAdmin() && ! $user->belongsToTenant($tenant)) {
                abort(Response::HTTP_FORBIDDEN, 'You do not have access to this salon.');
            }
        }

        return $next($request);
    }
}
