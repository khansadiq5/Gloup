<?php

namespace App\Services;

use App\Mail\AppointmentCancelled;
use App\Mail\AppointmentConfirmation;
use App\Mail\AppointmentReminder;
use App\Mail\PaymentConfirmation;
use App\Mail\RefundConfirmation;
use App\Mail\RewardEarned;
use App\Mail\RewardRedeemed;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\CustomerProfile;
use App\Models\Payment;
use App\Models\Reward;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

class NotificationService
{
    /**
     * Send appointment confirmation notification.
     */
    public function sendAppointmentConfirmation(Appointment $appointment): void
    {
        $customer = $appointment->customerProfile;
        $branch = $appointment->branch;
        $tenant = $appointment->tenant;

        if (! $customer->email && ! $customer->phone) {
            Log::warning('Cannot send confirmation: no contact info', [
                'appointment_id' => $appointment->id,
            ]);

            return;
        }

        $data = [
            'customer_name' => $customer->first_name,
            'appointment_id' => $appointment->id,
            'date' => Carbon::parse($appointment->starts_at)->format('l, F j, Y'),
            'time' => Carbon::parse($appointment->starts_at)->format('g:i A'),
            'branch_name' => $branch->name,
            'branch_address' => $branch->address_line1.', '.$branch->city,
            'staff_name' => $appointment->staff?->name,
            'services' => $appointment->services->pluck('service.name')->implode(', '),
            'total_price' => $appointment->total_price_inr,
            'payment_status' => $appointment->payment_status->value,
            'management_url' => route('marketplace.booking.confirmation', [
                'tenant' => $tenant->slug,
                'appointment' => $appointment->id,
            ]),
        ];

        // Send email
        if ($customer->email) {
            Queue::push(function () use ($customer, $data) {
                Mail::to($customer->email)
                    ->send(new AppointmentConfirmation($data));
            });
        }

        // Send SMS (placeholder - integrate with SMS provider)
        if ($customer->phone) {
            $this->sendSms($customer->phone, $this->buildSmsMessage($data));
        }
    }

    /**
     * Send appointment reminder (24 hours before).
     */
    public function sendAppointmentReminder(Appointment $appointment): void
    {
        $customer = $appointment->customerProfile;
        $branch = $appointment->branch;
        $tenant = $appointment->tenant;

        if (! $customer->email && ! $customer->phone) {
            return;
        }

        $data = [
            'customer_name' => $customer->first_name,
            'appointment_id' => $appointment->id,
            'date' => Carbon::parse($appointment->starts_at)->format('l, F j, Y'),
            'time' => Carbon::parse($appointment->starts_at)->format('g:i A'),
            'branch_name' => $branch->name,
            'branch_address' => $branch->address_line1.', '.$branch->city,
            'services' => $appointment->services->pluck('service.name')->implode(', '),
            'management_url' => route('marketplace.booking.manage', [
                'tenant' => $tenant->slug,
                'appointment' => $appointment->id,
            ]),
        ];

        if ($customer->email) {
            Queue::push(function () use ($customer, $data) {
                Mail::to($customer->email)
                    ->send(new AppointmentReminder($data));
            });
        }

        if ($customer->phone) {
            $this->sendSms($customer->phone, $this->buildReminderSms($data));
        }
    }

    /**
     * Send appointment cancellation notification.
     */
    public function sendCancellationNotification(Appointment $appointment, string $reason): void
    {
        $customer = $appointment->customerProfile;

        if (! $customer->email && ! $customer->phone) {
            return;
        }

        $data = [
            'customer_name' => $customer->first_name,
            'appointment_id' => $appointment->id,
            'date' => Carbon::parse($appointment->starts_at)->format('l, F j, Y'),
            'time' => Carbon::parse($appointment->starts_at)->format('g:i A'),
            'reason' => $reason,
        ];

        if ($customer->email) {
            Queue::push(function () use ($customer, $data) {
                Mail::to($customer->email)
                    ->send(new AppointmentCancelled($data));
            });
        }
    }

    /**
     * Send payment confirmation.
     */
    public function sendPaymentConfirmation(Payment $payment): void
    {
        $customer = $payment->customerProfile;
        $appointment = $payment->appointment;

        if (! $customer->email && ! $customer->phone) {
            return;
        }

        $data = [
            'customer_name' => $customer->first_name,
            'payment_id' => $payment->id,
            'amount' => $payment->amount_paid_inr,
            'appointment_id' => $appointment?->id,
        ];

        if ($customer->email) {
            Queue::push(function () use ($customer, $data) {
                Mail::to($customer->email)
                    ->send(new PaymentConfirmation($data));
            });
        }
    }

    /**
     * Send refund notification.
     */
    public function sendRefundNotification(Payment $payment): void
    {
        $customer = $payment->customerProfile;

        if (! $customer->email && ! $customer->phone) {
            return;
        }

        $data = [
            'customer_name' => $customer->first_name,
            'payment_id' => $payment->id,
            'refund_amount' => $payment->refunded_amount_inr,
            'appointment_id' => $payment->appointment?->id,
        ];

        if ($customer->email) {
            Queue::push(function () use ($customer, $data) {
                Mail::to($customer->email)
                    ->send(new RefundConfirmation($data));
            });
        }
    }

    /**
     * Send loyalty reward earned notification.
     */
    public function sendRewardEarnedNotification(
        CustomerProfile $customer,
        Branch $branch,
        Reward $reward
    ): void {
        if (! $customer->email && ! $customer->phone) {
            return;
        }

        $data = [
            'customer_name' => $customer->first_name,
            'reward_name' => $reward->name,
            'branch_name' => $branch->name,
            'reward_type' => $reward->type->label(),
        ];

        if ($customer->email) {
            Queue::push(function () use ($customer, $data) {
                Mail::to($customer->email)
                    ->send(new RewardEarned($data));
            });
        }
    }

    /**
     * Send reward redeemed notification.
     */
    public function sendRewardRedeemedNotification(
        CustomerProfile $customer,
        Branch $branch,
        Reward $reward
    ): void {
        if (! $customer->email && ! $customer->phone) {
            return;
        }

        $data = [
            'customer_name' => $customer->first_name,
            'reward_name' => $reward->name,
            'branch_name' => $branch->name,
        ];

        if ($customer->email) {
            Queue::push(function () use ($customer, $data) {
                Mail::to($customer->email)
                    ->send(new RewardRedeemed($data));
            });
        }
    }

    /**
     * Send walk-in confirmation.
     */
    public function sendWalkInConfirmation(Appointment $appointment): void
    {
        $this->sendAppointmentConfirmation($appointment);
    }

    /**
     * Schedule appointment reminders for a branch.
     */
    public function scheduleReminders(Branch $branch): void
    {
        $appointments = Appointment::where('branch_id', $branch->id)
            ->where('status', 'confirmed')
            ->where('starts_at', '>', now())
            ->where('starts_at', '<=', now()->addDays(2))
            ->whereDoesntHave('events', function ($q) {
                $q->where('event_type', 'reminder_sent');
            })
            ->get();

        foreach ($appointments as $appointment) {
            $this->sendAppointmentReminder($appointment);

            $appointment->recordEvent(
                'reminder_sent',
                null,
                null,
                null,
                null,
                '24-hour reminder sent to customer',
                ['reminder_type' => '24_hour'],
                null
            );
        }
    }

    /**
     * Send SMS (placeholder for SMS provider integration).
     */
    protected function sendSms(string $phone, string $message): void
    {
        // TODO: Integrate with SMS provider (MSG91, Twilio, Fast2SMS, etc.)
        Log::info('SMS would be sent', [
            'phone' => $phone,
            'message' => $message,
        ]);
    }

    protected function buildSmsMessage(array $data): string
    {
        return "Hi {$data['customer_name']}, your appointment at {$data['branch_name']} is confirmed for {$data['date']} at {$data['time']}. Services: {$data['services']}. Total: ₹{$data['total_price']}. Manage: {$data['management_url']}";
    }

    protected function buildReminderSms(array $data): string
    {
        return "Reminder: Your appointment at {$data['branch_name']} is tomorrow at {$data['time']}. Services: {$data['services']}. Manage: {$data['management_url']}";
    }
}
