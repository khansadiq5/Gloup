<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentService extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'service_id',
        'price_inr',
        'duration_minutes',
        'buffer_minutes',
    ];

    protected function casts(): array
    {
        return [
            'price_inr' => 'integer',
            'duration_minutes' => 'integer',
            'buffer_minutes' => 'integer',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function getTotalDurationMinutes(): int
    {
        return $this->duration_minutes + $this->buffer_minutes;
    }
}
