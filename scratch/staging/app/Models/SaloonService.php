<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaloonService extends Model
{
    use HasFactory;

    protected $table = 'services';

    protected $fillable = [
        'service_category_id',
        'title',
        'description',
        'price',
        'discount',
        'discounted_price',
        'commission',
        'junior_commission',
        'senior_commission',
        'image',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount' => 'decimal:2',
        'discounted_price' => 'decimal:2',
        'commission' => 'decimal:2',
        'junior_commission' => 'decimal:2',
        'senior_commission' => 'decimal:2',
    ];

    /**
     * Service category relationship.
     */
    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    /**
     * Get calculated discounted price if not set manually.
     */
    public function calculateDiscountedPrice(): float
    {
        if ($this->discount > 0) {
            return round($this->price - ($this->price * ($this->discount / 100)), 2);
        }
        return (float) $this->price;
    }
}
