<?php

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;

test('appointment status has correct values', function () {
    expect(AppointmentStatus::PENDING->value)->toBe('pending');
    expect(AppointmentStatus::CONFIRMED->value)->toBe('confirmed');
    expect(AppointmentStatus::COMPLETED->value)->toBe('completed');
    expect(AppointmentStatus::CANCELLED->value)->toBe('cancelled');
    expect(AppointmentStatus::NO_SHOW->value)->toBe('no_show');
});

test('appointment status isActive works correctly', function () {
    expect(AppointmentStatus::PENDING->isActive())->toBeTrue();
    expect(AppointmentStatus::CONFIRMED->isActive())->toBeTrue();
    expect(AppointmentStatus::COMPLETED->isActive())->toBeFalse();
    expect(AppointmentStatus::CANCELLED->isActive())->toBeFalse();
    expect(AppointmentStatus::NO_SHOW->isActive())->toBeFalse();
});

test('appointment status isTerminal works correctly', function () {
    expect(AppointmentStatus::PENDING->isTerminal())->toBeFalse();
    expect(AppointmentStatus::CONFIRMED->isTerminal())->toBeFalse();
    expect(AppointmentStatus::COMPLETED->isTerminal())->toBeTrue();
    expect(AppointmentStatus::CANCELLED->isTerminal())->toBeTrue();
    expect(AppointmentStatus::NO_SHOW->isTerminal())->toBeTrue();
});

test('appointment status transitions are valid', function () {
    // PENDING can go to CONFIRMED or CANCELLED
    expect(AppointmentStatus::PENDING->canTransitionTo(AppointmentStatus::CONFIRMED))->toBeTrue();
    expect(AppointmentStatus::PENDING->canTransitionTo(AppointmentStatus::CANCELLED))->toBeTrue();
    expect(AppointmentStatus::PENDING->canTransitionTo(AppointmentStatus::COMPLETED))->toBeFalse();
    expect(AppointmentStatus::PENDING->canTransitionTo(AppointmentStatus::NO_SHOW))->toBeFalse();
    expect(AppointmentStatus::PENDING->canTransitionTo(AppointmentStatus::PENDING))->toBeFalse();

    // CONFIRMED can go to COMPLETED, CANCELLED, NO_SHOW
    expect(AppointmentStatus::CONFIRMED->canTransitionTo(AppointmentStatus::COMPLETED))->toBeTrue();
    expect(AppointmentStatus::CONFIRMED->canTransitionTo(AppointmentStatus::CANCELLED))->toBeTrue();
    expect(AppointmentStatus::CONFIRMED->canTransitionTo(AppointmentStatus::NO_SHOW))->toBeTrue();
    expect(AppointmentStatus::CONFIRMED->canTransitionTo(AppointmentStatus::PENDING))->toBeFalse();

    // Terminal states cannot transition
    expect(AppointmentStatus::COMPLETED->canTransitionTo(AppointmentStatus::CANCELLED))->toBeFalse();
    expect(AppointmentStatus::CANCELLED->canTransitionTo(AppointmentStatus::COMPLETED))->toBeFalse();
    expect(AppointmentStatus::NO_SHOW->canTransitionTo(AppointmentStatus::COMPLETED))->toBeFalse();
});

test('payment status has correct values', function () {
    expect(PaymentStatus::UNPAID->value)->toBe('unpaid');
    expect(PaymentStatus::PAY_AT_SALON->value)->toBe('pay_at_salon');
    expect(PaymentStatus::PENDING->value)->toBe('pending');
    expect(PaymentStatus::PAID->value)->toBe('paid');
    expect(PaymentStatus::REFUNDED->value)->toBe('refunded');
    expect(PaymentStatus::FAILED->value)->toBe('failed');
    expect(PaymentStatus::PARTIALLY_REFUNDED->value)->toBe('partially_refunded');
});

test('payment status isPaid works correctly', function () {
    expect(PaymentStatus::UNPAID->isPaid())->toBeFalse();
    expect(PaymentStatus::PAY_AT_SALON->isPaid())->toBeFalse();
    expect(PaymentStatus::PENDING->isPaid())->toBeFalse();
    expect(PaymentStatus::PAID->isPaid())->toBeTrue();
    expect(PaymentStatus::REFUNDED->isPaid())->toBeFalse();
    expect(PaymentStatus::FAILED->isPaid())->toBeFalse();
    expect(PaymentStatus::PARTIALLY_REFUNDED->isPaid())->toBeTrue();
});

test('payment status isRefunded works correctly', function () {
    expect(PaymentStatus::UNPAID->isRefunded())->toBeFalse();
    expect(PaymentStatus::PAY_AT_SALON->isRefunded())->toBeFalse();
    expect(PaymentStatus::PENDING->isRefunded())->toBeFalse();
    expect(PaymentStatus::PAID->isRefunded())->toBeFalse();
    expect(PaymentStatus::REFUNDED->isRefunded())->toBeTrue();
    expect(PaymentStatus::FAILED->isRefunded())->toBeFalse();
    expect(PaymentStatus::PARTIALLY_REFUNDED->isRefunded())->toBeTrue();
});

test('payment status transitions are valid', function () {
    // UNPAID can go to PAY_AT_SALON or PENDING
    expect(PaymentStatus::UNPAID->canTransitionTo(PaymentStatus::PAY_AT_SALON))->toBeTrue();
    expect(PaymentStatus::UNPAID->canTransitionTo(PaymentStatus::PENDING))->toBeTrue();
    expect(PaymentStatus::UNPAID->canTransitionTo(PaymentStatus::PAID))->toBeFalse();

    // PAY_AT_SALON can go to PAID or FAILED
    expect(PaymentStatus::PAY_AT_SALON->canTransitionTo(PaymentStatus::PAID))->toBeTrue();
    expect(PaymentStatus::PAY_AT_SALON->canTransitionTo(PaymentStatus::FAILED))->toBeTrue();
    expect(PaymentStatus::PAY_AT_SALON->canTransitionTo(PaymentStatus::REFUNDED))->toBeFalse();

    // PENDING can go to PAID or FAILED
    expect(PaymentStatus::PENDING->canTransitionTo(PaymentStatus::PAID))->toBeTrue();
    expect(PaymentStatus::PENDING->canTransitionTo(PaymentStatus::FAILED))->toBeTrue();

    // PAID can go to REFUNDED or PARTIALLY_REFUNDED
    expect(PaymentStatus::PAID->canTransitionTo(PaymentStatus::REFUNDED))->toBeTrue();
    expect(PaymentStatus::PAID->canTransitionTo(PaymentStatus::PARTIALLY_REFUNDED))->toBeTrue();

    // REFUNDED cannot transition
    expect(PaymentStatus::REFUNDED->canTransitionTo(PaymentStatus::PAID))->toBeFalse();

    // FAILED can go to PENDING or PAY_AT_SALON
    expect(PaymentStatus::FAILED->canTransitionTo(PaymentStatus::PENDING))->toBeTrue();
    expect(PaymentStatus::FAILED->canTransitionTo(PaymentStatus::PAY_AT_SALON))->toBeTrue();

    // PARTIALLY_REFUNDED can go to REFUNDED
    expect(PaymentStatus::PARTIALLY_REFUNDED->canTransitionTo(PaymentStatus::REFUNDED))->toBeTrue();
    expect(PaymentStatus::PARTIALLY_REFUNDED->canTransitionTo(PaymentStatus::PAID))->toBeFalse();
});
