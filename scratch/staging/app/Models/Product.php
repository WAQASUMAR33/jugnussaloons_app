<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'price',
        'discount',
        'discounted_price',
        'stock',
        'low_stock',
        'image',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount' => 'decimal:2',
        'discounted_price' => 'decimal:2',
        'stock' => 'integer',
        'low_stock' => 'integer',
    ];

    /**
     * Check if product current stock is at or below the low stock threshold.
     */
    public function isLowStock(): bool
    {
        return $this->stock <= ($this->low_stock ?? 5);
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
