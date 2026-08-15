<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_no',
        'account_id',
        'employee_id',
        'appointment_date',
        'start_time',
        'total_amount',
        'discount',
        'net_amount',
        'paid_amount',
        'balance_due',
        'total_commission',
        'status',
        'notes',
        'receipt_image',
        'payment_mode',
        'extra_amount',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'total_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'total_commission' => 'decimal:2',
        'extra_amount' => 'decimal:2',
    ];

    /**
     * Customer account relationship.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Employee staff account relationship.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'employee_id');
    }

    /**
     * Appointment service line items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(AppointmentService::class, 'appointment_id');
    }
}
