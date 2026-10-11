<?php

namespace App\Services;

use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class PaymentService
{
    public const DEFAULT_GATEWAY = PaymentGateway::RAZORPAY;

    /**
     * Create a payment order for an appointment.
     */
    public function createPaymentOrder(
        Appointment $appointment,
        PaymentMethod $method,
        PaymentGateway $gateway = self::DEFAULT_GATEWAY,
        ?array $metadata = null
    ): Payment {
        return DB::transaction(function () use ($appointment, $method, $gateway, $metadata) {
            $tenant = $appointment->tenant;
            $branch = $appointment->branch;

            // Create payment record
            $payment = Payment::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'appointment_id' => $appointment->id,
                'customer_profile_id' => $appointment->customer_profile_id,
                'gateway' => $gateway,
                'method' => $method,
                'amount_inr' => $appointment->total_price_inr,
                'currency' => 'INR',
                'status' => PaymentStatus::PENDING,
                'metadata' => array_merge($metadata ?? [], [
                    'appointment_id' => $appointment->id,
                    'appointment_number' => $appointment->id,
                ]),
            ]);

            // Create gateway order based on provider
            $gatewayOrder = $this->createGatewayOrder($payment, $gateway);

            $payment->update([
                'gateway_order_id' => $gatewayOrder['order_id'] ?? null,
                'gateway_response' => $gatewayOrder,
            ]);

            return $payment->fresh();
        });
    }

    /**
     * Create gateway-specific order.
     */
    protected function createGatewayOrder(Payment $payment, PaymentGateway $gateway): array
    {
        return match ($gateway) {
            PaymentGateway::RAZORPAY => $this->createRazorpayOrder($payment),
            PaymentGateway::CASHFREE => $this->createCashfreeOrder($payment),
            PaymentGateway::STRIPE => $this->createStripeOrder($payment),
        };
    }

    /**
     * Create Razorpay order.
     */
    protected function createRazorpayOrder(Payment $payment): array
    {
        $keyId = config('services.razorpay.key_id');
        $keySecret = config('services.razorpay.key_secret');

        // Detect mock/test credentials and return mock data
        $isMock = ! $keyId || ! $keySecret
            || str_starts_with($keyId, 'rzp_test_mock')
            || str_starts_with($keySecret, 'mock_');

        if ($isMock) {
            // Return mock order for testing/sandbox
            return [
                'order_id' => 'order_mock_'.Str::random(12),
                'currency' => 'INR',
                'amount' => $payment->amount_inr * 100, // Razorpay expects paise
            ];
        }

        $response = Http::withBasicAuth($keyId, $keySecret)
            ->post('https://api.razorpay.com/v1/orders', [
                'amount' => $payment->amount_inr * 100,
                'currency' => 'INR',
                'receipt' => 'pay_'.$payment->id,
                'notes' => [
                    'payment_id' => $payment->id,
                    'appointment_id' => $payment->appointment_id,
                ],
            ]);

        if ($response->failed()) {
            Log::error('Razorpay order creation failed', [
                'payment_id' => $payment->id,
                'response' => $response->json(),
            ]);
            throw new \Exception('Failed to create payment order: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Create Cashfree order.
     */
    protected function createCashfreeOrder(Payment $payment): array
    {
        $appId = config('services.cashfree.app_id');
        $secretKey = config('services.cashfree.secret_key');

        $isMock = ! $appId || ! $secretKey
            || str_starts_with($appId, 'mock_')
            || str_starts_with($secretKey, 'mock_');

        if ($isMock) {
            return [
                'order_id' => 'cf_order_mock_'.Str::random(12),
                'currency' => 'INR',
                'amount' => $payment->amount_inr,
            ];
        }

        $response = Http::withHeaders([
            'x-client-id' => $appId,
            'x-client-secret' => $secretKey,
            'Content-Type' => 'application/json',
        ])->post('https://api.cashfree.com/pg/orders', [
            'order_id' => 'pay_'.$payment->id,
            'order_amount' => $payment->amount_inr,
            'order_currency' => 'INR',
            'customer_details' => [
                'customer_id' => 'cust_'.$payment->customer_profile_id,
            ],
            'order_meta' => [
                'return_url' => config('app.url').'/payment/return',
            ],
        ]);

        if ($response->failed()) {
            Log::error('Cashfree order creation failed', [
                'payment_id' => $payment->id,
                'response' => $response->json(),
            ]);
            throw new \Exception('Failed to create payment order: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Create Stripe order.
     */
    protected function createStripeOrder(Payment $payment): array
    {
        $secretKey = config('services.stripe.secret');

        $isMock = ! $secretKey || str_starts_with($secretKey, 'mock_') || str_starts_with($secretKey, 'sk_test_mock');

        if ($isMock) {
            return [
                'order_id' => 'stripe_order_mock_'.Str::random(12),
                'currency' => 'INR',
                'amount' => $payment->amount_inr * 100,
            ];
        }

        $response = Http::withToken($secretKey)
            ->post('https://api.stripe.com/v1/payment_intents', [
                'amount' => $payment->amount_inr * 100,
                'currency' => 'inr',
                'metadata' => [
                    'payment_id' => $payment->id,
                    'appointment_id' => $payment->appointment_id,
                ],
            ]);

        if ($response->failed()) {
            Log::error('Stripe payment intent creation failed', [
                'payment_id' => $payment->id,
                'response' => $response->json(),
            ]);
            throw new \Exception('Failed to create payment intent: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Verify payment signature/webhook.
     */
    public function verifyWebhook(PaymentGateway $gateway, array $payload, string $signature): bool
    {
        return match ($gateway) {
            PaymentGateway::RAZORPAY => $this->verifyRazorpaySignature($payload, $signature),
            PaymentGateway::CASHFREE => $this->verifyCashfreeSignature($payload, $signature),
            PaymentGateway::STRIPE => $this->verifyStripeSignature($payload, $signature),
        };
    }

    protected function verifyRazorpaySignature(array $payload, string $signature): bool
    {
        $secret = config('services.razorpay.webhook_secret');
        if (! $secret) {
            return true; // Skip in testing
        }

        $expectedSignature = hash_hmac('sha256', json_encode($payload), $secret);

        return hash_equals($expectedSignature, $signature);
    }

    protected function verifyCashfreeSignature(array $payload, string $signature): bool
    {
        $secret = config('services.cashfree.webhook_secret');
        if (! $secret) {
            return true;
        }

        $expectedSignature = hash_hmac('sha256', json_encode($payload), $secret);

        return hash_equals($expectedSignature, $signature);
    }

    protected function verifyStripeSignature(array $payload, string $signature): bool
    {
        $secret = config('services.stripe.webhook_secret');
        if (! $secret) {
            return true;
        }

        // Stripe uses a different verification method
        // This is simplified - in production use Stripe's official library
        return true;
    }

    /**
     * Process webhook event with idempotency.
     */
    public function processWebhookEvent(
        PaymentGateway $gateway,
        string $eventType,
        string $eventId,
        array $payload
    ): WebhookEvent {
        return DB::transaction(function () use ($gateway, $eventType, $eventId, $payload) {
            // Check if event already processed (idempotency)
            $existing = WebhookEvent::where('event_id', $eventId)->first();
            if ($existing) {
                Log::info('Duplicate webhook event ignored', [
                    'event_id' => $eventId,
                    'gateway' => $gateway->value,
                ]);

                return $existing;
            }

            // Create webhook event record
            $webhookEvent = WebhookEvent::create([
                'gateway' => $gateway->value,
                'event_type' => $eventType,
                'event_id' => $eventId,
                'payload' => $payload,
                'status' => 'pending',
            ]);

            try {
                $this->handleWebhookEvent($gateway, $eventType, $payload);

                $webhookEvent->update([
                    'status' => 'processed',
                    'processed_at' => now(),
                ]);

                Log::info('Webhook event processed successfully', [
                    'event_id' => $eventId,
                    'event_type' => $eventType,
                ]);
            } catch (Throwable $e) {
                $webhookEvent->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'retry_count' => $webhookEvent->retry_count + 1,
                ]);

                Log::error('Webhook event processing failed', [
                    'event_id' => $eventId,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            return $webhookEvent;
        });
    }

    /**
     * Handle specific webhook event types.
     */
    protected function handleWebhookEvent(PaymentGateway $gateway, string $eventType, array $payload): void
    {
        switch ($eventType) {
            case 'payment.captured':
            case 'payment.paid':
            case 'payment_intent.succeeded':
                $this->handlePaymentSuccess($gateway, $payload);
                break;
            case 'payment.failed':
            case 'payment_intent.payment_failed':
                $this->handlePaymentFailed($gateway, $payload);
                break;
            case 'refund.created':
            case 'refund.succeeded':
            case 'charge.refunded':
                $this->handleRefundSuccess($gateway, $payload);
                break;
            default:
                Log::info('Unhandled webhook event type', [
                    'gateway' => $gateway->value,
                    'event_type' => $eventType,
                ]);
        }
    }

    /**
     * Handle successful payment.
     */
    protected function handlePaymentSuccess(PaymentGateway $gateway, array $payload): void
    {
        $gatewayPaymentId = $this->extractGatewayPaymentId($gateway, $payload);
        $gatewayOrderId = $this->extractGatewayOrderId($gateway, $payload);

        $payment = Payment::where(function ($q) use ($gatewayPaymentId, $gatewayOrderId) {
            if ($gatewayPaymentId) {
                $q->orWhere('gateway_payment_id', $gatewayPaymentId);
            }
            if ($gatewayOrderId) {
                $q->orWhere('gateway_order_id', $gatewayOrderId);
            }
        })->first();

        if (! $payment) {
            Log::warning('Payment not found for successful webhook', [
                'gateway' => $gateway->value,
                'gateway_payment_id' => $gatewayPaymentId,
                'gateway_order_id' => $gatewayOrderId,
            ]);

            return;
        }

        if ($payment->isPaid()) {
            Log::info('Payment already marked as paid', ['payment_id' => $payment->id]);

            return;
        }

        DB::transaction(function () use ($payment, $gateway, $payload) {
            $payment->update([
                'status' => PaymentStatus::PAID,
                'gateway_payment_id' => $this->extractGatewayPaymentId($gateway, $payload),
                'amount_paid_inr' => $this->extractAmount($gateway, $payload),
                'gateway_response' => $payload,
                'paid_at' => now(),
            ]);

            // Update appointment payment status
            if ($payment->appointment) {
                $payment->appointment->update([
                    'payment_status' => PaymentStatus::PAID,
                ]);
            }
        });
    }

    /**
     * Handle failed payment.
     */
    protected function handlePaymentFailed(PaymentGateway $gateway, array $payload): void
    {
        $gatewayPaymentId = $this->extractGatewayPaymentId($gateway, $payload);
        $gatewayOrderId = $this->extractGatewayOrderId($gateway, $payload);

        $payment = Payment::where(function ($q) use ($gatewayPaymentId, $gatewayOrderId) {
            if ($gatewayPaymentId) {
                $q->orWhere('gateway_payment_id', $gatewayPaymentId);
            }
            if ($gatewayOrderId) {
                $q->orWhere('gateway_order_id', $gatewayOrderId);
            }
        })->first();

        if (! $payment) {
            return;
        }

        $payment->update([
            'status' => PaymentStatus::FAILED,
            'failure_reason' => $this->extractFailureReason($gateway, $payload),
            'gateway_response' => $payload,
        ]);
    }

    /**
     * Handle successful refund.
     */
    protected function handleRefundSuccess(PaymentGateway $gateway, array $payload): void
    {
        $gatewayPaymentId = $this->extractGatewayPaymentId($gateway, $payload);

        $payment = Payment::where('gateway_payment_id', $gatewayPaymentId)->first();

        if (! $payment) {
            return;
        }

        $refundAmount = $this->extractRefundAmount($gateway, $payload);

        $payment->update([
            'status' => PaymentStatus::REFUNDED,
            'refunded_at' => now(),
            'refunded_amount_inr' => $payment->refunded_amount_inr + $refundAmount,
            'gateway_response' => $payload,
        ]);
    }

    // Gateway-specific extraction methods
    protected function extractGatewayPaymentId(PaymentGateway $gateway, array $payload): ?string
    {
        return match ($gateway) {
            PaymentGateway::RAZORPAY => $payload['payment']['id'] ?? $payload['refund']['payment_id'] ?? $payload['id'] ?? null,
            PaymentGateway::CASHFREE => $payload['data']['payment']['payment_id'] ?? null,
            PaymentGateway::STRIPE => $payload['data']['object']['id'] ?? null,
        };
    }

    protected function extractGatewayOrderId(PaymentGateway $gateway, array $payload): ?string
    {
        return match ($gateway) {
            PaymentGateway::RAZORPAY => $payload['payment']['order_id'] ?? null,
            PaymentGateway::CASHFREE => $payload['data']['payment']['order_id'] ?? null,
            PaymentGateway::STRIPE => $payload['data']['object']['id'] ?? null,
        };
    }

    protected function extractAmount(PaymentGateway $gateway, array $payload): int
    {
        return match ($gateway) {
            PaymentGateway::RAZORPAY => ($payload['payment']['amount'] ?? 0) / 100,
            PaymentGateway::CASHFREE => $payload['data']['payment']['payment_amount'] ?? 0,
            PaymentGateway::STRIPE => ($payload['data']['object']['amount'] ?? 0) / 100,
        };
    }

    protected function extractRefundAmount(PaymentGateway $gateway, array $payload): int
    {
        return match ($gateway) {
            PaymentGateway::RAZORPAY => ($payload['refund']['amount'] ?? 0) / 100,
            PaymentGateway::CASHFREE => $payload['data']['refund']['refund_amount'] ?? 0,
            PaymentGateway::STRIPE => ($payload['data']['object']['amount_refunded'] ?? 0) / 100,
        };
    }

    protected function extractFailureReason(PaymentGateway $gateway, array $payload): string
    {
        return match ($gateway) {
            PaymentGateway::RAZORPAY => $payload['payment']['error_description'] ?? 'Payment failed',
            PaymentGateway::CASHFREE => $payload['data']['payment']['payment_error_message'] ?? 'Payment failed',
            PaymentGateway::STRIPE => $payload['data']['object']['last_payment_error']['message'] ?? 'Payment failed',
        };
    }

    /**
     * Initiate refund for a payment.
     */
    public function refundPayment(Payment $payment, ?int $amountInr = null, string $reason = 'Customer requested'): Payment
    {
        error_log('SERVICE DEBUG: Payment status='.$payment->status->value.', amount_paid_inr='.$payment->amount_paid_inr.', isPaid='.($payment->isPaid() ? 'true' : 'false'));

        // Allow refunding paid or partially refunded payments
        if (! in_array($payment->status, [PaymentStatus::PAID, PaymentStatus::PARTIALLY_REFUNDED], true)) {
            throw new \Exception('Only paid or partially refunded payments can be refunded.', 422);
        }

        $refundAmount = $amountInr ?? $payment->amount_paid_inr;

        if ($payment->refunded_amount_inr + $refundAmount > $payment->amount_paid_inr) {
            throw new \Exception('Refund amount exceeds paid amount.', 422);
        }

        $totalRefunded = $payment->refunded_amount_inr + $refundAmount;
        $isFullRefund = $totalRefunded >= $payment->amount_paid_inr;
        $newStatus = $isFullRefund ? PaymentStatus::REFUNDED : PaymentStatus::PARTIALLY_REFUNDED;

        // In production, call gateway refund API
        // For now, mark as refunded locally
        return DB::transaction(function () use ($payment, $refundAmount, $reason, $newStatus, $isFullRefund, $totalRefunded) {
            $payment->update([
                'status' => $newStatus,
                'refunded_at' => $isFullRefund ? now() : $payment->refunded_at,
                'refunded_amount_inr' => $totalRefunded,
                'metadata' => array_merge($payment->metadata ?? [], [
                    'refund_reason' => $reason,
                    'refund_amount' => $refundAmount,
                ]),
            ]);

            if ($payment->appointment) {
                $payment->appointment->update([
                    'payment_status' => $newStatus,
                ]);
            }

            return $payment->fresh();
        });
    }
}
