<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'requested_by_user_id',
        'gross_amount',
        'discount_amount',
        'discount_percentage',
        'status',
        'action_by_user_id',
        'notes',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_percentage' => 'decimal:2',
    ];

    /**
     * Target appointment booking relationship.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    /**
     * User who requested the discount.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * Admin user who approved or rejected the discount request.
     */
    public function actionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'action_by_user_id');
    }
}
