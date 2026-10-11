<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], SymfonyResponse::HTTP_UNAUTHORIZED);
        }

        if (! $user->isPlatformAdmin()) {
            return response()->json([
                'message' => 'Forbidden. Platform admin access required.',
            ], SymfonyResponse::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
