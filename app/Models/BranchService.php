<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchService extends Model
{
    use HasFactory;

    protected $table = 'branch_services';

    protected $fillable = [
        'branch_id',
        'service_id',
        'price_inr',
        'duration_minutes',
        'is_available',
    ];

    protected function casts(): array
    {
        return [
            'price_inr' => 'integer',
            'duration_minutes' => 'integer',
            'is_available' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
