<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DayClosing extends Model
{
    use HasFactory;

    protected $table = 'day_closings';

    protected $fillable = [
        'closing_date',
        'opening_cash',
        'opening_bank',
        'total_opening',
        'cash_received',
        'bank_received',
        'total_received',
        'cash_paid',
        'bank_paid',
        'total_paid',
        'net_cash_flow',
        'net_bank_flow',
        'total_net_flow',
        'closing_cash_expected',
        'closing_bank_expected',
        'closing_total_expected',
        'cash_actual_counted',
        'cash_difference',
        'denominations',
        'status',
        'closed_by',
        'closed_at',
        'notes',
        'next_day_opening_cash',
        'next_day_opening_bank',
        'next_day_opening_total',
    ];

    protected $casts = [
        'closing_date' => 'date',
        'opening_cash' => 'decimal:2',
        'opening_bank' => 'decimal:2',
        'total_opening' => 'decimal:2',
        'cash_received' => 'decimal:2',
        'bank_received' => 'decimal:2',
        'total_received' => 'decimal:2',
        'cash_paid' => 'decimal:2',
        'bank_paid' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'net_cash_flow' => 'decimal:2',
        'net_bank_flow' => 'decimal:2',
        'total_net_flow' => 'decimal:2',
        'closing_cash_expected' => 'decimal:2',
        'closing_bank_expected' => 'decimal:2',
        'closing_total_expected' => 'decimal:2',
        'cash_actual_counted' => 'decimal:2',
        'cash_difference' => 'decimal:2',
        'denominations' => 'array',
        'closed_at' => 'datetime',
        'next_day_opening_cash' => 'decimal:2',
        'next_day_opening_bank' => 'decimal:2',
        'next_day_opening_total' => 'decimal:2',
    ];

    /**
     * User who closed the day.
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
