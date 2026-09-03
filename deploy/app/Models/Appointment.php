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
        'order_type',
        'account_id',
        'employee_id',
        'appointment_date',
        'start_time',
        'total_amount',
        'discount',
        'discount_type',
        'discount_percentage',
        'discount_status',
        'discount_approved_by',
        'net_amount',
        'paid_amount',
        'balance_due',
        'status',
        'ranking',
        'ranking_notes',
        'ranked_by',
        'ranked_at',
        'notes',
        'receipt_image',
        'payment_mode',
        'extra_amount',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'total_amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance_due' => 'decimal:2',
        'extra_amount' => 'decimal:2',
        'ranking' => 'integer',
        'ranked_at' => 'datetime',
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
     * User who ranked the employee.
     */
    public function rankedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ranked_by');
    }

    /**
     * Get human-readable rank label.
     */
    public function getRankLabelAttribute(): string
    {
        return match($this->ranking) {
            5 => '⭐⭐⭐⭐⭐ Top Excellent (5/5)',
            4 => '⭐⭐⭐⭐ Very Good (4/5)',
            3 => '⭐⭐⭐ Good Service (3/5)',
            2 => '⭐⭐ Fair Performance (2/5)',
            1 => '⭐ Needs Improvement (1/5)',
            default => 'Not Ranked Yet',
        };
    }

    /**
     * Appointment service line items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(AppointmentService::class, 'appointment_id');
    }

    /**
     * Admin user who approved the discount.
     */
    public function discountApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discount_approved_by');
    }

    /**
     * Discount approval request history logs.
     */
    public function discountRequests(): HasMany
    {
        return $this->hasMany(DiscountRequest::class, 'appointment_id');
    }
}
