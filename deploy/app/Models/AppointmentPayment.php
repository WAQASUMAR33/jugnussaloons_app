<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentPayment extends Model
{
    use HasFactory;

    protected $table = 'appointment_payments';

    protected $fillable = [
        'appointment_id',
        'payment_date',
        'amount',
        'payment_mode',
        'bank_account_id',
        'split_payments',
        'notes',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'split_payments' => 'array',
    ];

    /**
     * Associated appointment booking.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    /**
     * Associated bank account if paid through bank/card/split.
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id', 'bankid');
    }

    /**
     * Direct accessors to parent appointment properties for seamless report templates.
     */
    public function getBookingNoAttribute(): ?string
    {
        return $this->appointment ? $this->appointment->booking_no : null;
    }

    public function getCustomerAttribute()
    {
        return $this->appointment ? $this->appointment->customer : null;
    }

    public function getEmployeeAttribute()
    {
        return $this->appointment ? $this->appointment->employee : null;
    }

    public function getNetAmountAttribute(): float
    {
        return $this->appointment ? (float)$this->appointment->net_amount : 0.0;
    }

    public function getPaidAmountAttribute(): float
    {
        return (float)$this->amount;
    }
}
