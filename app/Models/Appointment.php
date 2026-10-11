<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'customer_profile_id',
        'staff_id',
        'status',
        'starts_at',
        'ends_at',
        'total_price_inr',
        'total_duration_minutes',
        'notes',
        'payment_status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => AppointmentStatus::class,
            'payment_status' => PaymentStatus::class,
            'total_price_inr' => 'integer',
            'total_duration_minutes' => 'integer',
            'metadata' => 'array',
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

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(AppointmentService::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AppointmentEvent::class);
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function canTransitionTo(AppointmentStatus $newStatus): bool
    {
        return $this->status->canTransitionTo($newStatus);
    }

    public function recordEvent(
        string $eventType,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $fromPaymentStatus = null,
        ?string $toPaymentStatus = null,
        ?string $description = null,
        ?array $metadata = null,
        ?int $userId = null
    ): AppointmentEvent {
        return $this->events()->create([
            'user_id' => $userId,
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'from_payment_status' => $fromPaymentStatus,
            'to_payment_status' => $toPaymentStatus,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}
