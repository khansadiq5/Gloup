<?php

namespace App\Models;

use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'appointment_id',
        'customer_profile_id',
        'gateway',
        'gateway_payment_id',
        'gateway_order_id',
        'method',
        'amount_inr',
        'amount_paid_inr',
        'currency',
        'status',
        'gateway_response',
        'metadata',
        'paid_at',
        'refunded_at',
        'refunded_amount_inr',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'gateway' => PaymentGateway::class,
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'amount_inr' => 'integer',
            'amount_paid_inr' => 'integer',
            'refunded_amount_inr' => 'integer',
            'gateway_response' => 'array',
            'metadata' => 'array',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class);
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status->isPaid();
    }

    public function isRefunded(): bool
    {
        return $this->status->isRefunded();
    }

    public function isFailed(): bool
    {
        return $this->status === PaymentStatus::FAILED;
    }
}
