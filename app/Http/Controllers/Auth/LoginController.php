<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse|JsonResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Logged in successfully.',
                'user' => Auth::user(),
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        // If user is a tenant owner/staff, redirect to their first active tenant dashboard
        $firstMembership = $user->tenantMemberships()->with('tenant')->where('is_active', true)->first();
        if ($firstMembership && $firstMembership->tenant) {
            return redirect()->intended(route('tenant.dashboard', ['tenant' => $firstMembership->tenant->slug]));
        }

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request): RedirectResponse|JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Logged out successfully.']);
        }

        return redirect()->route('home');
    }
}
