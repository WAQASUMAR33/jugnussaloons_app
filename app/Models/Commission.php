<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    use HasFactory;

    protected $table = 'commissions';

    protected $fillable = [
        'employee_id',
        'amount_of_work',
        'total_amount',
        'date',
        'description',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'amount_of_work' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Relationship to employee account.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'employee_id');
    }

    /**
     * Calculate effective commission rate percentage.
     */
    public function getCommissionPercentageAttribute(): float
    {
        if ($this->amount_of_work > 0) {
            return round(($this->total_amount / $this->amount_of_work) * 100, 2);
        }
        return 0.0;
    }
}
