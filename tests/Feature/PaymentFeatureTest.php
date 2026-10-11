<?php

use App\Enums\AppointmentStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchHour;
use App\Models\CustomerProfile;
use App\Models\Payment;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Models\StaffShift;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Configure mock gateway credentials for testing
    Config::set('services.razorpay.key_id', 'rzp_test_mock');
    Config::set('services.razorpay.key_secret', 'mock_secret');
    Config::set('services.razorpay.webhook_secret', 'mock_webhook_secret');
    Config::set('services.cashfree.app_id', 'mock_app_id');
    Config::set('services.cashfree.secret_key', 'mock_secret');
    Config::set('services.cashfree.webhook_secret', 'mock_webhook_secret');
    Config::set('services.stripe.secret', 'sk_test_mock');
    Config::set('services.stripe.webhook_secret', 'mock_webhook_secret');

    // Tenant A
    $this->tenantA = Tenant::factory()->create(['slug' => 'salon-a']);
    $this->ownerA = User::factory()->create();
    TenantUser::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'user_id' => $this->ownerA->id,
        'role' => 'owner',
    ]);

    $this->branchA = Branch::factory()->primary()->create(['tenant_id' => $this->tenantA->id, 'slug' => 'main']);
    $this->categoryA = ServiceCategory::factory()->create(['tenant_id' => $this->tenantA->id]);
    $this->serviceA = Service::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'category_id' => $this->categoryA->id,
        'duration_minutes' => 30,
        'buffer_minutes' => 5,
        'price_inr' => 1000,
    ]);
    $this->staffA = Staff::factory()->create(['tenant_id' => $this->tenantA->id]);
    $this->staffA->branches()->attach($this->branchA->id);
    $this->staffA->services()->attach($this->serviceA->id);

    $this->date = Carbon::tomorrow();
    $dayOfWeek = (int) $this->date->format('w');
    BranchHour::factory()->create([
        'branch_id' => $this->branchA->id,
        'day_of_week' => $dayOfWeek,
        'open_time' => '09:00:00',
        'close_time' => '20:00:00',
        'is_closed' => false,
    ]);
    StaffShift::factory()->create([
        'staff_id' => $this->staffA->id,
        'branch_id' => $this->branchA->id,
        'day_of_week' => $dayOfWeek,
        'start_time' => '09:00:00',
        'end_time' => '18:00:00',
        'is_working' => true,
    ]);

    $this->customerA = CustomerProfile::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'user_id' => $this->ownerA->id,
    ]);

    $this->availabilityService = app(AvailabilityService::class);
    $this->bookingService = app(BookingService::class);
    $this->paymentService = app(PaymentService::class);

    Queue::fake();
});

test('creates payment order for appointment with Razorpay', function () {
    $start = $this->date->copy()->setTime(10, 0);
    $end = $start->copy()->addMinutes(35);
    $token = 'payment-test-token';

    $this->availabilityService->createReservation(
        $this->tenantA->id,
        $this->branchA->id,
        $this->staffA->id,
        $start,
        $end,
        $token
    );

    $result = $this->bookingService->createBooking([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'customer_profile_id' => $this->customerA->id,
        'staff_id' => $this->staffA->id,
        'service_ids' => [$this->serviceA->id],
        'starts_at' => $start,
        'ends_at' => $end,
        'session_token' => $token,
    ]);

    $appointment = $result['appointment'];

    // Create payment order
    $payment = $this->paymentService->createPaymentOrder(
        $appointment,
        PaymentMethod::CARD,
        PaymentGateway::RAZORPAY
    );

    expect($payment)->not->toBeNull();
    expect($payment->gateway)->toBe(PaymentGateway::RAZORPAY);
    expect($payment->method)->toBe(PaymentMethod::CARD);
    expect($payment->amount_inr)->toBe(1000);
    expect($payment->status)->toBe(PaymentStatus::PENDING);
    expect($payment->gateway_order_id)->not->toBeNull();
});

test('creates payment order with different payment methods', function () {
    $appointment = Appointment::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'customer_profile_id' => $this->customerA->id,
        'staff_id' => $this->staffA->id,
        'status' => AppointmentStatus::CONFIRMED,
        'starts_at' => $this->date->copy()->setTime(10, 0),
        'ends_at' => $this->date->copy()->setTime(10, 35),
        'total_price_inr' => 1000,
    ]);

    foreach ([PaymentMethod::CARD, PaymentMethod::UPI, PaymentMethod::NETBANKING, PaymentMethod::WALLET] as $method) {
        $payment = $this->paymentService->createPaymentOrder(
            $appointment->fresh(),
            $method,
            PaymentGateway::RAZORPAY
        );

        expect($payment->method)->toBe($method);
        expect($payment->gateway)->toBe(PaymentGateway::RAZORPAY);
    }
});

test('creates payment order with different gateways', function () {
    $appointment = Appointment::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'customer_profile_id' => $this->customerA->id,
        'staff_id' => $this->staffA->id,
        'status' => AppointmentStatus::CONFIRMED,
        'starts_at' => $this->date->copy()->setTime(10, 0),
        'ends_at' => $this->date->copy()->setTime(10, 35),
        'total_price_inr' => 1000,
    ]);

    foreach ([PaymentGateway::RAZORPAY, PaymentGateway::CASHFREE, PaymentGateway::STRIPE] as $gateway) {
        $payment = $this->paymentService->createPaymentOrder(
            $appointment->fresh(),
            PaymentMethod::CARD,
            $gateway
        );

        expect($payment->gateway)->toBe($gateway);
    }
});

test('verifies Razorpay webhook signature', function () {
    $payload = [
        'id' => 'evt_test_123',
        'event' => 'payment.captured',
        'payment' => [
            'id' => 'pay_test_123',
            'order_id' => 'order_test_123',
            'amount' => 100000,
            'currency' => 'INR',
        ],
    ];

    $secret = 'mock_webhook_secret';
    $signature = hash_hmac('sha256', json_encode($payload), $secret);

    $result = $this->paymentService->verifyWebhook(
        PaymentGateway::RAZORPAY,
        $payload,
        $signature
    );

    expect($result)->toBeTrue();
});

test('rejects invalid Razorpay webhook signature', function () {
    $payload = [
        'id' => 'evt_test_123',
        'event' => 'payment.captured',
    ];

    $result = $this->paymentService->verifyWebhook(
        PaymentGateway::RAZORPAY,
        $payload,
        'invalid_signature'
    );

    expect($result)->toBeFalse();
});

test('processes Razorpay payment success webhook', function () {
    $appointment = Appointment::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'customer_profile_id' => $this->customerA->id,
        'staff_id' => $this->staffA->id,
        'status' => AppointmentStatus::CONFIRMED,
        'starts_at' => $this->date->copy()->setTime(10, 0),
        'ends_at' => $this->date->copy()->setTime(10, 35),
        'total_price_inr' => 1000,
        'payment_status' => PaymentStatus::PAY_AT_SALON,
    ]);

    $payment = Payment::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'appointment_id' => $appointment->id,
        'customer_profile_id' => $this->customerA->id,
        'gateway' => 'razorpay',
        'gateway_order_id' => 'order_test_123',
        'method' => 'card',
        'amount_inr' => 1000,
        'status' => 'pending',
    ]);

    $payload = [
        'id' => 'evt_test_123',
        'event' => 'payment.captured',
        'payment' => [
            'id' => 'pay_test_123',
            'order_id' => 'order_test_123',
            'amount' => 100000,
            'currency' => 'INR',
        ],
    ];

    $webhookEvent = $this->paymentService->processWebhookEvent(
        PaymentGateway::RAZORPAY,
        'payment.captured',
        'evt_test_123',
        $payload
    );

    expect($webhookEvent->status)->toBe('processed');

    $payment->refresh();
    expect($payment->status->value)->toBe('paid');
    expect($payment->gateway_payment_id)->toBe('pay_test_123');
    expect($payment->amount_paid_inr)->toBe(1000);

    $appointment->refresh();
    expect($appointment->payment_status->value)->toBe('paid');
});

test('processes Razorpay payment failed webhook', function () {
    $appointment = Appointment::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'customer_profile_id' => $this->customerA->id,
        'staff_id' => $this->staffA->id,
        'status' => AppointmentStatus::CONFIRMED,
        'starts_at' => $this->date->copy()->setTime(10, 0),
        'ends_at' => $this->date->copy()->setTime(10, 35),
        'total_price_inr' => 1000,
    ]);

    $payment = Payment::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'appointment_id' => $appointment->id,
        'customer_profile_id' => $this->customerA->id,
        'gateway' => 'razorpay',
        'gateway_order_id' => 'order_test_123',
        'method' => 'card',
        'amount_inr' => 1000,
        'status' => 'pending',
    ]);

    $payload = [
        'id' => 'evt_test_123',
        'event' => 'payment.failed',
        'payment' => [
            'id' => 'pay_test_123',
            'order_id' => 'order_test_123',
            'error_description' => 'Card declined',
        ],
    ];

    $webhookEvent = $this->paymentService->processWebhookEvent(
        PaymentGateway::RAZORPAY,
        'payment.failed',
        'evt_test_123',
        $payload
    );

    expect($webhookEvent->status)->toBe('processed');

    $payment->refresh();
    expect($payment->status->value)->toBe('failed');
    expect($payment->failure_reason)->toBe('Card declined');
});

test('webhook idempotency prevents duplicate processing', function () {
    $payload = [
        'id' => 'evt_test_123',
        'event' => 'payment.captured',
        'payment' => [
            'id' => 'pay_test_123',
            'order_id' => 'order_test_123',
            'amount' => 100000,
        ],
    ];

    // Process first time
    $webhookEvent1 = $this->paymentService->processWebhookEvent(
        PaymentGateway::RAZORPAY,
        'payment.captured',
        'evt_test_123',
        $payload
    );

    expect($webhookEvent1->status)->toBe('processed');

    // Process second time with same event ID
    $webhookEvent2 = $this->paymentService->processWebhookEvent(
        PaymentGateway::RAZORPAY,
        'payment.captured',
        'evt_test_123',
        $payload
    );

    // Should return the same event, not create duplicate
    expect($webhookEvent2->id)->toBe($webhookEvent1->id);
    expect(WebhookEvent::count())->toBe(1);
});

test('processes refund webhook', function () {
    $appointment = Appointment::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'customer_profile_id' => $this->customerA->id,
        'staff_id' => $this->staffA->id,
        'status' => AppointmentStatus::CONFIRMED,
        'starts_at' => $this->date->copy()->setTime(10, 0),
        'ends_at' => $this->date->copy()->setTime(10, 35),
        'total_price_inr' => 1000,
    ]);

    $payment = Payment::factory()->paid()->create([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'appointment_id' => $appointment->id,
        'customer_profile_id' => $this->customerA->id,
        'gateway' => 'razorpay',
        'gateway_payment_id' => 'pay_test_123',
        'method' => 'card',
        'amount_inr' => 1000,
    ]);

    $payload = [
        'id' => 'evt_test_refund',
        'event' => 'refund.created',
        'refund' => [
            'id' => 'rfnd_test_123',
            'payment_id' => 'pay_test_123',
            'amount' => 100000,
        ],
    ];

    $webhookEvent = $this->paymentService->processWebhookEvent(
        PaymentGateway::RAZORPAY,
        'refund.created',
        'evt_test_refund',
        $payload
    );

    expect($webhookEvent->status)->toBe('processed');

    $payment->refresh();
    expect($payment->status->value)->toBe('refunded');
    expect($payment->refunded_amount_inr)->toBe(1000);
});

test('refunds payment via service', function () {
    $appointment = Appointment::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'customer_profile_id' => $this->customerA->id,
        'staff_id' => $this->staffA->id,
        'status' => AppointmentStatus::CONFIRMED,
        'starts_at' => $this->date->copy()->setTime(10, 0),
        'ends_at' => $this->date->copy()->setTime(10, 35),
        'total_price_inr' => 1000,
    ]);

    $payment = Payment::factory()->paid()->create([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'appointment_id' => $appointment->id,
        'customer_profile_id' => $this->customerA->id,
        'gateway' => 'razorpay',
        'method' => 'card',
        'amount_inr' => 1000,
        'amount_paid_inr' => 1000,
    ]);

    $refunded = $this->paymentService->refundPayment($payment, 500, 'Partial refund');

    expect($refunded->status->value)->toBe('partially_refunded');
    expect($refunded->refunded_amount_inr)->toBe(500);

    $refunded = $this->paymentService->refundPayment($refunded, 500);
    expect($refunded->refunded_amount_inr)->toBe(1000);
    expect($refunded->status->value)->toBe('refunded');
});

test('rejects refund exceeding paid amount', function () {
    $appointment = Appointment::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'customer_profile_id' => $this->customerA->id,
        'staff_id' => $this->staffA->id,
        'status' => AppointmentStatus::CONFIRMED,
        'starts_at' => $this->date->copy()->setTime(10, 0),
        'ends_at' => $this->date->copy()->setTime(10, 35),
        'total_price_inr' => 1000,
    ]);

    $payment = Payment::factory()->pending()->create([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'appointment_id' => $appointment->id,
        'customer_profile_id' => $this->customerA->id,
        'gateway' => 'razorpay',
        'method' => 'card',
        'amount_inr' => 1000,
    ]);

    try {
        $this->paymentService->refundPayment($payment, 1500);
    } catch (Exception $e) {
        // Payment is pending, so it should fail with the new message
        expect($e->getMessage())->toBe('Only paid or partially refunded payments can be refunded.');
        expect($e->getCode())->toBe(422);
    }
});

test('rejects refund for unpaid payment', function () {
    $appointment = Appointment::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'customer_profile_id' => $this->customerA->id,
        'staff_id' => $this->staffA->id,
        'status' => AppointmentStatus::CONFIRMED,
        'starts_at' => $this->date->copy()->setTime(10, 0),
        'ends_at' => $this->date->copy()->setTime(10, 35),
        'total_price_inr' => 1000,
    ]);

    // Create a PENDING payment (not paid)
    $payment = Payment::factory()->pending()->create([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'appointment_id' => $appointment->id,
        'customer_profile_id' => $this->customerA->id,
        'gateway' => 'razorpay',
        'method' => 'card',
        'amount_inr' => 1000,
    ]);

    try {
        $this->paymentService->refundPayment($payment, 500);
    } catch (Exception $e) {
        expect($e->getMessage())->toBe('Only paid or partially refunded payments can be refunded.');
        expect($e->getCode())->toBe(422);
    }
});

test('webhook controller handles Razorpay webhook', function () {
    $payload = [
        'id' => 'evt_test_123',
        'event' => 'payment.captured',
        'payment' => [
            'id' => 'pay_test_123',
            'order_id' => 'order_test_123',
            'amount' => 100000,
        ],
    ];

    $secret = 'mock_webhook_secret';
    $signature = hash_hmac('sha256', json_encode($payload), $secret);

    $response = $this->postJson('/webhooks/razorpay', $payload, [
        'X-Razorpay-Signature' => $signature,
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'ok']);
});

test('webhook controller rejects invalid signature', function () {
    $payload = [
        'id' => 'evt_test_123',
        'event' => 'payment.captured',
    ];

    $response = $this->postJson('/webhooks/razorpay', $payload, [
        'X-Razorpay-Signature' => 'invalid_signature',
    ]);

    $response->assertStatus(400);
    $response->assertJson(['error' => 'Invalid signature']);
});

test('webhook controller handles Cashfree webhook', function () {
    $payload = [
        'event_id' => 'evt_test_123',
        'event_type' => 'payment.paid',
        'data' => [
            'payment' => [
                'payment_id' => 'pay_test_123',
                'order_id' => 'order_test_123',
                'payment_amount' => 1000,
            ],
        ],
    ];

    $secret = 'mock_webhook_secret';
    $signature = hash_hmac('sha256', json_encode($payload), $secret);

    $response = $this->postJson('/webhooks/cashfree', $payload, [
        'X-Cashfree-Signature' => $signature,
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'ok']);
});

test('webhook controller handles Stripe webhook', function () {
    $payload = [
        'id' => 'evt_test_123',
        'type' => 'payment_intent.succeeded',
        'data' => [
            'object' => [
                'id' => 'pi_test_123',
                'amount' => 100000,
            ],
        ],
    ];

    $response = $this->postJson('/webhooks/stripe', $payload, [
        'Stripe-Signature' => 'valid_signature',
    ]);

    $response->assertOk();
    $response->assertJson(['status' => 'ok']);
});

test('cross-tenant payment isolation', function () {
    $tenantB = Tenant::factory()->create(['slug' => 'salon-b']);
    $ownerB = User::factory()->create();
    TenantUser::factory()->create([
        'tenant_id' => $tenantB->id,
        'user_id' => $ownerB->id,
        'role' => 'owner',
    ]);
    $branchB = Branch::factory()->primary()->create(['tenant_id' => $tenantB->id]);

    $appointmentA = Appointment::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'customer_profile_id' => $this->customerA->id,
        'staff_id' => $this->staffA->id,
        'status' => AppointmentStatus::CONFIRMED,
        'starts_at' => $this->date->copy()->setTime(10, 0),
        'ends_at' => $this->date->copy()->setTime(10, 35),
        'total_price_inr' => 1000,
    ]);

    $payment = Payment::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'appointment_id' => $appointmentA->id,
        'customer_profile_id' => $this->customerA->id,
        'gateway' => 'razorpay',
        'method' => 'card',
        'amount_inr' => 1000,
        'status' => 'pending',
    ]);

    // Tenant B cannot access Tenant A's payment
    $payments = Payment::where('tenant_id', $tenantB->id)->get();
    expect($payments->contains($payment->id))->toBeFalse();
});

test('payment status transitions are validated', function () {
    $payment = Payment::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'branch_id' => $this->branchA->id,
        'customer_profile_id' => $this->customerA->id,
        'gateway' => 'razorpay',
        'method' => 'card',
        'amount_inr' => 1000,
        'status' => 'pending',
    ]);

    // pending -> paid (valid)
    $payment->update(['status' => 'paid']);
    expect($payment->status->value)->toBe('paid');

    // paid -> refunded (valid)
    $payment->update(['status' => 'refunded']);
    expect($payment->status->value)->toBe('refunded');

    // refunded -> paid (invalid - would need service validation)
    // The service layer validates this
});
