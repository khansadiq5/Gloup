<?php

namespace App\Http\Controllers\Payment;

use App\Enums\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * Handle Razorpay webhook.
     */
    public function razorpay(Request $request): JsonResponse
    {
        $signature = $request->header('X-Razorpay-Signature');
        $payload = $request->all();
        $eventId = $payload['id'] ?? Str::random(20);

        if (! $this->paymentService->verifyWebhook(PaymentGateway::RAZORPAY, $payload, $signature)) {
            Log::warning('Invalid Razorpay webhook signature', [
                'event_id' => $eventId,
            ]);

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        try {
            $this->paymentService->processWebhookEvent(
                PaymentGateway::RAZORPAY,
                $payload['event'] ?? 'unknown',
                $eventId,
                $payload
            );

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('Razorpay webhook processing failed', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle Cashfree webhook.
     */
    public function cashfree(Request $request): JsonResponse
    {
        $signature = $request->header('X-Cashfree-Signature');
        $payload = $request->all();
        $eventId = $payload['event_id'] ?? $payload['data']['event_id'] ?? Str::random(20);

        if (! $this->paymentService->verifyWebhook(PaymentGateway::CASHFREE, $payload, $signature)) {
            Log::warning('Invalid Cashfree webhook signature', [
                'event_id' => $eventId,
            ]);

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        try {
            $this->paymentService->processWebhookEvent(
                PaymentGateway::CASHFREE,
                $payload['event_type'] ?? 'unknown',
                $eventId,
                $payload
            );

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('Cashfree webhook processing failed', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Handle Stripe webhook.
     */
    public function stripe(Request $request): JsonResponse
    {
        $signature = $request->header('Stripe-Signature');
        $payload = $request->all();
        $eventId = $payload['id'] ?? Str::random(20);

        if (! $this->paymentService->verifyWebhook(PaymentGateway::STRIPE, $payload, $signature)) {
            Log::warning('Invalid Stripe webhook signature', [
                'event_id' => $eventId,
            ]);

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        try {
            $this->paymentService->processWebhookEvent(
                PaymentGateway::STRIPE,
                $payload['type'] ?? 'unknown',
                $eventId,
                $payload
            );

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('Stripe webhook processing failed', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Generic webhook endpoint for testing.
     */
    public function generic(Request $request, string $gateway): JsonResponse
    {
        if (! in_array($gateway, ['razorpay', 'cashfree', 'stripe'])) {
            return response()->json(['error' => 'Invalid gateway'], 400);
        }

        $payload = $request->all();
        $eventId = $payload['id'] ?? Str::random(20);

        try {
            $this->paymentService->processWebhookEvent(
                PaymentGateway::from($gateway),
                $payload['event'] ?? $payload['event_type'] ?? $payload['type'] ?? 'unknown',
                $eventId,
                $payload
            );

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::error('Generic webhook processing failed', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }
}
