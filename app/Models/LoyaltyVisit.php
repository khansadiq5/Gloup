<?php

namespace App\Models;

use App\Enums\LoyaltyVisitStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'customer_profile_id',
        'source_type',
        'source_id',
        'status',
        'visit_number',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => LoyaltyVisitStatus::class,
            'visit_number' => 'integer',
            'source_id' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class);
    }

    public function isQualified(): bool
    {
        return $this->status === LoyaltyVisitStatus::QUALIFIED;
    }
}
