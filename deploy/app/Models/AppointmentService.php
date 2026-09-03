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
        'saloon_service_id',
        'custom_title',
        'quantity',
        'price',
        'discount',
        'discounted_price',
        'commission',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'discount' => 'decimal:2',
        'discounted_price' => 'decimal:2',
        'commission' => 'decimal:2',
    ];

    protected $appends = ['display_title'];

    public function getDisplayTitleAttribute(): string
    {
        return $this->custom_title ?: ($this->service->title ?? 'Service');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(SaloonService::class, 'saloon_service_id');
    }
}
