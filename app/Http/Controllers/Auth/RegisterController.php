<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterCustomerAction;
use App\Actions\Auth\RegisterSalonOwnerAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterCustomerRequest;
use App\Http\Requests\RegisterOwnerRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function showOwnerRegisterForm(): View
    {
        return view('auth.register-owner');
    }

    public function registerOwner(
        RegisterOwnerRequest $request,
        RegisterSalonOwnerAction $action
    ): RedirectResponse|JsonResponse {
        $result = $action->execute($request->validated());

        Auth::login($result['user']);
        $request->session()->regenerate();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Salon owner registered successfully.',
                'user' => $result['user'],
                'tenant' => $result['tenant'],
            ], 201);
        }

        return redirect()->route('tenant.dashboard', ['tenant' => $result['tenant']->slug]);
    }

    public function showCustomerRegisterForm(): View
    {
        return view('auth.register-customer');
    }

    public function registerCustomer(
        RegisterCustomerRequest $request,
        RegisterCustomerAction $action
    ): RedirectResponse|JsonResponse {
        $user = $action->execute($request->validated());

        Auth::login($user);
        $request->session()->regenerate();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Customer registered successfully.',
                'user' => $user,
            ], 201);
        }

        return redirect()->route('home');
    }
}
