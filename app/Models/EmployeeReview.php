<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'customer_id',
        'appointment_id',
        'rating',
        'comments',
        'is_anonymous',
        'customer_name',
        'customer_phone',
        'ip_address',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_anonymous' => 'boolean',
    ];

    /**
     * Stylist / Employee being reviewed.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'employee_id');
    }

    /**
     * Customer who submitted the review (if identified).
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'customer_id');
    }

    /**
     * Associated appointment booking (if linked).
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    /**
     * Display name of the reviewer.
     */
    public function getReviewerDisplayNameAttribute(): string
    {
        if ($this->is_anonymous) {
            return 'Anonymous Client';
        }

        if ($this->customer) {
            return $this->customer->name;
        }

        return $this->customer_name ?: 'Verified Client';
    }
}
